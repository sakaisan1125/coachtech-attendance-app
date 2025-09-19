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

        // 勤怠データ取得
        $attendanceData = Attendance::with('user', 'breaks')
            ->whereDate('work_date', $date->toDateString())
            ->get()
            ->map(function ($row) {
                // 出勤・退勤
                $clockIn  = $row->clock_in_at;
                $clockOut = $row->clock_out_at;

                // 休憩合計（分）
                $breakMinutes = 0;
                foreach ($row->breaks as $break) {
                    if ($break->break_start_at && $break->break_end_at && $break->break_end_at > $break->break_start_at) {
                        $breakMinutes += $break->break_start_at->diffInMinutes($break->break_end_at);
                    }
                }

                // 労働合計（分）
                $workMinutes = null;
                if ($clockIn && $clockOut && $clockOut > $clockIn) {
                    $workMinutes = $clockIn->diffInMinutes($clockOut) - $breakMinutes;
                    if ($workMinutes < 0) $workMinutes = 0;
                }

                // H:i形式に変換
                $toHm = fn($min) => sprintf('%d:%02d', intdiv($min, 60), $min % 60);

                $row->breaks_sum = $breakMinutes ? $toHm($breakMinutes) : '';
                $row->work_sum   = !is_null($workMinutes) ? $toHm($workMinutes) : '';

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
        $attendance = Attendance::with(['user','breaks'])->findOrFail($id);

        $correctionRequest = CorrectionRequest::with('breaks')
            ->where('attendance_id', $attendance->id)
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->latest()
            ->first();
        $hasPending = $correctionRequest && $correctionRequest->status === 'pending';

        return view('admin.detail', [
            'attendance' => $attendance,
            'user'       => $attendance->user,
            'workDate'   => Carbon::parse($attendance->work_date)->format('Y年n月j日'),
            // 画面は H:i 表示に揃える
            'clockIn'    => $attendance->clock_in_at?->format('H:i'),
            'clockOut'   => $attendance->clock_out_at?->format('H:i'),
            'breaks'     => $attendance->breaks->map(fn($b)=>[
                                'start'=>$b->break_start_at?->format('H:i'),
                                'end'  =>$b->break_end_at?->format('H:i'),
                            ])->values()->all(),
            'hasPending' => $hasPending,
        ]);
    }

    /**
     * 勤怠の管理者修正（直接反映）
     */
    public function adminAttendanceUpdate(Request $request, int $id)
    {
        $attendance = Attendance::with('breaks')->findOrFail($id);

        // 入力値（H:i 形式）。未入力は null 許容
        $data = $request->validate([
            'clock_in'           => ['nullable','date_format:H:i'],
            'clock_out'          => ['nullable','date_format:H:i'],
            'notes'              => ['nullable','string','max:1000'],
            // 休憩は可変配列 breaks[n][start/end]
            'breaks'             => ['array'],
            'breaks.*.start'     => ['nullable','date_format:H:i'],
            'breaks.*.end'       => ['nullable','date_format:H:i'],
        ]);

        $workDate = Carbon::parse($attendance->work_date)->toDateString(); // Y-m-d

        // H:i を当日の DateTime へ
        $toDateTime = function(?string $hm) use ($workDate) {
            if (!$hm) return null;
            return Carbon::createFromFormat('Y-m-d H:i', "$workDate $hm");
        };

        $clockIn  = $toDateTime($data['clock_in']  ?? null);
        $clockOut = $toDateTime($data['clock_out'] ?? null);

        // 相関チェック
        if ($clockIn && $clockOut && $clockIn->gte($clockOut)) {
            return back()->withInput()->withErrors([
                'clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
            ]);
        }

        // 休憩の検証（順序：出勤 <= 休憩開始 < 休憩終了 <= 退勤）
        $breakPayloads = [];
        foreach ($data['breaks'] ?? [] as $i => $br) {
            $start = $toDateTime($br['start'] ?? null);
            $end   = $toDateTime($br['end']   ?? null);

            // 片方だけの入力は無視（空行的な扱い）
            if (!$start && !$end) continue;

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

            $breakPayloads[] = ['break_start_at'=>$start, 'break_end_at'=>$end];
        }

        DB::transaction(function () use ($attendance, $clockIn, $clockOut, $data, $breakPayloads) {
            // 勤怠の更新
            $attendance->clock_in_at  = $clockIn;
            $attendance->clock_out_at = $clockOut;
            $attendance->notes        = $data['notes'] ?? null;
            $attendance->save();

            // 休憩は入れ替え
            BreakModel::where('attendance_id', $attendance->id)->delete();
            foreach ($breakPayloads as $bp) {
                BreakModel::create([
                    'attendance_id'  => $attendance->id,
                    'break_start_at' => $bp['break_start_at'],
                    'break_end_at'   => $bp['break_end_at'],
                ]);
            }
        });

        return redirect()->route('admin.attendance.list', ['id'=>$attendance->id])
            ->with('success','修正しました。');
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
        $attendance->clock_in_at  = $attendance_correct_request->requested_clock_in_at;
        $attendance->clock_out_at = $attendance_correct_request->requested_clock_out_at;
        $attendance->notes        = $attendance_correct_request->requested_notes;
        $attendance->save();

        $attendance_correct_request ->status = 'approved';
        $attendance_correct_request ->approved_at = now();
        $attendance_correct_request ->approved_by = Auth::id();
        $attendance_correct_request ->save();

        return redirect()->route('requests.pending', ['attendance_correct_request' => $attendance_correct_request->id])
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

        $month = $request->input('month', now()) ->format('Y-m');
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate   = $startDate->copy()->endOfMonth();

        $attendances = Attendance::with('breaks')
            ->where('user_id', $staff->id)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->orderBy('work_date')
            ->get();

        $dailyAttendanceList = [];
        foreach ($attendances as $attendance) {
            $date = Carbon::parse($attendance->work_date);
            $clockIn  = $attendance->clock_in_at ? $attendance->clock_in_at->format('H:i') : '';
            $clockOut = $attendance->clock_out_at ? $attendance->clock_out_at->format('H:i') : '';
            $breakMinutes = 0;
            foreach ($attendance->breaks as $break) {
                if ($break->break_start_at && $break->break_end_at) {
                    $breakMinutes += $break->break_start_at->diffInMinutes($break->break_end_at);
                }
            }
            $workMinutes = ($clockIn && $clockOut) ? Carbon::createFromFormat('H:i', $clockIn)->diffInMinutes(Carbon::createFromFormat('H:i', $clockOut)) - $breakMinutes : null;
            if ($workMinutes < 0) $workMinutes = 0;

            $toHm = fn($min) => sprintf('%d:%02d', intdiv($min, 60), $min % 60);

            $dailyAttendanceList[] = [
                'date'       => $date,
                'clock_in'   => $clockIn,
                'clock_out'  => $clockOut,
                'break_hm'   => $breakMinutes ? $toHm($breakMinutes) : '',
                'total_hm'   => !is_null($workMinutes) ? $toHm($workMinutes) : '',
                'detail_url' => route('admin.detail', ['id' => $attendance->id]),
            ];
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
}
