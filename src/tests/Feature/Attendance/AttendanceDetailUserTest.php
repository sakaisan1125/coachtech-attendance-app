<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use PHPUnit\Framework\Attributes\Test;

class AttendanceDetailUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_attendance_detail_shows_logged_in_user_name()
    {
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 2. 勤怠情報を1件登録
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'status' => 'clocked_out',
        ]);

        // 3. 勤怠詳細ページを開く
        $response = $this->get('/attendance/detail/' . $attendance->id);

        // 4. 名前欄にログインユーザーの氏名が表示されていること
        $response->assertSee($user->name);
    }

    #[Test]
    public function test_attendance_detail_shows_selected_date()
    {
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 2. 任意の日付で勤怠情報を登録
        $selectedDate = now()->subDays(3)->toDateString();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $selectedDate,
            'status' => 'clocked_out',
        ]);

        // 3. 勤怠詳細ページを開く
        $response = $this->get('/attendance/detail/' . $attendance->id);

        // 4. 日付欄が選択した日付になっていること（画面表示形式に合わせる）
        $carbon = \Carbon\Carbon::parse($selectedDate);
        $year = $carbon->format('Y年');
        $monthDay = $carbon->format('n月j日');
        $response->assertSee($year);
        $response->assertSee($monthDay);
    }

    #[Test]
    public function test_attendance_detail_shows_correct_clock_in_and_out_times()
    {
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 2. 出勤・退勤時刻を指定して勤怠情報を登録
        $clockIn = now()->setTime(9, 0);
        $clockOut = now()->setTime(18, 0);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'status' => 'clocked_out',
        ]);

        // 3. 勤怠詳細ページを開く
        $response = $this->get('/attendance/detail/' . $attendance->id);

        // 4. 出勤・退勤欄が打刻と一致していること（画面表示形式に合わせる）
        $response->assertSee($clockIn->format('H:i'));
        $response->assertSee($clockOut->format('H:i'));
    }

    #[Test]
    public function test_attendance_detail_shows_correct_break_times()
    {
        // 1. 勤怠情報が登録されたユーザーにログイン
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 2. 勤怠情報と休憩情報を登録
        $breakStart = now()->setTime(11, 0);
        $breakEnd = now()->setTime(13, 0);
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
            'status' => 'clocked_out',
        ]);
        // 休憩情報を関連テーブルに登録（リレーション名は breaks と仮定）
        $attendance->breaks()->create([
            'break_start_at' => $breakStart,
            'break_end_at' => $breakEnd,
        ]);

        // 3. 勤怠詳細ページを開く
        $response = $this->get('/attendance/detail/' . $attendance->id);

        // 4. 休憩欄が打刻と一致していること（画面表示形式に合わせる）
        $response->assertSee($breakStart->format('H:i'));
        $response->assertSee($breakEnd->format('H:i'));
    }
}