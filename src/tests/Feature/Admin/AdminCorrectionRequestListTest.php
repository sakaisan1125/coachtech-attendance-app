<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AdminCorrectionRequestListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_pending_correction_requests_are_listed_for_admin()
    {
        Carbon::setTestNow('2025-09-15 12:00:00');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        $userPending1 = User::factory()->create(['role' => 'user', 'name' => '申請者A', 'email_verified_at' => now()]);
        $userPending2 = User::factory()->create(['role' => 'user', 'name' => '申請者B', 'email_verified_at' => now()]);
        $userApproved = User::factory()->create(['role' => 'user', 'name' => '申請者C', 'email_verified_at' => now()]);

        $attA = Attendance::factory()->create(['user_id' => $userPending1->id, 'work_date' => '2025-09-10']);
        $attB = Attendance::factory()->create(['user_id' => $userPending2->id, 'work_date' => '2025-09-11']);
        $attC = Attendance::factory()->create(['user_id' => $userApproved->id, 'work_date' => '2025-09-12']);

        DB::table('correction_requests')->insert([
            'attendance_id'   => $attA->id,
            'requested_by'    => $userPending1->id,
            'requested_notes' => 'Aの申請',
            'status'          => 'pending',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        DB::table('correction_requests')->insert([
            'attendance_id'   => $attB->id,
            'requested_by'    => $userPending2->id,
            'requested_notes' => 'Bの申請',
            'status'          => 'pending',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        DB::table('correction_requests')->insert([
            'attendance_id'   => $attC->id,
            'requested_by'    => $userApproved->id,
            'requested_notes' => 'Cの申請',
            'status'          => 'approved',
            'approved_by'     => $admin->id,
            'approved_at'     => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSee('申請者A');
        $response->assertSee('申請者B');
        $response->assertDontSee('申請者C');

        Carbon::setTestNow();
    }

    #[Test]
    public function test_approved_correction_requests_are_listed_for_admin()
    {
        Carbon::setTestNow('2025-09-15 12:00:00');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        $u1 = User::factory()->create(['role' => 'user', 'name' => '承認済み太郎', 'email_verified_at' => now()]);
        $att = Attendance::factory()->create(['user_id' => $u1->id, 'work_date' => '2025-09-10']);

        DB::table('correction_requests')->insert([
            'attendance_id'   => $att->id,
            'requested_by'    => $u1->id,
            'requested_notes' => '承認済みの申請',
            'status'          => 'approved',
            'approved_by'     => $admin->id,
            'approved_at'     => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->get('/stamp_correction_request/list/approved');
        $response->assertStatus(200);
        $response->assertSee('承認済み太郎');

        Carbon::setTestNow();
    }

    #[Test]
    public function test_all_approved_correction_requests_are_listed_for_admin()
    {
        Carbon::setTestNow('2025-09-15 12:00:00');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        $userApproved1 = User::factory()->create(['role' => 'user', 'name' => '承認済みA', 'email_verified_at' => now()]);
        $userApproved2 = User::factory()->create(['role' => 'user', 'name' => '承認済みB', 'email_verified_at' => now()]);
        $userPending   = User::factory()->create(['role' => 'user', 'name' => '承認待ちX', 'email_verified_at' => now()]);

        $attA = Attendance::factory()->create(['user_id' => $userApproved1->id, 'work_date' => '2025-09-10']);
        $attB = Attendance::factory()->create(['user_id' => $userApproved2->id, 'work_date' => '2025-09-11']);
        $attX = Attendance::factory()->create(['user_id' => $userPending->id,   'work_date' => '2025-09-12']);

        DB::table('correction_requests')->insert([
            'attendance_id'   => $attA->id,
            'requested_by'    => $userApproved1->id,
            'requested_notes' => 'Aの修正',
            'status'          => 'approved',
            'approved_by'     => $admin->id,
            'approved_at'     => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        DB::table('correction_requests')->insert([
            'attendance_id'   => $attB->id,
            'requested_by'    => $userApproved2->id,
            'requested_notes' => 'Bの修正',
            'status'          => 'approved',
            'approved_by'     => $admin->id,
            'approved_at'     => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        DB::table('correction_requests')->insert([
            'attendance_id'   => $attX->id,
            'requested_by'    => $userPending->id,
            'requested_notes' => 'Xの修正',
            'status'          => 'pending',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $response = $this->get('/stamp_correction_request/list/approved');
        $response->assertStatus(200);
        $response->assertSee('承認済みA');
        $response->assertSee('承認済みB');
        $response->assertDontSee('承認待ちX');

        Carbon::setTestNow();
    }

    #[Test]
    public function test_admin_can_see_correction_request_detail_contents()
    {
        Carbon::setTestNow('2025-09-10 09:30:00');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        $user = User::factory()->create([
            'role' => 'user',
            'name' => '申請者テスト',
            'email_verified_at' => now(),
        ]);
        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => '2025-09-10',
        ]);

        $requestId = DB::table('correction_requests')->insertGetId([
            'attendance_id'          => $attendance->id,
            'requested_by'           => $user->id,
            'requested_clock_in_at'  => '09:00',
            'requested_clock_out_at' => '18:00',
            'requested_notes'        => 'テストの申請メモ',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $requestId = DB::table('correction_requests')->insertGetId([
            'attendance_id'          => $attendance->id,
            'requested_by'           => $user->id,
            'requested_clock_in_at'  => '09:00',
            'requested_clock_out_at' => '18:00',
            'requested_notes'        => 'テストの申請メモ',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $response = $this->get('/stamp_correction_request/approve/' . $requestId);
        $response->assertStatus(200);
        $response->assertSee('申請者テスト');
        $response->assertSee('2025年');
        $response->assertSee('9月10日');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('テストの申請メモ');

        Carbon::setTestNow();
    }

    #[Test]
    public function test_admin_can_approve_correction_request_and_update_attendance()
    {
        Carbon::setTestNow('2025-09-10 12:00:00');

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        $user = User::factory()->create([
            'role' => 'user',
            'name' => '申請者テスト',
            'email_verified_at' => now(),
        ]);
        $attendance = Attendance::factory()->create([
            'user_id'      => $user->id,
            'work_date'    => '2025-09-10',
            'clock_in_at'  => '2025-09-10 08:00:00',
            'clock_out_at' => '2025-09-10 17:00:00',
            'notes'        => '元の備考',
        ]);

        $requestId = DB::table('correction_requests')->insertGetId([
            'attendance_id'          => $attendance->id,
            'requested_by'           => $user->id,
            'requested_clock_in_at'  => '2025-09-10 09:00:00',
            'requested_clock_out_at' => '2025-09-10 18:00:00',
            'requested_notes'        => '修正申請の備考',
            'status'                 => 'pending',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $response = $this->post('/stamp_correction_request/approve/' . $requestId);
        $response->assertStatus(302);
        $response->assertSessionHas('success', '勤怠修正申請を承認しました。');

        $attendance->refresh();
        $this->assertSame('2025-09-10 09:00:00', (string)$attendance->clock_in_at);
        $this->assertSame('2025-09-10 18:00:00', (string)$attendance->clock_out_at);
        $this->assertSame('修正申請の備考', $attendance->notes);

        $this->assertDatabaseHas('correction_requests', [
            'id'          => $requestId,
            'status'      => 'approved',
            'approved_by' => $admin->id,
        ]);

        $approvedAt = DB::table('correction_requests')->where('id', $requestId)->value('approved_at');
        $this->assertNotNull($approvedAt);

        Carbon::setTestNow();
    }
}