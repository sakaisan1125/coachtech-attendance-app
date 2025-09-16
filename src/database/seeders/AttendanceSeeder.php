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
        $months = [8, 9, 10];
        $year = Carbon::now()->year;
        $userIds = \App\Models\User::where('role', 'user')->pluck('id')->toArray();

        foreach ($userIds as $userId) {
            for ($i = 0; $i < 30; $i++) {
                $month = $months[array_rand($months)];
                $day = rand(1, 28);

                $workDate = Carbon::create($year, $month, $day);
                $startHour = rand(7, 10);
                $endHour = $startHour + rand(7, 10);

                $attendance = Attendance::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'work_date' => $workDate->toDateString(),
                    ],
                    [
                        'clock_in_at' => $workDate->copy()->setTime($startHour, 0),
                        'clock_out_at' => $workDate->copy()->setTime($endHour, 0),
                    ]
                );

                // 休憩データを出勤・退勤ごとに作成
                $breakCount = rand(1, 2);
                $breakStart = $startHour + rand(2, 4);
                for ($b = 0; $b < $breakCount; $b++) {
                    $breakEnd = $breakStart + rand(1, 2);
                    BreakModel::updateOrCreate(
                        [
                            'attendance_id' => $attendance->id,
                            'break_start_at' => $workDate->copy()->setTime($breakStart, 0),
                        ],
                        [
                            'break_end_at' => $workDate->copy()->setTime($breakEnd, 0),
                        ]
                    );
                    $breakStart = $breakEnd + rand(1, 2);
                    if ($breakStart >= $endHour) break;
                }
            }
        }
    }
}