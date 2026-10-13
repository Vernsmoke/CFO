<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CfoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_user_can_sign_in_with_university_id(): void
    {
        $user = User::factory()->create(['university_id' => '2026-0001', 'password' => 'password']);
        $this->post('/login', ['university_id' => '2026-0001', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_student_cannot_open_staff_attendance_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard/attendance')->assertNotFound();
    }

    public function test_admin_can_open_staff_attendance_page(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/dashboard/attendance')->assertOk();
    }
}
