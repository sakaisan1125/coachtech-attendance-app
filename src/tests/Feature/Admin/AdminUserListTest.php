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
        // 管理者を作成してログイン（メール認証済み）
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        // 一般ユーザーを複数作成（メール認証済み）
        $Users = User::factory()->count(5)->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        $response = $this->get('/admin/staff/list');

        // ステータス200
        $response->assertStatus(200);

        // 全ての一般ユーザーの氏名・メールが表示されている
        foreach ($Users as $user) {
            $response->assertSee($user->name);
            $response->assertSee($user->email);
        }
    }

    #[Test]
    public function test_admin_can_view_selected_users_attendance_list()
    {
        // 1. 管理者でログイン
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        // 対象ユーザーと他ユーザー
        $targetUser = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
        $otherUser = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        // 2. 対象ユーザーの当月勤怠を3件作成（同一月内）
        $month = now()->startOfMonth();
        $targetDates = [
            $month->copy()->addDays(0)->toDateString(),
            $month->copy()->addDays(5)->toDateString(),
            $month->copy()->addDays(10)->toDateString(),
        ];
        foreach ($targetDates as $date) {
            Attendance::factory()->create([
                'user_id' => $targetUser->id,
                'work_date' => $date,
                'status' => 'clocked_out',
            ]);
        }
        // 他ユーザーの同月勤怠（表示されない想定）
        $otherDate = $month->copy()->addDays(3)->toDateString();
        $otherAttendance = Attendance::factory()->create([
             'user_id' => $otherUser->id,
             'work_date' => $otherDate,
             'status' => 'clocked_out',
         ]);

        // 対象ユーザーの勤怠一覧ページを開く
        $response = $this->get('/admin/attendance/staff/' . $targetUser->id . '?month=' . $month->format('Y-m'));
        $response->assertStatus(200);

        // 期待挙動：対象ユーザーの勤怠日付が表示される（日本語曜日）
        foreach ($targetDates as $date) {
            $carbon = \Carbon\Carbon::parse($date)->locale('ja');
            $formatted = $carbon->isoFormat('MM/DD(ddd)');
            $response->assertSee($formatted);
        }
        // 月表示
        $response->assertSee($month->format('Y年n月'));

        $response->assertDontSee('/admin/attendance/staff/' . $otherAttendance->id);
    }

    #[Test]
    public function test_previous_month_attendance_is_visible_when_prev_button_clicked_for_staff()
    {
        // 実行時刻を固定（任意だが安定のため）
        Carbon::setTestNow(Carbon::create(2025, 9, 15, 12, 0, 0, 'Asia/Tokyo'));

        // 1. 管理者でログイン
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        // 対象ユーザー
        $staff = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        // 2. 前月の勤怠データを作成（同一月内）
        $currentMonth = now()->startOfMonth();      // 2025-09-01
        $prevMonth    = $currentMonth->copy()->subMonth(); // 2025-08-01
        $dates = [
            $prevMonth->copy()->startOfMonth()->toDateString(),        // 月初
            $prevMonth->copy()->startOfMonth()->addDays(3)->toDateString(), // 月初+3日
            $prevMonth->copy()->endOfMonth()->toDateString(),          // 月末
        ];
        foreach ($dates as $date) {
            Attendance::factory()->create([
                'user_id'   => $staff->id,
                'work_date' => $date,
                'status'    => 'clocked_out',
            ]);
        }

        // 3. 現在月ページを開き → 「前月」相当のURLへアクセス（クリック相当）
        $this->get('/admin/attendance/staff/' . $staff->id . '?month=' . $currentMonth->format('Y-m'))
             ->assertStatus(200);

        $response = $this->get('/admin/attendance/staff/' . $staff->id . '?month=' . $prevMonth->format('Y-m'));
        $response->assertStatus(200);

        // 期待挙動：前月の年月表示（ビューは「Y年n月」表記）
        $response->assertSee($prevMonth->format('Y年n月'));

        // 期待挙動：前月の勤怠日付が表示（日本語曜日）
        foreach ($dates as $date) {
            $carbon = Carbon::parse($date)->locale('ja');
            $formatted = $carbon->isoFormat('MM/DD(ddd)');
            $response->assertSee($formatted);
        }

        // 片付け
        Carbon::setTestNow(); // reset
    }

    #[Test]
    public function test_next_month_attendance_is_visible_when_next_button_clicked_for_staff()
    {
        // 実行時刻を固定（安定化）
        Carbon::setTestNow(Carbon::create(2025, 9, 15, 12, 0, 0, 'Asia/Tokyo'));

        // 1. 管理者でログイン
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        // 対象ユーザー
        $staff = User::factory()->create([
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        // 2. 翌月の勤怠データを作成（同一月内）
        $currentMonth = now()->startOfMonth();           // 2025-09-01
        $nextMonth    = $currentMonth->copy()->addMonth(); // 2025-10-01
        $dates = [
            $nextMonth->copy()->startOfMonth()->toDateString(),         // 月初
            $nextMonth->copy()->startOfMonth()->addDays(3)->toDateString(), // 月初+3日
            $nextMonth->copy()->endOfMonth()->toDateString(),           // 月末
        ];
        foreach ($dates as $date) {
            Attendance::factory()->create([
                'user_id'   => $staff->id,
                'work_date' => $date,
                'status'    => 'clocked_out',
            ]);
        }

        // 3. 現在月 → 「翌月」相当のURLへアクセス（クリック相当）
        $this->get('/admin/attendance/staff/' . $staff->id . '?month=' . $currentMonth->format('Y-m'))
             ->assertStatus(200);

        $response = $this->get('/admin/attendance/staff/' . $staff->id . '?month=' . $nextMonth->format('Y-m'));
        $response->assertStatus(200);

        // 期待挙動：翌月の年月表示（ビューは「Y年n月」表記）
        $response->assertSee($nextMonth->format('Y年n月'));

        // 期待挙動：翌月の勤怠日付が表示（日本語曜日）
        foreach ($dates as $date) {
            $carbon = Carbon::parse($date)->locale('ja');
            $formatted = $carbon->isoFormat('MM/DD(ddd)');
            $response->assertSee($formatted);
        }

        Carbon::setTestNow(); // reset
    }

    #[Test]
    public function test_admin_can_navigate_to_attendance_detail_from_list()
    {
        // 実行日時固定
        Carbon::setTestNow(Carbon::create(2025, 9, 15, 12, 0, 0, 'Asia/Tokyo'));
        $today = now()->toDateString();

        // 1. 管理者でログイン
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);

        // 一般ユーザーと当日の勤怠を作成
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

        // 2. 勤怠一覧ページを開く
        $response = $this->get('/admin/attendance/list?date=' . $today);
        $response->assertStatus(200);

        // 一覧にユーザー名と詳細リンクが出ている
        $response->assertSee($user->name);
        $response->assertSee('/admin/attendance/' . $attendance->id); // aタグのhref

        // 3. 「詳細」リンク先へアクセス（押下相当）
        $detail = $this->get('/admin/attendance/' . $attendance->id);
        $detail->assertStatus(200);

        // その日の勤怠詳細が表示（見出しの日付と時刻）
        $detail->assertSee(Carbon::parse($attendance->work_date)->format('Y年'));
        $detail->assertSee(Carbon::parse($attendance->work_date)->format('n月j日'));
        $detail->assertSee('09:00');
        $detail->assertSee('18:00');

        Carbon::setTestNow(); // reset
    }
}