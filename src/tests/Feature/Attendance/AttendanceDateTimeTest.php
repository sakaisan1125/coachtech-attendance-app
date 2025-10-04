<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AttendanceDateTimeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_displays_current_datetime_on_attendance_screen(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('attendance'));

        $date = Carbon::now()->isoFormat('YYYY年M月D日(ddd)');
        $response->assertSee($date);

        $time = Carbon::now()->format('H:i');
        $response->assertSee($time);
    }
}