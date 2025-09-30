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
        // 1. 管理者ユーザーにログイン
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 2. 一般ユーザーを複数作成し、勤怠情報を登録
        $user1 = User::factory()->create(['name' => 'ユーザーA', 'email_verified_at' => now()]);
        $user2 = User::factory()->create(['name' => 'ユーザーB', 'email_verified_at' => now()]);

        $attendance1 = Attendance::factory()->create([
            'user_id' => $user1->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => '09:00',
            'clock_out_at' => '18:00',
            'status' => 'clocked_out',
        ]);
        $attendance2 = Attendance::factory()->create([
            'user_id' => $user2->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => '10:00',
            'clock_out_at' => '19:00',
            'status' => 'clocked_out',
        ]);

        // 3. 勤怠一覧画面を開く
        $response = $this->get('/admin/attendance/list?date=' . now()->toDateString());

        // 4. その日の全ユーザーの勤怠情報が正確な値になっていることを確認
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
        // 1. 管理者ユーザーにログイン
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 2. 勤怠一覧画面を開く
        $response = $this->get('/admin/attendance/list?date=' . now()->toDateString());

        // 3. 画面に今日の日付が表示されていることを確認
        $carbon = now();
        $formattedDate = $carbon->format('Y年n月j日');
        $response->assertSee($formattedDate);
    }

    #[Test]
    public function test_admin_attendance_list_shows_previous_day_data()
    {
        // 1. 管理者ユーザーにログイン
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 2. 前日の日付を取得
        $prevDay = now()->subDay();
        $prevDate = $prevDay->toDateString(); // 例: '2025-09-25'
        $formattedDate = $prevDay->format('Y年n月j日'); // 例: '2025年9月25日'

        // 3. 前日分の勤怠データを登録
        $user = User::factory()->create(['name' => 'ユーザーC', 'email_verified_at' => now()]);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $prevDate,
            'clock_in_at' => '08:00',
            'clock_out_at' => '17:00',
            'status' => 'clocked_out',
        ]);

        // 4. 勤怠一覧画面を前日パラメータで開く
        $response = $this->get('/admin/attendance/list?date=' . $prevDate);

        // 5. 前日の日付と勤怠情報が表示されていることを確認
        $response->assertSee($formattedDate); // 日付
        $response->assertSee($user->name);    // ユーザー名
        $response->assertSee('08:00');        // 出勤
        $response->assertSee('17:00');        // 退勤
    }

    #[Test]
    public function test_admin_attendance_list_shows_next_day_data()
    {
        // 1. 管理者ユーザーにログイン
        $admin = User::factory()->create([
            'name' => '管理者',
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 2. 翌日の日付を取得
        $addDay = now()->addDay();
        $addDate = $addDay->toDateString(); // 例: '2025-09-25'
        $formattedDate = $addDay->format('Y年n月j日'); // 例: '2025年9月25日'

        // 3. 翌日分の勤怠データを登録
        $user = User::factory()->create(['name' => 'ユーザーC', 'email_verified_at' => now()]);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $addDate,
            'clock_in_at' => '08:00',
            'clock_out_at' => '17:00',
            'status' => 'clocked_out',
        ]);

        // 4. 勤怠一覧画面を翌日パラメータで開く
        $response = $this->get('/admin/attendance/list?date=' . $addDate);

        // 5. 翌日の日付と勤怠情報が表示されていることを確認
        $response->assertSee($formattedDate); // 日付
        $response->assertSee($user->name);    // ユーザー名
        $response->assertSee('08:00');        // 出勤
        $response->assertSee('17:00');        // 退勤
    }
}