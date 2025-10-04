<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Attendance;

class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_attendance_status_off_duty(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'status' => 'off_duty',
        ]);
        $this->get('/attendance')->assertSee('勤務外');
    }

    #[Test]
    public function test_attendance_status_on_duty(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'status' => 'on_duty',
        ]);
        $this->get('/attendance')->assertSee('出勤中');
    }

    #[Test]
    public function test_attendance_status_on_break(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'status' => 'on_break',
        ]);
        $this->get('/attendance')->assertSee('休憩中');
    }

    #[Test]
    public function test_attendance_status_clocked_out(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'status' => 'clocked_out',
        ]);
        $this->get('/attendance')->assertSee('退勤済');
    }
}
