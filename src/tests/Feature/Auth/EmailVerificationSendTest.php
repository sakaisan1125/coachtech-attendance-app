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

        $email = 'newuser@example.com';
        $response = $this->post('/register', [
            'name' => '新規ユーザー',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $response->assertStatus(302);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        $this->actingAs($user);
        $send = $this->post('/email/verification-notification');
        $send->assertStatus(302);
        $send->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function test_clicking_verify_here_navigates_to_email_verification_site()
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user);

        $response = $this->get('/email/verify');
        $response->assertStatus(200);
        $response->assertSee('認証はこちらから');

        $verifiedUrl = route('email.verified');
        $dest = $this->get($verifiedUrl);
        $dest->assertStatus(200);
        $dest->assertSee('状態を更新する');
        $dest->assertSee('認証メールを再送する');
    }

    #[Test]
    public function test_after_email_verification_user_can_navigate_to_attendance_page()
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $resp = $this->get($verificationUrl);
        $resp->assertStatus(302);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $verifiedPage = $this->get(route('email.verified'));
        $verifiedPage->assertStatus(200);
        $verifiedPage->assertSee('メール認証は完了しています！');
        $verifiedPage->assertSee('勤怠登録画面へ');

        $attendance = $this->get(route('attendance'));
        $attendance->assertStatus(200);
    }
}