<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Attendance;

class AttendanceClockOutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_show_clock_out_button_and_clock_out(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 今日の勤怠を「勤務中」で作成
        Attendance::factory()->create([
            'user_id' => $user->id,
            'status' => 'on_duty',
            'work_date' => now()->toDateString(),
        ]);

        $response = $this->get('/attendance');
        $response->assertSee('退勤');
        $postResponse = $this->post(route('attendance.clock_out'));
        $postResponse->assertRedirect('/attendance');

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', now())
            ->first();
        $this->assertNotNull($attendance);
        $this->assertEquals('clocked_out', $attendance->status);
        $response = $this->get('/attendance');
        $response->assertSee('退勤済');

    }

    #[Test]
    public function test_clock_out_time_is_visible_in_attendance_list(): void
    {
        // 1. ステータスが勤務中のユーザーでログイン
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 今日の勤怠を「勤務中」で作成
        Attendance::factory()->create([
            'user_id' => $user->id,
            'status' => 'off_duty',
            'work_date' => now()->toDateString(),
        ]);

        // 休憩1回目
        $this->post(route('attendance.clock_in'));
        $this->post(route('attendance.clock_out'));
        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', now())
            ->first();
        $clockOutTime = optional($attendance->clock_out_at)->format('H:i');

        $response = $this->get('/attendance/list');
        $response->assertSee($clockOutTime);
    }


}