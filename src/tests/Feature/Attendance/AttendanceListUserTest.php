<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use PHPUnit\Framework\Attributes\Test;

class AttendanceListUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_user_can_see_all_own_attendance_records_in_list()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $dates = [
            now()->subDays(2)->toDateString(),
            now()->subDays(1)->toDateString(),
            now()->toDateString(),
        ];
        foreach ($dates as $date) {
            Attendance::factory()->create([
                'user_id' => $user->id,
                'work_date' => $date,
                'status' => 'clocked_out',
            ]);
        }

        $response = $this->get('/attendance/list');

        foreach ($dates as $date) {
            $formatted = \Carbon\Carbon::parse($date)->format('m/d');
            $response->assertSee($formatted);
        }
    }

    #[Test]
    public function test_current_month_is_visible_in_attendance_list()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get('/attendance/list');

        $currentMonth = now()->format('Y/m');
        $response->assertSee($currentMonth);
    }

    #[Test]
    public function test_previous_month_attendance_is_visible_when_prev_button_clicked()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $prevMonth = now()->subMonth();
        $dates = [
            $prevMonth->copy()->startOfMonth()->toDateString(),
            $prevMonth->copy()->startOfMonth()->addDays(3)->toDateString(),
            $prevMonth->copy()->endOfMonth()->toDateString(),
        ];
        foreach ($dates as $date) {
            Attendance::factory()->create([
                'user_id' => $user->id,
                'work_date' => $date,
                'status' => 'clocked_out',
            ]);
        }

        $response = $this->get('/attendance/list?month=' . $prevMonth->format('Y-m'));

        foreach ($dates as $date) {
            $carbon = \Carbon\Carbon::parse($date)->locale('ja');
            $formatted = $carbon->isoFormat('MM/DD(ddd)');
            $response->assertSee($formatted);
        }
        $response->assertSee($prevMonth->format('Y/m'));
    }

    #[Test]
    public function test_next_month_attendance_is_visible_when_next_button_clicked()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $nextMonth = now()->addMonth();
        $dates = [
            $nextMonth->copy()->startOfMonth()->toDateString(),
            $nextMonth->copy()->startOfMonth()->addDays(3)->toDateString(),
            $nextMonth->copy()->endOfMonth()->toDateString(),
        ];
        foreach ($dates as $date) {
            Attendance::factory()->create([
                'user_id' => $user->id,
                'work_date' => $date,
                'status' => 'clocked_out',
            ]);
        }

        $response = $this->get('/attendance/list?month=' . $nextMonth->format('Y-m'));

        foreach ($dates as $date) {
            $carbon = \Carbon\Carbon::parse($date);
            $week = ['日','月','火','水','木','金','土'][$carbon->dayOfWeek];
            $formatted = $carbon->format('m/d') . '(' . $week . ')';
            $response->assertSee($formatted);
        }
        $response->assertSee($nextMonth->format('Y/m'));
    }

    #[Test]
    public function test_attendance_detail_link_navigates_to_detail_page()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'status' => 'clocked_out',
        ]);

        $response = $this->get('/attendance/list');

        $detailUrl = '/attendance/detail/' . $attendance->id;
        $response->assertSee($detailUrl);

        $detailResponse = $this->get($detailUrl);
        $detailResponse->assertStatus(200);

        $carbon = \Carbon\Carbon::parse($attendance->work_date);
        $detailResponse->assertSee($carbon->format('Y年'));
        $detailResponse->assertSee($carbon->format('n月j日'));
    }
}