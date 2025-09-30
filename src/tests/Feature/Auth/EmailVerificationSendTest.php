<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;

class EmailVerificationSendTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_verification_email_is_sent_after_registration()
    {
        Notification::fake();

        // 1. 会員登録
        $email = 'newuser@example.com';
        $response = $this->post('/register', [
            'name'                  => '新規ユーザー',
            'email'                 => $email,
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $response->assertStatus(302);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        // 2. 認証メールを送信（認証必須ルートのためログイン状態にする）
        $this->actingAs($user);
        $send = $this->post('/email/verification-notification');
        $send->assertStatus(302);
        $send->assertSessionHas('status', 'verification-link-sent');

        // 期待挙動: 登録したメール宛に認証メール（VerifyEmail 通知）が送信される
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function test_clicking_verify_here_navigates_to_email_verification_site()
    {
        // 未認証ユーザーでログイン
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        $this->actingAs($user);

        // 1. 誘導画面を表示
        $response = $this->get('/email/verify'); // 実装の誘導画面URLに合わせる
        $response->assertStatus(200);
        // 「認証はこちらから」のボタン（リンク文言）が表示されている
        $response->assertSee('認証はこちらから');

        // 2. 「認証はこちらから」押下相当（リンク先へ遷移）
        $verifiedUrl = route('email.verified'); // verify-email.blade.php の href に合わせる
        $dest = $this->get($verifiedUrl);
        $dest->assertStatus(200);

        // 3. メール認証サイト（verified.blade.php）の要素を確認
        // 未認証時にだけ出る「状態を更新する」ボタン文言で画面を特定
        $dest->assertSee('状態を更新する');
        // 再送ボタンがあること（画面の主要機能）
        $dest->assertSee('認証メールを再送する');
    }

    #[Test]
    public function test_after_email_verification_user_can_navigate_to_attendance_page()
    {
        // 未認証ユーザー作成・ログイン
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user);

        // メール内の認証リンク（署名付きURL）を生成しアクセス＝認証完了
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $resp = $this->get($verificationUrl);
        $resp->assertStatus(302); // 認証後はリダイレクト

        // ユーザーが認証済みになっている
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // 認証サイトを表示すると「完了」表示と遷移ボタンがある
        $verifiedPage = $this->get(route('email.verified'));
        $verifiedPage->assertStatus(200);
        $verifiedPage->assertSee('メール認証は完了しています！');
        $verifiedPage->assertSee('勤怠登録画面へ');

        // 勤怠登録画面へ遷移できる
        $attendance = $this->get(route('attendance'));
        $attendance->assertStatus(200);
    }
}