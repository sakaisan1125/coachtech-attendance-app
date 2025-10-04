<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Attendance;

class AttendanceClockInTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_show_clock_in_button_and_clock_in(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertSee('出勤');

        $postResponse = $this->post(route('attendance.clock_in'));
        $postResponse->assertRedirect('/attendance');

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', now())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertEquals('on_duty', $attendance->status);

        $response = $this->get('/attendance');
        $response->assertSee('出勤中');
    }

    #[Test]
    public function test_clock_in_button_not_visible_after_clocked_out(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Attendance::factory()->create([
            'user_id'   => $user->id,
            'status'    => 'clocked_out',
            'work_date' => now()->toDateString(),
        ]);

        $response = $this->get('/attendance');
        $response->assertSee('お疲れ様でした');
    }

    #[Test]
    public function test_clock_in_time_is_recorded_and_visible_in_list(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $this->get('/attendance');
        $this->post(route('attendance.clock_in'));

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', now())
            ->first();

        $this->assertNotNull($attendance);

        $response = $this->get('/attendance/list');
        $clockInTime = optional($attendance->clock_in_at)->format('H:i');
        $response->assertSee($clockInTime);
    }
}