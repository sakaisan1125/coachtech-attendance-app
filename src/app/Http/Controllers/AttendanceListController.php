<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CorrectionRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceListController extends Controller
{
    public function index(Request $request)
    {   
        Carbon::setLocale('ja');

        $currentUser = $request->user();

        // ?month=YYYY-MM（省略時は今月）
        $monthString = $request->query('month', now()->format('Y-m'));
        $firstDayOfMonth = Carbon::createFromFormat('Y-m', $monthString)->startOfMonth();
        $startDate = $firstDayOfMonth->copy();
        $endDate   = $firstDayOfMonth->copy()->endOfMonth();

        // 今月の勤怠＋休憩をまとめて取得
        $attendanceRecords = Attendance::with('breaks')
            ->where('user_id', $currentUser->id)
            ->whereBetween('work_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->mapWithKeys(function ($attendance) {
                $dateKey = $attendance->work_date instanceof Carbon
                    ? $attendance->work_date->format('Y-m-d')
                    : (string)$attendance->work_date;
                return [$dateKey => $attendance];
            });

        // 一覧用データを日別に組み立て
        $dailyAttendanceList = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->toDateString();
            $attendance = $attendanceRecords->get($dateKey);

            $correctionRequest = $attendance
                ? CorrectionRequest::with('breaks')
                    ->where('attendance_id', $attendance->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->latest()
                    ->first()
                : null;

            // 出勤・退勤時刻（修正申請があればそちらを優先）
            $clockInTime  = $correctionRequest?->requested_clock_in_at
                ? Carbon::parse($correctionRequest->requested_clock_in_at)->format('H:i')
                : ($attendance?->clock_in_at?->format('H:i') ?? '');

            $clockOutTime = $correctionRequest?->requested_clock_out_at
                ? Carbon::parse($correctionRequest->requested_clock_out_at)->format('H:i')
                : ($attendance?->clock_out_at?->format('H:i') ?? '');

            // 休憩合計（分） 修正申請があればそちらを優先
            $totalBreakMinutes = 0;
            if ($correctionRequest && $correctionRequest->breaks->count()) {
                foreach ($correctionRequest->breaks as $break) {
                    if ($break->requested_break_start_at && $break->requested_break_end_at) {
                        $start = $break->requested_break_start_at instanceof Carbon
                            ? $break->requested_break_start_at
                            : Carbon::parse($break->requested_break_start_at);
                        $end = $break->requested_break_end_at instanceof Carbon
                            ? $break->requested_break_end_at
                            : Carbon::parse($break->requested_break_end_at);
                        $totalBreakMinutes += $start->diffInMinutes($end);
                    }
                }
            } else {
                foreach ($attendance?->breaks ?? [] as $break) {
                    if ($break->break_start_at && $break->break_end_at) {
                        $totalBreakMinutes += $break->break_start_at->diffInMinutes($break->break_end_at);
                    }
                }
            }

            // 合計（分）＝ 退勤-出勤 - 休憩（両方ある時だけ計算）
            $totalWorkMinutes = null;
            if ($clockInTime && $clockOutTime) {
                $in = Carbon::createFromFormat('H:i', $clockInTime);
                $out = Carbon::createFromFormat('H:i', $clockOutTime);
                $totalWorkMinutes = $in->diffInMinutes($out) - $totalBreakMinutes;
                if ($totalWorkMinutes < 0) $totalWorkMinutes = 0;
            }

            $dailyAttendanceList[] = [
                'date'        => $date->copy(),
                'clock_in'    => $clockInTime, 
                'clock_out'   => $clockOutTime,
                'break_hm'    => $totalBreakMinutes ? $this->toHm($totalBreakMinutes) : '',
                'total_hm'    => is_null($totalWorkMinutes) ? '' : $this->toHm($totalWorkMinutes),
                'detail_url'  => $attendance ? route('attendance.detail', ['id' => $attendance->id]) : null,
            ];
        }

        $previousMonth = $firstDayOfMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $firstDayOfMonth->copy()->addMonth()->format('Y-m');

        return view('attendance.list', [
            'month'        => $firstDayOfMonth,
            'days'         => $dailyAttendanceList,
            'prevMonth'    => $previousMonth,
            'nextMonth'    => $nextMonth,
        ]);
    }

    private function toHm(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf('%d:%02d', $hours, $mins);
    }
}