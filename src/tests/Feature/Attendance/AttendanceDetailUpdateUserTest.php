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
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 2. 勤怠情報を登録（正常な時刻）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => now()->setTime(18, 0),
            'status' => 'clocked_out',
        ]);

        // 3. 出勤時間を退勤時間より後に設定して保存処理
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
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 2. 勤怠情報を登録（正常な時刻）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => now()->setTime(18, 0),
            'status' => 'clocked_out',
        ]);

        // 3. 休憩開始時間を退勤時間より後に設定して保存処理
        $response = $this->from('/attendance/detail/' . $attendance->id)
            ->post('/attendance/detail/' . $attendance->id, [
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
                'notes' => 'テスト',
                'breaks' => [
                    [
                        'start' => '19:00', // 退勤後
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
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 2. 勤怠情報を登録（正常な時刻）
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => now()->setTime(18, 0),
            'status' => 'clocked_out',
        ]);

        // 3. 休憩開始時間を退勤時間より後に設定して保存処理
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
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 2. 勤怠情報を登録
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => now()->setTime(9, 0),
            'clock_out_at' => now()->setTime(18, 0),
            'status' => 'clocked_out',
        ]);

        // 3. 備考欄を未入力で保存処理
        $response = $this->from('/attendance/detail/' . $attendance->id)
            ->post('/attendance/detail/' . $attendance->id, [
                'clock_in_at' => '09:00',
                'clock_out_at' => '18:00',
                'notes' => '', // 未入力
                'breaks' => [],
            ]);

        $response->assertSessionHasErrors(['notes']);
        $detailResponse = $this->get('/attendance/detail/' . $attendance->id);
        $detailResponse->assertSee('備考を記入してください');
    }

    #[Test]
    public function test_correction_request_is_created_and_visible_to_admin()
    {
        // 1. 一般ユーザーで勤怠情報を登録＆ログイン
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        // 2. 勤怠詳細を修正し申請
        $response = $this->post('/attendance/detail/' . $attendance->id, [
            'clock_in_at' => '10:00',
            'clock_out_at' => '18:00',
            'notes' => '修正申請テスト',
            'breaks' => [],
        ]);
        $response->assertSessionHas('success');

        // 3. 管理者ユーザーで申請一覧・承認画面を確認
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'role' => 'admin', // 管理者判定用
        ]);
        $this->actingAs($admin);

        // 申請一覧画面に表示されること
        $listResponse = $this->get('/stamp_correction_request/list');
        $listResponse->assertSee('修正申請テスト');
        $listResponse->assertSee($user->name);

        // 承認画面（例: /correction-request/{id}/approve）に表示されること
        $correctionRequest = CorrectionRequest::first();
        $approveResponse = $this->get('/stamp_correction_request/approve/' . $correctionRequest->id);
        $approveResponse->assertSee('修正申請テスト');
        $approveResponse->assertSee('10:00');
    }

    #[Test]
    public function test_pending_requests_list_shows_all_user_requests()
    {
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 2. 勤怠詳細を複数修正申請
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

        // 3. 申請一覧画面を確認
        $listResponse = $this->get('/stamp_correction_request/list');
        $listResponse->assertSee('申請1');
        $listResponse->assertSee('申請2');
        $listResponse->assertSee($user->name);
    }

    #[Test]
    public function test_approved_requests_list_shows_all_admin_approved_requests()
    {
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 2. 勤怠詳細を複数修正申請
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

        // 3. 管理者ユーザーで承認処理
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 申請を全て承認（モデル名や承認処理は実装に合わせて修正してください）
        foreach (CorrectionRequest::all() as $request) {
            $request->status = 'approved';
            $request->save();
        }

        // 4. 承認済み一覧画面を確認
        $approvedResponse = $this->get('/stamp_correction_request/list/approved');
        $approvedResponse->assertSee('申請A');
        $approvedResponse->assertSee('申請B');
        $approvedResponse->assertSee($user->name);
    }

    #[Test]
    public function test_correction_request_detail_link_navigates_to_attendance_detail()
    {
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 2. 勤怠詳細を修正申請
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

        // 3. 申請一覧画面を開く
        $listResponse = $this->get('/stamp_correction_request/list');
        $correctionRequest = CorrectionRequest::first();

        // 4. 「詳細」ボタンのリンク先にアクセス（例: /attendance/detail/{attendance_id}）
        $detailUrl = '/attendance/detail/' . $attendance->id;
        $detailResponse = $this->get($detailUrl);

        // 勤怠詳細画面に遷移し、申請内容が表示されていること
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('申請詳細テスト');
        $detailResponse->assertSee('10:00');
    }
}