<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use PHPUnit\Framework\Attributes\Test;

class AttendanceDetailUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_attendance_detail_shows_logged_in_user_name()
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'status' => 'clocked_out',
        ]);

        $response = $this->get('/attendance/detail/' . $attendance->id);
        $response->assertSee($user->name);
    }

    #[Test]
    public function test_attendance_detail_shows_selected_date()
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $selectedDate = now()->subDays(3)->toDateString();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $selectedDate,
            'status' => 'clocked_out',
        ]);

        $response = $this->get('/attendance/detail/' . $attendance->id);

        $carbon = \Carbon\Carbon::parse($selectedDate);
        $response->assertSee($carbon->format('Y年'));
        $response->assertSee($carbon->format('n月j日'));
    }

    #[Test]
    public function test_attendance_detail_shows_correct_clock_in_and_out_times()
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $clockIn = now()->setTime(9, 0);
        $clockOut = now()->setTime(18, 0);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'status' => 'clocked_out',
        ]);

        $response = $this->get('/attendance/detail/' . $attendance->id);
        $response->assertSee($clockIn->format('H:i'));
        $response->assertSee($clockOut->format('H:i'));
    }

    #[Test]
    public function test_attendance_detail_shows_correct_break_times()
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $breakStart = now()->setTime(11, 0);
        $breakEnd = now()->setTime(13, 0);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'status' => 'clocked_out',
        ]);
        $attendance->breaks()->create([
            'break_start_at' => $breakStart,
            'break_end_at' => $breakEnd,
        ]);

        $response = $this->get('/attendance/detail/' . $attendance->id);
        $response->assertSee($breakStart->format('H:i'));
        $response->assertSee($breakEnd->format('H:i'));
    }
}