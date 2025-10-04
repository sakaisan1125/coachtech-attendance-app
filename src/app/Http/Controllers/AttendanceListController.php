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
        $monthString = $request->query('month', now()->format('Y-m'));
        $firstDayOfMonth = Carbon::createFromFormat('Y-m', $monthString)->startOfMonth();
        $startDate = $firstDayOfMonth->copy();
        $endDate = $firstDayOfMonth->copy()->endOfMonth();

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

        $dailyAttendanceList = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->toDateString();

            $attendance = $attendanceRecords->get($dateKey)
                ?? Attendance::with('breaks')
                    ->where('user_id', $currentUser->id)
                    ->whereDate('work_date', $dateKey)
                    ->first();

            $correctionRequest = $attendance
                ? CorrectionRequest::with('breaks')
                    ->where('attendance_id', $attendance->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->latest()
                    ->first()
                : null;

            $isPending = $correctionRequest && $correctionRequest->status === 'pending';

            if ($isPending) {
                $clockInTime = $correctionRequest?->requested_clock_in_at
                    ? Carbon::parse($correctionRequest->requested_clock_in_at)->format('H:i')
                    : '';
                $clockOutTime = $correctionRequest?->requested_clock_out_at
                    ? Carbon::parse($correctionRequest->requested_clock_out_at)->format('H:i')
                    : '';
                $totalBreakMinutes = 0;
                foreach ($correctionRequest->breaks ?? [] as $break) {
                    if ($break->requested_break_start_at && $break->requested_break_end_at) {
                        $start = Carbon::parse($break->requested_break_start_at);
                        $end = Carbon::parse($break->requested_break_end_at);
                        $totalBreakMinutes += $start->diffInMinutes($end);
                    }
                }
            } else {
                $clockInTime = $attendance?->clock_in_at ? Carbon::parse($attendance->clock_in_at)->format('H:i') : '';
                $clockOutTime = $attendance?->clock_out_at ? Carbon::parse($attendance->clock_out_at)->format('H:i') : '';
                $totalBreakMinutes = 0;
                foreach ($attendance?->breaks ?? [] as $break) {
                    if (!empty($break->break_start_at) && !empty($break->break_end_at)) {
                        $start = $break->break_start_at instanceof Carbon ? $break->break_start_at : Carbon::parse($break->break_start_at);
                        $end = $break->break_end_at instanceof Carbon ? $break->break_end_at : Carbon::parse($break->break_end_at);
                        $totalBreakMinutes += $start->diffInMinutes($end);
                    }
                }
            }

            $totalWorkMinutes = null;
            if ($clockInTime && $clockOutTime) {
                $in = Carbon::createFromFormat('H:i', $clockInTime);
                $out = Carbon::createFromFormat('H:i', $clockOutTime);
                $totalWorkMinutes = max(0, $out->diffInMinutes($in) * -1 - $totalBreakMinutes);
            }

            $detailUrl = $attendance
                ? "/attendance/detail/{$attendance->id}"
                : route('attendance.detail.by_date', ['date' => $dateKey]);

            $dailyAttendanceList[] = [
                'date' => $date->copy(),
                'clock_in' => $clockInTime,
                'clock_out' => $clockOutTime,
                'break_hm' => $totalBreakMinutes ? $this->toHm($totalBreakMinutes) : '',
                'total_hm' => is_null($totalWorkMinutes) ? '' : $this->toHm($totalWorkMinutes),
                'detail_url' => $detailUrl,
            ];
        }

        $previousMonth = $firstDayOfMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $firstDayOfMonth->copy()->addMonth()->format('Y-m');

        return view('attendance.list', [
            'month' => $firstDayOfMonth,
            'days' => $dailyAttendanceList,
            'prevMonth' => $previousMonth,
            'nextMonth' => $nextMonth,
        ]);
    }

    private function toHm(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf('%d:%02d', $hours, $mins);
    }
}