<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null, 
            'work_date' => Carbon::now()->toDateString(),
            'status' => 'off_duty',
            'clock_in_at' => null,
            'clock_out_at' => null,
        ];
    }
}