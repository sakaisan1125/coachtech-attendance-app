<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Attendance;

class AttendanceStatusTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;
    #[Test]
    public function test_attendance_status_off_duty(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'status' => 'off_duty',
        ]);

        $response = $this->get('/attendance');
        $response->assertSee('勤務外');
    }

    #[Test]
    public function test_attendance_status_on_duty(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'status' => 'on_duty',
        ]);

        $response = $this->get('/attendance');
        $response->assertSee('出勤中');
    }

    #[Test]
    public function test_attendance_status_on_break(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'status' => 'on_break',
        ]);

        $response = $this->get('/attendance');
        $response->assertSee('休憩中');
    }

    #[Test]
    public function test_attendance_status_clocked_out(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'status' => 'clocked_out',
        ]);

        $response = $this->get('/attendance');
        $response->assertSee('退勤済');
    }
}
