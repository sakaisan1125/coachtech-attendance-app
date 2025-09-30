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
        // 1. 管理者ユーザーを作成しログイン
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 2. 勤怠情報を登録
        $user = User::factory()->create(['name' => '一般ユーザー', 'email_verified_at' => now()]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2025-09-26',
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        // 3. 勤怠詳細画面にアクセス
        $response = $this->get('/admin/attendance/' . $attendance->id);

        // 4. 画面に登録した勤怠情報が表示されていることを確認
        $response->assertSee($user->name); // ユーザー名
        $response->assertSee('2025年'); // 日付（画面表示形式に合わせる）
        $response->assertSee('9月26日');   // 日付
        $response->assertSee('09:00'); // 出勤
        $response->assertSee('18:00'); // 退勤
    }

    #[Test]
    public function test_admin_attendance_update_with_invalid_times_shows_validation_error()
    {
        // 1. 管理者ユーザーを作成しログイン
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 2. 勤怠情報を登録
        $user = User::factory()->create(['name' => '一般ユーザー', 'email_verified_at' => now()]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2025-09-26',
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        // 3. 勤怠情報を「出勤時間 > 退勤時間」に修正して保存
        $response = $this->from('/admin/attendance/' . $attendance->id)
            ->post('/admin/attendance/' . $attendance->id, [
                'clock_in' => '19:00',
                'clock_out' => '18:00',
                'notes' => 'テスト',
                // 他の必要な項目があれば追加
            ]);

        // 4. バリデーションメッセージが表示されていることを確認
        $response->assertSessionHasErrors(['clock_out']);
        $detailResponse = $this->get('/admin/attendance/' . $attendance->id);
        $detailResponse->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    #[Test]
    public function test_admin_attendance_update_with_break_start_after_clock_out_shows_validation_error()
    {
        // 1. 管理者ユーザーを作成しログイン
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 2. 勤怠情報を登録
        $user = User::factory()->create(['name' => '一般ユーザー', 'email_verified_at' => now()]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2025-09-26',
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        // 3. 勤怠情報を「休憩開始 > 退勤時間」に修正して保存
        $response = $this->from('/admin/attendance/' . $attendance->id)
            ->post('/admin/attendance/' . $attendance->id, [
                'clock_in' => '09:00',
                'clock_out' => '18:00',
                'notes' => 'テスト',
                'breaks' => [
                    [
                        'start' => '19:00', // 退勤後
                        'end' => '20:00',
                    ]
                ],
            ]);

        // 4. バリデーションメッセージが表示されていることを確認
        $response->assertSessionHasErrors(['breaks.0.end']);
        $detailResponse = $this->get('/admin/attendance/' . $attendance->id);
        $detailResponse->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    #[Test]
    public function test_admin_attendance_update_with_break_end_after_clock_out_shows_validation_error()
    {
        // 1. 管理者ユーザーを作成しログイン
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 2. 勤怠情報を登録
        $user = User::factory()->create(['name' => '一般ユーザー', 'email_verified_at' => now()]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2025-09-26',
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        // 3. 勤怠情報を「休憩終了 > 退勤時間」に修正して保存
        $response = $this->from('/admin/attendance/' . $attendance->id)
            ->post('/admin/attendance/' . $attendance->id, [
                'clock_in' => '09:00',
                'clock_out' => '18:00',
                'notes' => 'テスト',
                'breaks' => [
                    [
                        'start' => '17:00',
                        'end' => '19:00', // 退勤後
                    ]
                ],
            ]);

        // 4. バリデーションメッセージが表示されていることを確認
        $response->assertSessionHasErrors(['breaks.0.end']);
        $detailResponse = $this->get('/admin/attendance/' . $attendance->id);
        $detailResponse->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    #[Test]
    public function test_admin_attendance_update_with_empty_notes_shows_validation_error()
    {
        // 1. 管理者ユーザーを作成しログイン
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 2. 勤怠情報を登録
        $user = User::factory()->create(['name' => '一般ユーザー', 'email_verified_at' => now()]);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => '2025-09-26',
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);

        // 3. 勤怠情報を「休憩終了 > 退勤時間」に修正して保存
        $response = $this->from('/admin/attendance/' . $attendance->id)
            ->post('/admin/attendance/' . $attendance->id, [
                'clock_in' => '09:00',
                'clock_out' => '19:00',
                'notes' => '',
            ]);

        // 4. バリデーションメッセージが表示されていることを確認
        $response->assertSessionHasErrors(['notes']);
        $detailResponse = $this->get('/admin/attendance/' . $attendance->id);
        $detailResponse->assertSee('備考を入力してください');
    }
}