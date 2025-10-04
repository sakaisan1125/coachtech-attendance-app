<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakModel;
use Carbon\Carbon;

class AttendanceBreakTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_show_break_start_button_and_break_start(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Attendance::factory()->create([
            'user_id'   => $user->id,
            'status'    => 'on_duty',
            'work_date' => now()->toDateString(),
        ]);

        $response = $this->get('/attendance');
        $response->assertSee('休憩入');

        $postResponse = $this->post(route('attendance.break_start'));
        $postResponse->assertRedirect('/attendance');

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', now())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertEquals('on_break', $attendance->status);

        $response = $this->get('/attendance');
        $response->assertSee('休憩中');
    }

    #[Test]
    public function test_break_can_be_started_multiple_times_in_a_day(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Attendance::factory()->create([
            'user_id'   => $user->id,
            'status'    => 'on_duty',
            'work_date' => now()->toDateString(),
        ]);

        $this->post(route('attendance.break_start'));
        $this->post(route('attendance.break_end'));

        $response = $this->get('/attendance');
        $response->assertSee('休憩入');
    }

    #[Test]
    public function test_break_end_can_be_done_multiple_times_in_a_day(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        Attendance::factory()->create([
            'user_id'   => $user->id,
            'status'    => 'on_duty',
            'work_date' => now()->toDateString(),
        ]);

        $this->post(route('attendance.break_start'));
        $this->post(route('attendance.break_end'));
        $this->post(route('attendance.break_start'));

        $response = $this->get('/attendance');
        $response->assertSee('休憩戻');
    }

    #[Test]
    public function test_break_times_are_visible_in_attendance_list(): void
    {
        $today = Carbon::create(2025, 9, 30, 0, 0, 0);
        Carbon::setTestNow($today);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id'     => $user->id,
            'status'      => 'on_duty',
            'work_date'   => $today->toDateString(),
            'clock_in_at' => $today->copy()->setTime(9, 0),
        ]);

        $this->post(route('attendance.break_start'));
        $this->post(route('attendance.break_end'));

        $this->post(route('attendance.break_start'));
        $this->post(route('attendance.break_end'));

        $attendance->refresh();
        $breaks = $attendance->breaks()->orderBy('id')->get();

        $breaks[0]->break_start_at = Carbon::create(2025, 9, 30, 12, 0);
        $breaks[0]->break_end_at   = Carbon::create(2025, 9, 30, 12, 10);
        $breaks[0]->save();

        $breaks[1]->break_start_at = Carbon::create(2025, 9, 30, 15, 0);
        $breaks[1]->break_end_at   = Carbon::create(2025, 9, 30, 15, 5);
        $breaks[1]->save();

        $attendance = Attendance::with('breaks')->find($attendance->id);

        $response = $this->get('/attendance/list');
        file_put_contents(storage_path('logs/response.html'), $response->getContent());
        $response->assertSee('0:15');
    }
}