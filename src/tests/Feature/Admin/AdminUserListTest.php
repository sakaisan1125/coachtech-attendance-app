<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

class AdminUserListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_admin_can_see_all_general_users_name_and_email()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        $users = User::factory()->count(5)->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $response = $this->get('/admin/staff/list');
        $response->assertStatus(200);

        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee($user->email);
        }
    }

    #[Test]
    public function test_admin_can_view_selected_users_attendance_list()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        $targetUser = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        $otherUser = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $month = now()->startOfMonth();
        $targetDates = [
            $month->copy()->addDays(0)->toDateString(),
            $month->copy()->addDays(5)->toDateString(),
            $month->copy()->addDays(10)->toDateString(),
        ];
        foreach ($targetDates as $date) {
            Attendance::factory()->create([
                'user_id'   => $targetUser->id,
                'work_date' => $date,
                'status'    => 'clocked_out',
            ]);
        }

        $otherDate = $month->copy()->addDays(3)->toDateString();
        $otherAttendance = Attendance::factory()->create([
            'user_id'   => $otherUser->id,
            'work_date' => $otherDate,
            'status'    => 'clocked_out',
        ]);

        $response = $this->get('/admin/attendance/staff/' . $targetUser->id . '?month=' . $month->format('Y-m'));
        $response->assertStatus(200);

        foreach ($targetDates as $date) {
            $carbon = Carbon::parse($date)->locale('ja');
            $formatted = $carbon->isoFormat('MM/DD(ddd)');
            $response->assertSee($formatted);
        }

        $response->assertSee($month->format('Y/m'));
        $response->assertDontSee('/admin/attendance/staff/' . $otherAttendance->id);
    }

    #[Test]
    public function test_previous_month_attendance_is_visible_when_prev_button_clicked_for_staff()
    {
        Carbon::setTestNow(Carbon::create(2025, 9, 15, 12, 0, 0, 'Asia/Tokyo'));

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        $staff = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $currentMonth = now()->startOfMonth();
        $prevMonth = $currentMonth->copy()->subMonth();
        $dates = [
            $prevMonth->copy()->startOfMonth()->toDateString(),
            $prevMonth->copy()->startOfMonth()->addDays(3)->toDateString(),
            $prevMonth->copy()->endOfMonth()->toDateString(),
        ];
        foreach ($dates as $date) {
            Attendance::factory()->create([
                'user_id'   => $staff->id,
                'work_date' => $date,
                'status'    => 'clocked_out',
            ]);
        }

        $this->get('/admin/attendance/staff/' . $staff->id . '?month=' . $currentMonth->format('Y-m'))
             ->assertStatus(200);

        $response = $this->get('/admin/attendance/staff/' . $staff->id . '?month=' . $prevMonth->format('Y-m'));
        $response->assertStatus(200);
        $response->assertSee($prevMonth->format('Y/m'));

        foreach ($dates as $date) {
            $carbon = Carbon::parse($date)->locale('ja');
            $formatted = $carbon->isoFormat('MM/DD(ddd)');
            $response->assertSee($formatted);
        }

        Carbon::setTestNow();
    }

    #[Test]
    public function test_next_month_attendance_is_visible_when_next_button_clicked_for_staff()
    {
        Carbon::setTestNow(Carbon::create(2025, 9, 15, 12, 0, 0, 'Asia/Tokyo'));

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        $staff = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        $currentMonth = now()->startOfMonth();
        $nextMonth = $currentMonth->copy()->addMonth();
        $dates = [
            $nextMonth->copy()->startOfMonth()->toDateString(),
            $nextMonth->copy()->startOfMonth()->addDays(3)->toDateString(),
            $nextMonth->copy()->endOfMonth()->toDateString(),
        ];
        foreach ($dates as $date) {
            Attendance::factory()->create([
                'user_id'   => $staff->id,
                'work_date' => $date,
                'status'    => 'clocked_out',
            ]);
        }

        $this->get('/admin/attendance/staff/' . $staff->id . '?month=' . $currentMonth->format('Y-m'))
             ->assertStatus(200);

        $response = $this->get('/admin/attendance/staff/' . $staff->id . '?month=' . $nextMonth->format('Y-m'));
        $response->assertStatus(200);
        $response->assertSee($nextMonth->format('Y/m'));

        foreach ($dates as $date) {
            $carbon = Carbon::parse($date)->locale('ja');
            $formatted = $carbon->isoFormat('MM/DD(ddd)');
            $response->assertSee($formatted);
        }

        Carbon::setTestNow();
    }

    #[Test]
    public function test_admin_can_navigate_to_attendance_detail_from_list()
    {
        Carbon::setTestNow(Carbon::create(2025, 9, 15, 12, 0, 0, 'Asia/Tokyo'));
        $today = now()->toDateString();

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        $user = User::factory()->create([
            'role' => 'user',
            'name' => 'テスト太郎',
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::factory()->create([
            'user_id'      => $user->id,
            'work_date'    => $today,
            'clock_in_at'  => Carbon::parse("$today 09:00"),
            'clock_out_at' => Carbon::parse("$today 18:00"),
            'status'       => 'clocked_out',
        ]);

        $response = $this->get('/admin/attendance/list?date=' . $today);
        $response->assertStatus(200);
        $response->assertSee($user->name);
        $response->assertSee('/admin/attendance/' . $attendance->id);

        $detail = $this->get('/admin/attendance/' . $attendance->id);
        $detail->assertStatus(200);
        $detail->assertSee(Carbon::parse($attendance->work_date)->format('Y年'));
        $detail->assertSee(Carbon::parse($attendance->work_date)->format('n月j日'));
        $detail->assertSee('09:00');
        $detail->assertSee('18:00');

        Carbon::setTestNow();
    }
}