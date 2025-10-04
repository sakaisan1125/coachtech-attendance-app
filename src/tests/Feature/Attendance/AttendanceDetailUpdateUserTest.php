<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use PHPUnit\Framework\Attributes\Test;
use App\Models\CorrectionRequest;

class AttendanceDetailUpdateUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_clock_in_after_clock_out_shows_validation_error()
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => now()->setTime(18, 0),
            'status' => 'clocked_out',
        ]);

        $response = $this->from('/attendance/detail/' . $attendance->id)
            ->post('/attendance/detail/' . $attendance->id, [
                'clock_in_at' => '19:00',
                'clock_out_at' => '18:00',
                'notes' => 'テスト',
                'breaks' => [],
            ]);

        $response->assertSessionHasErrors(['clock_in_at']);
        $detailResponse = $this->get('/attendance/detail/' . $attendance->id);
        $detailResponse->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    #[Test]
    public function test_break_start_after_clock_out_shows_validation_error()
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => now()->setTime(18, 0),
            'status' => 'clocked_out',
        ]);

        $response = $this->from('/attendance/detail/' . $attendance->id)
            ->post('/attendance/detail/' . $attendance->id, [
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
                'notes' => 'テスト',
                'breaks' => [
                    [
                        'start' => '19:00',
                        'end' => '20:00',
                    ]
                ],
            ]);

        $response->assertSessionHasErrors(['breaks.0.start']);
        $detailResponse = $this->get('/attendance/detail/' . $attendance->id);
        $detailResponse->assertSee('休憩時間が不適切な値です');
    }

    #[Test]
    public function test_break_end_after_clock_out_shows_validation_error()
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => now()->setTime(18, 0),
            'status' => 'clocked_out',
        ]);

        $response = $this->from('/attendance/detail/' . $attendance->id)
            ->post('/attendance/detail/' . $attendance->id, [
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
                'notes' => 'テスト',
                'breaks' => [
                    [
                        'start' => '17:00',
                        'end' => '19:00',
                    ]
                ],
            ]);

        $response->assertSessionHasErrors(['breaks.0.end']);
        $detailResponse = $this->get('/attendance/detail/' . $attendance->id);
        $detailResponse->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    #[Test]
    public function test_notes_required_validation_error()
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => now()->setTime(18, 0),
            'status' => 'clocked_out',
        ]);

        $response = $this->from('/attendance/detail/' . $attendance->id)
            ->post('/attendance/detail/' . $attendance->id, [
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
                'notes' => '',
                'breaks' => [],
            ]);

        $response->assertSessionHasErrors(['notes']);
        $detailResponse = $this->get('/attendance/detail/' . $attendance->id);
        $detailResponse->assertSee('備考を記入してください');
    }

    #[Test]
    public function test_correction_request_is_created_and_visible_to_admin()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        $response = $this->post('/attendance/detail/' . $attendance->id, [
            'clock_in_at' => '10:00',
            'clock_out_at' => '18:00',
            'notes' => '修正申請テスト',
            'breaks' => [],
        ]);
        $response->assertSessionHas('success');

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $listResponse = $this->get('/stamp_correction_request/list');
        $listResponse->assertSee('修正申請テスト');
        $listResponse->assertSee($user->name);

        $correctionRequest = CorrectionRequest::first();
        $approveResponse = $this->get('/stamp_correction_request/approve/' . $correctionRequest->id);
        $approveResponse->assertSee('修正申請テスト');
        $approveResponse->assertSee('10:00');
    }

    #[Test]
    public function test_pending_requests_list_shows_all_user_requests()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance1 = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);
        $attendance2 = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->addDay()->toDateString(),
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        $this->post('/attendance/detail/' . $attendance1->id, [
            'clock_in_at' => '10:00',
            'clock_out_at' => '18:00',
            'notes' => '申請1',
            'breaks' => [],
        ]);
        $this->post('/attendance/detail/' . $attendance2->id, [
            'clock_in_at' => '11:00',
            'clock_out_at' => '18:00',
            'notes' => '申請2',
            'breaks' => [],
        ]);

        $listResponse = $this->get('/stamp_correction_request/list');
        $listResponse->assertSee('申請1');
        $listResponse->assertSee('申請2');
        $listResponse->assertSee($user->name);
    }

    #[Test]
    public function test_approved_requests_list_shows_all_admin_approved_requests()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance1 = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);
        $attendance2 = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->addDay()->toDateString(),
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        $this->post('/attendance/detail/' . $attendance1->id, [
            'clock_in_at' => '10:00',
            'clock_out_at' => '18:00',
            'notes' => '申請A',
            'breaks' => [],
        ]);
        $this->post('/attendance/detail/' . $attendance2->id, [
            'clock_in_at' => '11:00',
            'clock_out_at' => '18:00',
            'notes' => '申請B',
            'breaks' => [],
        ]);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        foreach (CorrectionRequest::all() as $request) {
            $request->status = 'approved';
            $request->save();
        }

        $approvedResponse = $this->get('/stamp_correction_request/list/approved');
        $approvedResponse->assertSee('申請A');
        $approvedResponse->assertSee('申請B');
        $approvedResponse->assertSee($user->name);
    }

    #[Test]
    public function test_correction_request_detail_link_navigates_to_attendance_detail()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        $this->post('/attendance/detail/' . $attendance->id, [
            'clock_in_at' => '10:00',
            'clock_out_at' => '18:00',
            'notes' => '申請詳細テスト',
            'breaks' => [],
        ]);

        $this->get('/stamp_correction_request/list');
        $detailUrl = '/attendance/detail/' . $attendance->id;
        $detailResponse = $this->get($detailUrl);

        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('申請詳細テスト');
        $detailResponse->assertSee('10:00');
    }
}