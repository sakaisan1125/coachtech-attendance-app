<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use PHPUnit\Framework\Attributes\Test;

class AttendanceDetailAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_admin_attendance_detail_shows_selected_data()
    {
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $user = User::factory()->create(['name' => '一般ユーザー', 'email_verified_at' => now()]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2025-09-26',
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        $response = $this->get('/admin/attendance/' . $attendance->id);
        $response->assertSee($user->name);
        $response->assertSee('2025年');
        $response->assertSee('9月26日');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    #[Test]
    public function test_admin_attendance_update_with_invalid_times_shows_validation_error()
    {
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $user = User::factory()->create(['name' => '一般ユーザー', 'email_verified_at' => now()]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2025-09-26',
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        $response = $this->from('/admin/attendance/' . $attendance->id)
            ->post('/admin/attendance/' . $attendance->id, [
                'clock_in' => '19:00',
                'clock_out' => '18:00',
                'notes' => 'テスト',
            ]);

        $response->assertSessionHasErrors(['clock_out']);
        $detailResponse = $this->get('/admin/attendance/' . $attendance->id);
        $detailResponse->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    #[Test]
    public function test_admin_attendance_update_with_break_start_after_clock_out_shows_validation_error()
    {
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $user = User::factory()->create(['name' => '一般ユーザー', 'email_verified_at' => now()]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2025-09-26',
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        $response = $this->from('/admin/attendance/' . $attendance->id)
            ->post('/admin/attendance/' . $attendance->id, [
                'clock_in' => '09:00',
                'clock_out' => '18:00',
                'notes' => 'テスト',
                'breaks' => [
                    [
                        'start' => '19:00',
                        'end' => '20:00',
                    ]
                ],
            ]);

        $response->assertSessionHasErrors(['breaks.0.end']);
        $detailResponse = $this->get('/admin/attendance/' . $attendance->id);
        $detailResponse->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    #[Test]
    public function test_admin_attendance_update_with_break_end_after_clock_out_shows_validation_error()
    {
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $user = User::factory()->create(['name' => '一般ユーザー', 'email_verified_at' => now()]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2025-09-26',
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        $response = $this->from('/admin/attendance/' . $attendance->id)
            ->post('/admin/attendance/' . $attendance->id, [
                'clock_in' => '09:00',
                'clock_out' => '18:00',
                'notes' => 'テスト',
                'breaks' => [
                    [
                        'start' => '17:00',
                        'end' => '19:00',
                    ]
                ],
            ]);

        $response->assertSessionHasErrors(['breaks.0.end']);
        $detailResponse = $this->get('/admin/attendance/' . $attendance->id);
        $detailResponse->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    #[Test]
    public function test_admin_attendance_update_with_empty_notes_shows_validation_error()
    {
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $user = User::factory()->create(['name' => '一般ユーザー', 'email_verified_at' => now()]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2025-09-26',
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        $response = $this->from('/admin/attendance/' . $attendance->id)
            ->post('/admin/attendance/' . $attendance->id, [
                'clock_in' => '09:00',
                'clock_out' => '19:00',
                'notes' => '',
            ]);

        $response->assertSessionHasErrors(['notes']);
        $detailResponse = $this->get('/admin/attendance/' . $attendance->id);
        $detailResponse->assertSee('備考を入力してください');
    }
}