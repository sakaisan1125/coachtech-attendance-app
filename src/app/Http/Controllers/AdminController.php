<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\User;
use App\Models\BreakModel;
use App\Models\CorrectionRequest;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function adminAttendanceList(Request $request)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }

        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $attendanceData = Attendance::with(['user', 'breaks'])
            ->whereDate('work_date', $date->toDateString())
            ->whereHas('user', function ($q) {
                $q->where('role', 'user');
            })
            ->get()
            ->map(function ($row) {
                $clockIn = $row->clock_in_at;
                $clockOut = $row->clock_out_at;

                $breakMinutes = 0;
                foreach ($row->breaks as $break) {
                    if ($break->break_start_at && $break->break_end_at && $break->break_end_at > $break->break_start_at) {
                        $breakMinutes += $break->break_start_at->diffInMinutes($break->break_end_at);
                    }
                }

                $workMinutes = null;
                if ($clockIn && $clockOut && $clockOut > $clockIn) {
                    $workMinutes = $clockIn->diffInMinutes($clockOut) - $breakMinutes;
                    if ($workMinutes < 0) {
                        $workMinutes = 0;
                    }
                }

                $toHm = fn($min) => sprintf('%d:%02d', intdiv($min, 60), $min % 60);

                $row->breaks_sum = $breakMinutes ? $toHm($breakMinutes) : '';
                $row->work_sum = !is_null($workMinutes) ? $toHm($workMinutes) : '';
                return $row;
            });

        return view('admin.list', compact('attendanceData', 'date'));
    }

    public function adminRequestPending(Request $request)
    {
        $rows = CorrectionRequest::with(['attendance.user'])->where('status', 'pending')->latest('id')->get();
        return view('admin.request', ['activeTab' => 'pending', 'rows' => $rows]);
    }

    public function adminRequestApproved(Request $request)
    {
        $rows = CorrectionRequest::with(['attendance.user'])->where('status', 'approved')->latest('approved_at')->get();
        return view('admin.request', ['activeTab' => 'approved', 'rows' => $rows]);
    }

    public function adminAttendanceShow(Request $request, int $id)
    {
        $attendance = Attendance::with(['user', 'breaks'])->findOrFail($id);

        $correctionRequest = CorrectionRequest::with('breaks')
            ->where('attendance_id', $attendance->id)
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->latest()
            ->first();
        $hasPending = $correctionRequest && $correctionRequest->status === 'pending';

        return view('admin.detail', [
            'attendance' => $attendance,
            'user' => $attendance->user,
            'workDate' => Carbon::parse($attendance->work_date)->format('Y年n月j日'),
            'clockIn' => $attendance->clock_in_at?->format('H:i'),
            'clockOut' => $attendance->clock_out_at?->format('H:i'),
            'breaks' => $attendance->breaks->map(fn($b) => [
                'start' => $b->break_start_at?->format('H:i'),
                'end' => $b->break_end_at?->format('H:i'),
            ])->values()->all(),
            'hasPending' => $hasPending,
        ]);
    }

    public function adminAttendanceUpdate(Request $request, int $id)
    {
        $attendance = Attendance::with('breaks')->findOrFail($id);

        $data = $request->validate([
            'clock_in' => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'notes' => ['required', 'string', 'max:1000'],
            'breaks' => ['array'],
            'breaks.*.start' => ['nullable', 'date_format:H:i'],
            'breaks.*.end' => ['nullable', 'date_format:H:i'],
        ], [
            'notes.required' => '備考を入力してください',
        ]);

        $workDate = Carbon::parse($attendance->work_date)->toDateString();

        $toDateTime = function (?string $hm) use ($workDate) {
            if (!$hm) {
                return null;
            }
            return Carbon::createFromFormat('Y-m-d H:i', "$workDate $hm");
        };

        $clockIn = $toDateTime($data['clock_in'] ?? null);
        $clockOut = $toDateTime($data['clock_out'] ?? null);

        if ($clockIn && $clockOut && $clockIn->gte($clockOut)) {
            return back()->withInput()->withErrors([
                'clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
            ]);
        }

        $breakPayloads = [];
        foreach ($data['breaks'] ?? [] as $i => $br) {
            $start = $toDateTime($br['start'] ?? null);
            $end = $toDateTime($br['end'] ?? null);

            if (!$start && !$end) {
                continue;
            }

            if (!$start || !$end || ($start && $end && $start->gte($end))) {
                return back()->withInput()->withErrors([
                    "breaks.$i.start" => '休憩時間が不適切な値です',
                ]);
            }
            if ($clockIn && $start->lt($clockIn)) {
                return back()->withInput()->withErrors([
                    "breaks.$i.start" => '休憩時間が不適切な値です',
                ]);
            }
            if ($clockOut && $end->gt($clockOut)) {
                return back()->withInput()->withErrors([
                    "breaks.$i.end" => '休憩時間もしくは退勤時間が不適切な値です',
                ]);
            }

            $breakPayloads[] = ['break_start_at' => $start, 'break_end_at' => $end];
        }

        DB::transaction(function () use ($attendance, $clockIn, $clockOut, $data, $breakPayloads) {
            $attendance->clock_in_at = $clockIn;
            $attendance->clock_out_at = $clockOut;
            $attendance->notes = $data['notes'] ?? null;
            $attendance->save();

            BreakModel::where('attendance_id', $attendance->id)->delete();
            foreach ($breakPayloads as $bp) {
                BreakModel::create([
                    'attendance_id' => $attendance->id,
                    'break_start_at' => $bp['break_start_at'],
                    'break_end_at' => $bp['break_end_at'],
                ]);
            }
        });

        return redirect()->route('admin.attendance.list', ['id' => $attendance->id])
            ->with('success', '修正しました。');
    }

    public function showApproveRequest(Request $request, CorrectionRequest $attendance_correct_request)
    {
        $correctionRequest = CorrectionRequest::with(['attendance.user', 'breaks'])->findOrFail($attendance_correct_request->id);
        $attendance = $correctionRequest->attendance;
        $user = $attendance->user;
        $hasPending = $correctionRequest->status === 'pending';
        $isApproved = $correctionRequest->status === 'approved';

        return view('admin.approve', [
            'correctionRequest' => $correctionRequest,
            'attendance' => $attendance,
            'user' => $user,
            'hasPending' => $hasPending,
            'isApproved' => $isApproved,
        ]);
    }

    public function approveCorrectionRequest(Request $request, CorrectionRequest $attendance_correct_request)
    {
        $attendance = $attendance_correct_request->attendance;
        $attendance->clock_in_at = $attendance_correct_request->requested_clock_in_at;
        $attendance->clock_out_at = $attendance_correct_request->requested_clock_out_at;
        $attendance->notes = $attendance_correct_request->requested_notes;
        $attendance->save();

        $attendance_correct_request->status = 'approved';
        $attendance_correct_request->approved_at = now();
        $attendance_correct_request->approved_by = Auth::id();
        $attendance_correct_request->save();

        return redirect()->route('admin.approve', ['attendance_correct_request' => $attendance_correct_request->id])
            ->with('success', '勤怠修正申請を承認しました。');
    }

    public function adminStaffList()
    {
        $staffs = User::where('role', 'user')->get();
        return view('admin.staff', compact('staffs'));
    }

    public function adminStaffAttendanceList(Request $request, int $id)
    {
        Carbon::setLocale('ja');

        $staff = User::findOrFail($id);
        if ($staff->role !== 'user') {
            abort(404);
        }

        $month = $request->input('month') ?: now()->format('Y-m');
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $attendances = Attendance::with('breaks')
            ->where('user_id', $staff->id)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->orderBy('work_date')
            ->get();

        $attendanceRecords = $attendances->keyBy(function ($a) {
            return Carbon::parse($a->work_date)->toDateString();
        });

        $dailyAttendanceList = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->toDateString();
            $attendance = $attendanceRecords->get($dateKey);

            if ($attendance) {
                $clockIn = $attendance->clock_in_at ? $attendance->clock_in_at->format('H:i') : '';
                $clockOut = $attendance->clock_out_at ? $attendance->clock_out_at->format('H:i') : '';
                $breakMinutes = 0;
                foreach ($attendance->breaks as $break) {
                    if ($break->break_start_at && $break->break_end_at) {
                        $breakMinutes += $break->break_start_at->diffInMinutes($break->break_end_at);
                    }
                }
                $workMinutes = ($clockIn && $clockOut)
                    ? Carbon::createFromFormat('H:i', $clockIn)->diffInMinutes(Carbon::createFromFormat('H:i', $clockOut)) - $breakMinutes
                    : null;
                if ($workMinutes < 0) {
                    $workMinutes = 0;
                }
                $toHm = fn($min) => sprintf('%d:%02d', intdiv($min, 60), $min % 60);
                $dailyAttendanceList[] = [
                    'date' => $date->copy(),
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'break_hm' => $breakMinutes ? $toHm($breakMinutes) : '',
                    'total_hm' => !is_null($workMinutes) ? $toHm($workMinutes) : '',
                    'detail_url' => route('admin.detail', ['id' => $attendance->id]),
                ];
            } else {
                $dailyAttendanceList[] = [
                    'date' => $date->copy(),
                    'clock_in' => '',
                    'clock_out' => '',
                    'break_hm' => '',
                    'total_hm' => '',
                    'detail_url' => null,
                ];
            }
        }

        return view('admin.staff_attendance_list', [
            'staff' => $staff,
            'attendances' => $attendances,
            'month' => $month,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dailyAttendanceList' => $dailyAttendanceList,
        ]);
    }

    public function exportStaffAttendanceCsv(Request $request, $id)
    {
        $month = $request->input('month') ?? now()->format('Y-m');
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $staff = User::findOrFail($id);
        $attendances = Attendance::with('breaks')
            ->where('user_id', $staff->id)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->orderBy('work_date')
            ->get();

        $csv = "日付,出勤,退勤,休憩,合計,備考\n";

        foreach ($attendances as $attendance) {
            $date = Carbon::parse($attendance->work_date)->format('Y/m/d');
            $clockIn = $attendance->clock_in_at ? $attendance->clock_in_at->format('H:i') : '';
            $clockOut = $attendance->clock_out_at ? $attendance->clock_out_at->format('H:i') : '';
            $breakMinutes = 0;
            foreach ($attendance->breaks as $break) {
                if ($break->break_start_at && $break->break_end_at) {
                    $breakMinutes += $break->break_start_at->diffInMinutes($break->break_end_at);
                }
            }
            $toHm = fn($min) => $min ? sprintf('%d:%02d', intdiv($min, 60), $min % 60) : '';
            $workMinutes = ($clockIn && $clockOut)
                ? Carbon::createFromFormat('H:i', $clockIn)->diffInMinutes(Carbon::createFromFormat('H:i', $clockOut)) - $breakMinutes
                : null;
            if ($workMinutes < 0) {
                $workMinutes = 0;
            }
            $totalHm = !is_null($workMinutes) ? $toHm($workMinutes) : '';
            $breakHm = $toHm($breakMinutes);

            $notesEscaped = str_replace('"', '""', $attendance->notes);
            $csv .= "{$date},{$clockIn},{$clockOut},{$breakHm},{$totalHm},\"{$notesEscaped}\"\n";
        }

        $filename = "{$staff->name}_{$month}_attendance.csv";
        $csv = "\xEF\xBB\xBF" . $csv;
        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }
}
