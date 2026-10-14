<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_students_only_see_published_activities_for_their_department(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        DB::table('profiles')->insert([
            'user_id' => $student->id, 'department' => 'CIT', 'year' => '3', 'section' => 'A',
            'contact' => 'student@example.invalid', 'status' => 'Verified', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createActivity($admin, 10, 'Visible department activity', 'Published', 'CIT');
        $this->createActivity($admin, 10, 'Visible all-departments activity', 'Published', 'All departments');
        $this->createActivity($admin, 10, 'Hidden draft activity', 'Draft', 'CIT');
        $this->createActivity($admin, 10, 'Hidden other-department activity', 'Published', 'Nursing');

        $this->actingAs($student)->get('/dashboard/activities')
            ->assertSee('Visible department activity')
            ->assertSee('Visible all-departments activity')
            ->assertDontSee('Hidden draft activity')
            ->assertDontSee('Hidden other-department activity');
    }

    public function test_students_only_see_announcements_for_their_department(): void
    {
        $student = User::factory()->create();
        DB::table('profiles')->insert([
            'user_id' => $student->id, 'department' => 'CIT', 'year' => '3', 'section' => 'A',
            'contact' => 'student@example.invalid', 'status' => 'Verified', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['All students', 'All departments'], ['CIT students', 'CIT'], ['Nursing students', 'Nursing']] as [$title, $department]) {
            DB::table('announcements')->insert(['title' => $title, 'body' => 'Update', 'department' => $department, 'author_id' => User::factory()->admin()->create()->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($student)->get('/dashboard/announcements')
            ->assertSee('All students')
            ->assertSee('CIT students')
            ->assertDontSee('Nursing students');
    }

    public function test_staff_can_review_a_student_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $profile = DB::table('profiles')->insertGetId([
            'user_id' => $student->id, 'department' => 'CIT', 'year' => '3', 'section' => 'A',
            'contact' => 'student@example.invalid', 'status' => 'Pending verification', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->patch("/profiles/{$profile}", ['status' => 'Verified', 'note' => 'Record confirmed.'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('profiles', ['id' => $profile, 'status' => 'Verified', 'note' => 'Record confirmed.']);
        $this->assertDatabaseHas('notifications', ['user_id' => $student->id, 'message' => 'Your profile status is now Verified.']);
    }

    public function test_attendance_requires_an_accepted_registration_and_preserves_first_checkpoint(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $activity = $this->createActivity($admin, 10);
        $registration = DB::table('registrations')->insertGetId([
            'user_id' => $student->id, 'activity_id' => $activity, 'status' => 'Submitted',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post("/registrations/{$registration}/attendance", ['checkpoint' => 'in'])
            ->assertSessionHasErrors('attendance');
        DB::table('registrations')->where('id', $registration)->update(['status' => 'Accepted']);
        $this->actingAs($admin)->post("/registrations/{$registration}/attendance", ['checkpoint' => 'out'])
            ->assertSessionHasErrors('attendance');

        Carbon::setTestNow('2026-10-14 09:00:00');
        $this->actingAs($admin)->post("/registrations/{$registration}/attendance", ['checkpoint' => 'in'])
            ->assertSessionHas('success', 'Attendance recorded.');
        Carbon::setTestNow('2026-10-14 09:15:00');
        $this->actingAs($admin)->post("/registrations/{$registration}/attendance", ['checkpoint' => 'in'])
            ->assertSessionHas('success', 'Attendance was already recorded.');
        $this->assertDatabaseHas('attendance', ['registration_id' => $registration, 'time_in' => '2026-10-14 09:00:00']);
        Carbon::setTestNow();
    }

    public function test_account_update_returns_not_found_for_missing_user(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin)->patch('/accounts/999999', ['role' => 'student', 'active' => true])->assertNotFound();
    }

    private function createActivity(User $admin, int $capacity, string $title = 'Test activity', string $status = 'Published', string $department = 'All departments'): int
    {
        return DB::table('activities')->insertGetId([
            'title' => $title,
            'type' => 'Retreat',
            'description' => 'Test description',
            'activity_date' => now()->addDays(10)->toDateString(),
            'activity_time' => '08:00',
            'venue' => 'Test venue',
            'department' => $department,
            'deadline' => now()->addDays(5)->toDateString(),
            'status' => $status,
            'capacity' => $capacity,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
