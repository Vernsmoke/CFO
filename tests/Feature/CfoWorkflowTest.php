<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_registration_respects_activity_capacity(): void
    {
        $admin = User::factory()->admin()->create();
        $firstStudent = User::factory()->create();
        $secondStudent = User::factory()->create();
        $activity = $this->createActivity($admin, 1);

        DB::table('registrations')->insert([
            'user_id' => $firstStudent->id,
            'activity_id' => $activity,
            'status' => 'Submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($secondStudent)
            ->post("/activities/{$activity}/register")
            ->assertSessionHasErrors('registration');

        $this->assertDatabaseMissing('registrations', [
            'user_id' => $secondStudent->id,
            'activity_id' => $activity,
        ]);
    }

    public function test_repeat_registration_does_not_reset_an_existing_review(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $activity = $this->createActivity($admin, 1);

        DB::table('registrations')->insert([
            'user_id' => $student->id,
            'activity_id' => $activity,
            'status' => 'Accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student)
            ->post("/activities/{$activity}/register")
            ->assertSessionHas('success', 'You are already registered for this activity.');

        $this->assertDatabaseHas('registrations', [
            'user_id' => $student->id,
            'activity_id' => $activity,
            'status' => 'Accepted',
        ]);
    }

    public function test_csv_export_escapes_spreadsheet_formulas(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create([
            'university_id' => '=2+3',
            'name' => '@malicious',
        ]);
        $activity = $this->createActivity($admin, 10, '+Formula activity');

        DB::table('registrations')->insert([
            'user_id' => $student->id,
            'activity_id' => $activity,
            'status' => 'Submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $content = $this->actingAs($admin)->get('/reports/export')->streamedContent();

        $this->assertStringContainsString("'=2+3", $content);
        $this->assertStringContainsString("'@malicious", $content);
        $this->assertStringContainsString("'+Formula activity", $content);
    }

    private function createActivity(User $admin, int $capacity, string $title = 'Test activity'): int
    {
        return DB::table('activities')->insertGetId([
            'title' => $title,
            'type' => 'Retreat',
            'description' => 'Test description',
            'activity_date' => now()->addDays(10)->toDateString(),
            'activity_time' => '08:00',
            'venue' => 'Test venue',
            'department' => 'All departments',
            'deadline' => now()->addDays(5)->toDateString(),
            'status' => 'Published',
            'capacity' => $capacity,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
