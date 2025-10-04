<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\BreakModel;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();
        $start = Carbon::create($today->year, 4, 1);
        if ($today->lt($start)) {
            $start = $start->copy()->subYear();
        }

        $userIds = \App\Models\User::where('role', 'user')->pluck('id');

        foreach ($userIds as $userId) {
            for ($month = $start->copy()->startOfMonth(); $month->lte($today); $month->addMonth()) {
                $periodStart = $month->copy()->max($start);
                $periodEnd = $month->copy()->endOfMonth()->min($today);

                $weekdays = [];
                for ($d = $periodStart->copy(); $d->lte($periodEnd); $d->addDay()) {
                    if (!$d->isWeekend()) {
                        $weekdays[] = $d->copy();
                    }
                }
                if (empty($weekdays)) {
                    continue;
                }

                $target = 21;
                if (count($weekdays) > $target) {
                    shuffle($weekdays);
                    $weekdays = array_slice($weekdays, 0, $target);
                }

                $attIds = Attendance::where('user_id', $userId)
                    ->whereBetween('work_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->pluck('id');

                if ($attIds->isNotEmpty()) {
                    BreakModel::whereIn('attendance_id', $attIds)->delete();
                    Attendance::whereIn('id', $attIds)->delete();
                }

                foreach ($weekdays as $date) {
                    $startHour = rand(7, 10);
                    $endHour = $startHour + rand(7, 10);

                    $attendance = Attendance::create([
                        'user_id' => $userId,
                        'work_date' => $date->toDateString(),
                        'clock_in_at' => $date->copy()->setTime($startHour, 0),
                        'clock_out_at' => $date->copy()->setTime($endHour, 0),
                    ]);

                    $breakCount = rand(1, 2);
                    $breakStartHour = $startHour + rand(2, 4);

                    for ($b = 0; $b < $breakCount; $b++) {
                        $breakEndHour = min($breakStartHour + rand(1, 2), $endHour - 1);
                        if ($breakStartHour >= $endHour || $breakEndHour <= $breakStartHour) {
                            break;
                        }
                        BreakModel::create([
                            'attendance_id' => $attendance->id,
                            'break_start_at' => $date->copy()->setTime($breakStartHour, 0),
                            'break_end_at' => $date->copy()->setTime($breakEndHour, 0),
                        ]);
                        $breakStartHour = $breakEndHour + rand(1, 2);
                    }
                }
            }
        }
    }
}