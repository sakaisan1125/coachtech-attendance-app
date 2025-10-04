<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use PHPUnit\Framework\Attributes\Test;

class AttendanceListAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_admin_can_see_all_users_attendance_for_today()
    {
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $user1 = User::factory()->create(['name' => 'ユーザーA', 'email_verified_at' => now()]);
        $user2 = User::factory()->create(['name' => 'ユーザーB', 'email_verified_at' => now()]);

        Attendance::factory()->create([
            'user_id' => $user1->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);
        Attendance::factory()->create([
            'user_id' => $user2->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => '10:00',
            'clock_out_at' => '19:00',
            'status' => 'clocked_out',
        ]);

        $response = $this->get('/admin/attendance/list?date=' . now()->toDateString());
        $response->assertSee($user1->name);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee($user2->name);
        $response->assertSee('10:00');
        $response->assertSee('19:00');
    }

    #[Test]
    public function test_admin_attendance_list_shows_today_date()
    {
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $response = $this->get('/admin/attendance/list?date=' . now()->toDateString());
        $formattedDate = now()->format('Y年n月j日');
        $response->assertSee($formattedDate);
    }

    #[Test]
    public function test_admin_attendance_list_shows_previous_day_data()
    {
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $prevDay = now()->subDay();
        $prevDate = $prevDay->toDateString();
        $formattedDate = $prevDay->format('Y年n月j日');

        $user = User::factory()->create(['name' => 'ユーザーC', 'email_verified_at' => now()]);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $prevDate,
            'clock_in_at' => '08:00',
            'clock_out_at' => '17:00',
            'status' => 'clocked_out',
        ]);

        $response = $this->get('/admin/attendance/list?date=' . $prevDate);
        $response->assertSee($formattedDate);
        $response->assertSee($user->name);
        $response->assertSee('08:00');
        $response->assertSee('17:00');
    }

    #[Test]
    public function test_admin_attendance_list_shows_next_day_data()
    {
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $addDay = now()->addDay();
        $addDate = $addDay->toDateString();
        $formattedDate = $addDay->format('Y年n月j日');

        $user = User::factory()->create(['name' => 'ユーザーC', 'email_verified_at' => now()]);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $addDate,
            'clock_in_at' => '08:00',
            'clock_out_at' => '17:00',
            'status' => 'clocked_out',
        ]);

        $response = $this->get('/admin/attendance/list?date=' . $addDate);
        $response->assertSee($formattedDate);
        $response->assertSee($user->name);
        $response->assertSee('08:00');
        $response->assertSee('17:00');
    }
}