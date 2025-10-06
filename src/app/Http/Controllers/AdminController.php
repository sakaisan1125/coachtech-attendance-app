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
    private function effectiveBreakSegments(Attendance $attendance): array
    {
        if ($attendance->breaks->count()) {
            return $attendance->breaks
                ->filter(fn($b) => $b->break_start_at && $b->break_end_at && $b->break_end_at > $b->break_start_at)
                ->sortBy('break_start_at')
                ->map(fn($b) => [
                    'start'   => $b->break_start_at->format('H:i'),
                    'end'     => $b->break_end_at->format('H:i'),
                    'minutes' => $b->break_start_at->diffInMinutes($b->break_end_at),
                    'pending' => false,
                ])->values()->all();
        }

        $cr = $attendance->correctionRequests
            ->whereIn('status', ['pending', 'approved'])
            ->sortByDesc('id')
            ->first();

        if ($cr && $cr->breaks->count()) {
            $seg = $cr->breaks
                ->filter(fn($b) => $b->requested_break_start_at && $b->requested_break_end_at && $b->requested_break_end_at > $b->requested_break_start_at)
                ->sortBy('requested_break_start_at')
                ->map(fn($b) => [
                    'start'   => $b->requested_break_start_at->format('H:i'),
                    'end'     => $b->requested_break_end_at->format('H:i'),
                    'minutes' => $b->requested_break_start_at->diffInMinutes($b->requested_break_end_at),
                    'pending' => $cr->status === 'pending',
                ])->values()->all();
            if ($seg) {
                return $seg;
            }
        }
        return [];
    }

    public function adminAttendanceList(Request $request)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }

        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $attendanceData = Attendance::with([
                'user',
                'breaks',
                'correctionRequests.breaks',
            ])
            ->whereDate('work_date', $date->toDateString())
            ->whereHas('user', fn($q) => $q->where('role', 'user'))
            ->orderBy('user_id')
            ->get()
            ->map(function ($row) {
                $segments = $this->effectiveBreakSegments($row);
                $breakMinutes = array_sum(array_column($segments, 'minutes'));

                $pendingCr = $row->correctionRequests
                    ->where('status', 'pending')
                    ->sortByDesc('id')
                    ->first();

                $clockIn  = $row->clock_in_at ?? ($pendingCr?->requested_clock_in_at);
                $clockOut = $row->clock_out_at ?? ($pendingCr?->requested_clock_out_at);

                $workMinutes = null;
                if ($clockIn && $clockOut) {
                    $in  = Carbon::parse($clockIn);
                    $out = Carbon::parse($clockOut);
                    if ($out->gt($in)) {
                        $workMinutes = $in->diffInMinutes($out) - $breakMinutes;
                        if ($workMinutes < 0) {
                            $workMinutes = 0;
                        }
                    }
                }

                $fmt = fn($m) => $m ? sprintf('%d:%02d', intdiv($m, 60), $m % 60) : '';

                $row->display_in  = $clockIn  ? Carbon::parse($clockIn)->format('H:i') : '-';
                $row->display_out = $clockOut ? Carbon::parse($clockOut)->format('H:i') : '-';
                $row->breaks_sum  = $breakMinutes ? $fmt($breakMinutes) : '';
                $row->work_sum    = $workMinutes !== null ? $fmt($workMinutes) : '';

                return $row;
            });

        return view('admin.list', compact('attendanceData', 'date'));
    }

    public function adminRequestPending(Request $request)
    {
        $rows = CorrectionRequest::with(['attendance.user'])
            ->where('status', 'pending')
            ->latest('id')
            ->get();
        return view('admin.request', ['activeTab' => 'pending', 'rows' => $rows]);
    }

    public function adminRequestApproved(Request $request)
    {
        $rows = CorrectionRequest::with(['attendance.user'])
            ->where('status', 'approved')
            ->latest('approved_at')
            ->get();
        return view('admin.request', ['activeTab' => 'approved', 'rows' => $rows]);
    }

    public function adminAttendanceShow(Request $request, int $id)
    {
        $attendance = Attendance::with(['user', 'breaks', 'correctionRequests.breaks'])->findOrFail($id);

        $pendingCr = $attendance->correctionRequests
            ->where('status', 'pending')
            ->sortByDesc('id')
            ->first();

        $workDate = Carbon::parse($attendance->work_date)->format('Y-m-d');

        $normalize = function ($val) use ($workDate) {
            if (!$val) {
                return null;
            }
            if (is_string($val) && preg_match('/^\d{2}:\d{2}$/', $val)) {
                return Carbon::createFromFormat('Y-m-d H:i', "$workDate $val");
            }
            return Carbon::parse($val);
        };

        $clockInDt  = $normalize($attendance->clock_in_at ?? ($pendingCr?->requested_clock_in_at));
        $clockOutDt = $normalize($attendance->clock_out_at ?? ($pendingCr?->requested_clock_out_at));

        if ($clockInDt && $clockOutDt && $clockOutDt->lte($clockInDt)) {
            $clockOutDt = $clockOutDt->copy()->addDay();
        }

        $breaksArray = $attendance->breaks
            ->map(fn($b) => [
                'start' => $b->break_start_at?->format('H:i'),
                'end'   => $b->break_end_at?->format('H:i'),
            ])->values()->all();

        if (empty($breaksArray) && $pendingCr) {
            $breaksArray = $pendingCr->breaks
                ->filter(fn($b) => $b->requested_break_start_at && $b->requested_break_end_at)
                ->sortBy('requested_break_start_at')
                ->map(fn($b) => [
                    'start' => $b->requested_break_start_at->format('H:i'),
                    'end'   => $b->requested_break_end_at->format('H:i'),
                ])->values()->all();
        }

        $correctionRequest = $pendingCr
            ?: $attendance->correctionRequests->sortByDesc('id')->first();

        $hasPending = $correctionRequest && $correctionRequest->status === 'pending';

        return view('admin.detail', [
            'attendance'        => $attendance,
            'user'              => $attendance->user,
            'workDate'          => Carbon::parse($attendance->work_date)->format('Y年n月j日'),
            'clockIn'           => $clockInDt?->format('H:i'),
            'clockOut'          => $clockOutDt?->format('H:i'),
            'breaks'            => $breaksArray,
            'hasPending'        => $hasPending,
            'correctionRequest' => $correctionRequest,
        ]);
    }

    public function adminAttendanceUpdate(Request $request, int $id)
    {
        $attendance = Attendance::with('breaks')->findOrFail($id);

        $rules = [
            'clock_in'       => ['nullable', 'date_format:H:i'],
            'clock_out'      => ['nullable', 'date_format:H:i'],
            'notes'          => ['required', 'string', 'max:1000'],
            'breaks'         => ['array'],
            'breaks.*.start' => ['nullable', 'date_format:H:i'],
            'breaks.*.end'   => ['nullable', 'date_format:H:i'],
        ];

        $messages = [
            'notes.required'            => '備考を記入してください',
            'clock_in.date_format'      => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_out.date_format'     => '出勤時間もしくは退勤時間が不適切な値です',
            'breaks.*.start.date_format'=> '休憩時間が不適切な値です',
            'breaks.*.end.date_format'  => '休憩時間が不適切な値です',
        ];

        $validator = \Validator::make($request->all(), $rules, $messages);

        $validator->after(function ($v) use ($request, $attendance) {
            $workDate = Carbon::parse($attendance->work_date)->toDateString();
            $toDt = fn($hm) => $hm ? Carbon::createFromFormat('Y-m-d H:i', "$workDate $hm") : null;

            $clockIn  = $toDt($request->input('clock_in'));
            $clockOut = $toDt($request->input('clock_out'));

            if ($clockIn && $clockOut && $clockIn->gte($clockOut)) {
                $v->errors()->add('clock_out', '出勤時間もしくは退勤時間が不適切な値です');
            }

            $breaks = $request->input('breaks', []);
            foreach ($breaks as $i => $br) {
                $start = $toDt($br['start'] ?? null);
                $end   = $toDt($br['end'] ?? null);
                if (!$start && !$end) {
                    continue;
                }
                if (!$start || !$end || $start->gte($end)) {
                    $v->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                    continue;
                }
                if ($clockIn && $start->lt($clockIn)) {
                    $v->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                }
                if ($clockOut && $end->gt($clockOut)) {
                    $v->errors()->add("breaks.$i.end", '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $workDate = Carbon::parse($attendance->work_date)->toDateString();
        $toDt = fn($hm) => $hm ? Carbon::createFromFormat('Y-m-d H:i', "$workDate $hm") : null;
        $clockIn  = $toDt($data['clock_in'] ?? null);
        $clockOut = $toDt($data['clock_out'] ?? null);

        $breakPayloads = [];
        foreach ($request->input('breaks', []) as $br) {
            $start = $toDt($br['start'] ?? null);
            $end   = $toDt($br['end'] ?? null);
            if ($start && $end && $start->lt($end)) {
                $breakPayloads[] = [
                    'break_start_at' => $start,
                    'break_end_at'   => $end,
                ];
            }
        }

        \DB::transaction(function () use ($attendance, $clockIn, $clockOut, $data, $breakPayloads) {
            $attendance->clock_in_at  = $clockIn;
            $attendance->clock_out_at = $clockOut;
            $attendance->notes        = $data['notes'] ?? null;
            $attendance->save();

            BreakModel::where('attendance_id', $attendance->id)->delete();
            foreach ($breakPayloads as $bp) {
                BreakModel::create([
                    'attendance_id'  => $attendance->id,
                    'break_start_at' => $bp['break_start_at'],
                    'break_end_at'   => $bp['break_end_at'],
                ]);
            }
        });

        return redirect()
            ->route('admin.attendance.list', ['date' => $attendance->work_date->toDateString()])
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
            'attendance'        => $attendance,
            'user'              => $user,
            'hasPending'        => $hasPending,
            'isApproved'        => $isApproved,
        ]);
    }

    public function approveCorrectionRequest(Request $request, CorrectionRequest $attendance_correct_request)
    {
        $attendance_correct_request->load(['breaks', 'attendance.breaks']);
        $attendance = $attendance_correct_request->attendance;

        DB::transaction(function () use ($attendance, $attendance_correct_request) {
            $attendance->clock_in_at  = $attendance_correct_request->requested_clock_in_at;
            $attendance->clock_out_at = $attendance_correct_request->requested_clock_out_at;
            $attendance->notes        = $attendance_correct_request->requested_notes;
            $attendance->save();

            BreakModel::where('attendance_id', $attendance->id)->delete();
            foreach ($attendance_correct_request->breaks as $br) {
                if ($br->requested_break_start_at && $br->requested_break_end_at) {
                    BreakModel::create([
                        'attendance_id'  => $attendance->id,
                        'break_start_at' => $br->requested_break_start_at,
                        'break_end_at'   => $br->requested_break_end_at,
                    ]);
                }
            }

            $attendance_correct_request->status      = 'approved';
            $attendance_correct_request->approved_at = now();
            $attendance_correct_request->approved_by = Auth::id();
            $attendance_correct_request->save();
        });

        return redirect()
            ->route('admin.approve', ['attendance_correct_request' => $attendance_correct_request->id])
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
        $endDate   = $startDate->copy()->endOfMonth();

        $attendances = Attendance::with(['breaks', 'correctionRequests.breaks'])
            ->where('user_id', $staff->id)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->orderBy('work_date')
            ->get();

        $attendanceRecords = $attendances->keyBy(fn($a) => Carbon::parse($a->work_date)->toDateString());

        $dailyAttendanceList = [];
        $fmt = fn($m) => $m ? sprintf('%d:%02d', intdiv($m, 60), $m % 60) : '';

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->toDateString();
            $attendance = $attendanceRecords->get($dateKey);

            if ($attendance) {
                $segments = $this->effectiveBreakSegments($attendance);
                $breakMinutes = array_sum(array_column($segments, 'minutes'));

                $pendingCr = $attendance->correctionRequests
                    ->where('status', 'pending')
                    ->sortByDesc('id')
                    ->first();

                $clockIn  = $attendance->clock_in_at ?? ($pendingCr?->requested_clock_in_at);
                $clockOut = $attendance->clock_out_at ?? ($pendingCr?->requested_clock_out_at);

                $workMinutes = null;
                if ($clockIn && $clockOut) {
                    $in  = Carbon::parse($clockIn);
                    $out = Carbon::parse($clockOut);
                    if ($out->gt($in)) {
                        $workMinutes = $in->diffInMinutes($out) - $breakMinutes;
                        if ($workMinutes < 0) {
                            $workMinutes = 0;
                        }
                    }
                }

                $dailyAttendanceList[] = [
                    'date'       => $date->copy(),
                    'clock_in'   => $clockIn  ? Carbon::parse($clockIn)->format('H:i') : '',
                    'clock_out'  => $clockOut ? Carbon::parse($clockOut)->format('H:i') : '',
                    'break_hm'   => $breakMinutes ? $fmt($breakMinutes) : '',
                    'total_hm'   => $workMinutes !== null ? $fmt($workMinutes) : '',
                    'detail_url' => route('admin.detail', ['id' => $attendance->id]),
                ];
            } else {
                $dailyAttendanceList[] = [
                    'date'       => $date->copy(),
                    'clock_in'   => '',
                    'clock_out'  => '',
                    'break_hm'   => '',
                    'total_hm'   => '',
                    'detail_url' => null,
                ];
            }
        }

        return view('admin.staff-attendance-list', [
            'staff'               => $staff,
            'attendances'         => $attendances,
            'month'               => $month,
            'startDate'           => $startDate,
            'endDate'             => $endDate,
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
