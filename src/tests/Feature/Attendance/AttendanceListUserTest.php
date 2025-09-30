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

    /** @test */
    #[Test]
    public function test_user_can_see_all_own_attendance_records_in_list()
    {
        // 1. 勤怠情報が登録されたユーザーを作成
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 2. 3日分の勤怠情報を登録
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

        // 3. 勤怠一覧ページを開く
        $response = $this->get('/attendance/list');

        // 4. 自分の勤怠情報が全て表示されていることを確認
        foreach ($dates as $date) {
            $formatted = \Carbon\Carbon::parse($date)->format('m/d');
            $response->assertSee($formatted);
        }
    }

    #[Test]
    public function test_current_month_is_visible_in_attendance_list()
    {
        // 1. ユーザーにログイン
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 2. 勤怠一覧ページを開く
        $response = $this->get('/attendance/list');

        // 期待挙動：現在の月（例: 2025/09）が表示されている
        $currentMonth = now()->format('Y/m');
        $response->assertSee($currentMonth);
    }

    #[Test]
    public function test_previous_month_attendance_is_visible_when_prev_button_clicked()
    {
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 2. 前月の日付で勤怠情報を登録
        $prevMonth = now()->subMonth();
        $dates = [
            $prevMonth->copy()->startOfMonth()->toDateString(),
            $prevMonth->copy()->startOfMonth()->addDays(3)->toDateString(), // ← 月初から+3日
            $prevMonth->copy()->endOfMonth()->toDateString(),
        ];
        foreach ($dates as $date) {
            Attendance::factory()->create([
                'user_id' => $user->id,
                'work_date' => $date,
                'status' => 'clocked_out',
            ]);
        }

        // 3. 勤怠一覧ページを開き、「前月」ボタンを押す（GETパラメータで前月指定）
        $response = $this->get('/attendance/list?month=' . $prevMonth->format('Y-m'));

        // 期待挙動：前月の情報が表示されている
        foreach ($dates as $date) {
            $carbon = \Carbon\Carbon::parse($date)->locale('ja');
            $formatted = $carbon->isoFormat('MM/DD(ddd)');
            $response->assertSee($formatted);
        }
        // 前月の年月も表示されていること
        $response->assertSee($prevMonth->format('Y/m'));
    }

    #[Test]
    public function test_next_month_attendance_is_visible_when_next_button_clicked()
    {
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 2. 翌月の日付で勤怠情報を登録
        $nextMonth = now()->addMonth();
        $dates = [
            $nextMonth->copy()->startOfMonth()->toDateString(),
            $nextMonth->copy()->startOfMonth()->addDays(3)->toDateString(), // ← 月初から+3日
            $nextMonth->copy()->endOfMonth()->toDateString(),
        ];
        foreach ($dates as $date) {
            Attendance::factory()->create([
                'user_id' => $user->id,
                'work_date' => $date,
                'status' => 'clocked_out',
            ]);
        }

        // 3. 勤怠一覧ページを開き、「翌月」ボタンを押す（GETパラメータで翌月指定）
        $response = $this->get('/attendance/list?month=' . $nextMonth->format('Y-m'));

        // 期待挙動：翌月の情報が表示されている
        foreach ($dates as $date) {
            $carbon = \Carbon\Carbon::parse($date);
            $week = ['日','月','火','水','木','金','土'][$carbon->dayOfWeek];
            $formatted = $carbon->format('m/d') . '(' . $week . ')';
            $response->assertSee($formatted);
        }
        // 翌月の年月も表示されていること
        $response->assertSee($nextMonth->format('Y/m'));
    }

    #[Test]
    public function test_attendance_detail_link_navigates_to_detail_page()
    {
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        // 2. 勤怠情報を1件登録
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'status' => 'clocked_out',
        ]);

        // 3. 勤怠一覧ページを開く
        $response = $this->get('/attendance/list');

        // 「詳細」リンクが正しいURLで表示されていること
        $detailUrl = '/attendance/detail/' . $attendance->id;
        $response->assertSee($detailUrl);

        // 4. 「詳細」リンクにアクセスし、詳細画面に遷移できること
        $detailResponse = $this->get($detailUrl);
        $detailResponse->assertStatus(200);
        // 詳細画面に日付が表示されていること
        $carbon = \Carbon\Carbon::parse($attendance->work_date);
        $year = $carbon->format('Y年');
        $monthDay = $carbon->format('n月j日');
        $detailResponse->assertSee($year);
        $detailResponse->assertSee($monthDay);
    }

}