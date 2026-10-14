<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
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

    public function test_student_profile_page_shows_identity_status_and_grouped_fields(): void
    {
        $student = User::factory()->create(['name' => 'Alex Rivera', 'university_id' => '2026-0101', 'email' => 'alex@example.test']);
        DB::table('profiles')->insert(['user_id' => $student->id, 'department' => 'Information Technology', 'year' => '3', 'section' => 'A', 'contact' => '09171234567', 'status' => 'Verified', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($student)->get('/dashboard/profile')->assertOk()
            ->assertSee('Alex Rivera')->assertSee('2026-0101')->assertSee('Verified')
            ->assertSee('Contact details')->assertSee('Academic details')->assertSee('Save profile changes');
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

    public function test_overview_content_is_specific_to_each_role(): void
    {
        $student = User::factory()->create(['name' => 'Alex Rivera']);
        $admin = User::factory()->admin()->create(['name' => 'Maria Dela Cruz']);
        $superAdmin = User::factory()->create(['name' => 'System Administrator', 'role' => 'super_admin']);

        $this->actingAs($student)->get('/dashboard')
            ->assertOk()
            ->assertSee('Needs your attention')
            ->assertSee('Next available activity')
            ->assertDontSee('Account oversight');

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Review queue')
            ->assertSee('Coming up')
            ->assertDontSee('Account oversight');

        $this->actingAs($superAdmin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Account oversight')
            ->assertSee('Changed recently');
    }

    public function test_activity_targeting_uses_department_year_and_section(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        DB::table('profiles')->insert(['user_id' => $student->id, 'department' => 'Nursing', 'year' => '2', 'section' => 'B', 'contact' => '09170000000', 'status' => 'Verified', 'created_at' => now(), 'updated_at' => now()]);
        $matching = $this->createActivity($admin, 50, 'Second year recollection', department: 'Nursing');
        DB::table('activities')->where('id', $matching)->update(['target_year' => '2', 'target_section' => 'B']);
        $hidden = $this->createActivity($admin, 50, 'Third year recollection', department: 'Nursing');
        DB::table('activities')->where('id', $hidden)->update(['target_year' => '3', 'target_section' => 'A']);

        $this->actingAs($student)->get('/dashboard/activities')
            ->assertOk()->assertSee('Upcoming activities')->assertSee('Places')
            ->assertSee('Second year recollection')->assertDontSee('Third year recollection');
    }

    public function test_registration_questions_are_required_and_answers_are_saved(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $activity = $this->createActivity($admin, 50);
        $field = DB::table('activity_form_fields')->insertGetId(['activity_id' => $activity, 'label' => 'Emergency contact', 'field_type' => 'text', 'required' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($student)->post("/activities/{$activity}/register")->assertSessionHasErrors("answers.{$field}");
        $this->actingAs($student)->post("/activities/{$activity}/register", ['answers' => [$field => 'Maria Rivera — 09171234567']])->assertSessionHas('success');
        $registration = DB::table('registrations')->where('user_id', $student->id)->where('activity_id', $activity)->value('id');
        $this->assertDatabaseHas('registration_answers', ['registration_id' => $registration, 'field_id' => $field, 'answer' => 'Maria Rivera — 09171234567']);
    }

    public function test_student_can_submit_a_reflection_and_staff_can_request_correction(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $activity = $this->createActivity($admin, 50);
        $this->actingAs($student)->post("/activities/{$activity}/register");
        $registration = DB::table('registrations')->where('user_id', $student->id)->where('activity_id', $activity)->value('id');
        $requirement = DB::table('activity_requirements')->insertGetId(['activity_id' => $activity, 'name' => 'Mass reflection', 'type' => 'reflection', 'required' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($student)->post("/registrations/{$registration}/requirements/{$requirement}", ['text_content' => 'I reflected on service and community.'])->assertSessionHas('success');
        $submission = DB::table('requirement_submissions')->where('registration_id', $registration)->value('id');
        $this->actingAs($admin)->patch("/requirement-submissions/{$submission}", ['status' => 'Requires correction', 'note' => 'Please connect this to the Gospel reading.'])->assertSessionHas('success');
        $this->assertDatabaseHas('requirement_submissions', ['id' => $submission, 'status' => 'Requires correction', 'note' => 'Please connect this to the Gospel reading.']);
    }

    public function test_retreat_creation_adds_the_standard_form_and_receipt_requirement(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = ['title' => 'Senior retreat', 'type' => 'Retreat', 'description' => 'A guided Catholic retreat.', 'activity_date' => now()->addDays(20)->toDateString(), 'activity_time' => '08:00', 'venue' => 'Formation Center', 'department' => 'All departments', 'target_year' => '', 'target_section' => '', 'participation_type' => 'Required', 'deadline' => now()->addDays(10)->toDateString(), 'requirements' => '', 'required_documents' => '', 'registration_questions' => '', 'capacity' => 80, 'status' => 'Published'];

        $this->actingAs($admin)->post('/activities', $payload)->assertSessionHas('success');
        $activity = DB::table('activities')->where('title', 'Senior retreat')->value('id');
        $this->assertDatabaseHas('activity_form_fields', ['activity_id' => $activity, 'label' => 'Emergency contact person', 'section_name' => 'Emergency information']);
        $this->assertDatabaseHas('activity_form_fields', ['activity_id' => $activity, 'label' => 'Is this your first Catholic retreat?', 'field_type' => 'select']);
        $this->assertDatabaseHas('activity_requirements', ['activity_id' => $activity, 'name' => 'Payment receipt', 'type' => 'receipt']);
    }

    public function test_verified_student_submits_formation_form_with_receipt(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create(['email' => 'student@example.test']);
        DB::table('profiles')->insert(['user_id' => $student->id, 'department' => 'Nursing', 'year' => '2', 'section' => 'B', 'contact' => '09170000000', 'status' => 'Verified', 'created_at' => now(), 'updated_at' => now()]);
        $payload = ['title' => 'Nursing recollection', 'type' => 'Recollection', 'description' => 'A guided recollection.', 'activity_date' => now()->addDays(20)->toDateString(), 'activity_time' => '08:00', 'venue' => 'Chapel', 'department' => 'Nursing', 'target_year' => '2', 'target_section' => 'B', 'participation_type' => 'Required', 'deadline' => now()->addDays(10)->toDateString(), 'requirements' => '', 'required_documents' => '', 'registration_questions' => '', 'capacity' => 80, 'status' => 'Published'];
        $this->actingAs($admin)->post('/activities', $payload);
        $activity = DB::table('activities')->where('title', 'Nursing recollection')->value('id');
        $fields = DB::table('activity_form_fields')->where('activity_id', $activity)->get();
        $answers = [];
        foreach ($fields as $field) {
            if (!$field->required && $field->field_type !== 'checkbox') continue;
            $answers[$field->id] = match ($field->field_type) {
                'number' => '20',
                'select' => json_decode($field->options, true)[0],
                'checkbox' => 'yes',
                default => 'Student response',
            };
        }

        $this->actingAs($student)->post("/activities/{$activity}/register", ['answers' => $answers, 'receipt' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg')])->assertSessionHas('success');
        $registration = DB::table('registrations')->where('activity_id', $activity)->where('user_id', $student->id)->value('id');
        $receipt = DB::table('requirement_submissions')->where('registration_id', $registration)->first();
        $this->assertSame('Submitted', $receipt->status);
        Storage::disk('local')->assertExists($receipt->path);
        $this->assertDatabaseHas('registration_answers', ['registration_id' => $registration, 'answer' => 'Student response']);
    }

    public function test_super_admin_can_create_a_student_account_with_email(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin)->post('/accounts', ['name' => 'Ana Reyes', 'university_id' => '2026-9001', 'email' => 'ana.reyes@example.test', 'role' => 'student', 'password' => 'Temporary123!'])->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['university_id' => '2026-9001', 'email' => 'ana.reyes@example.test', 'role' => 'student', 'active' => true]);
    }

    public function test_cfo_staff_records_daily_time_in_and_time_out_with_photos(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-10-14 08:00:00');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/staff-attendance', ['checkpoint' => 'in', 'photo' => UploadedFile::fake()->create('time-in.jpg', 100, 'image/jpeg')])->assertSessionHas('success');
        Carbon::setTestNow('2026-10-14 17:00:00');
        $this->actingAs($admin)->post('/staff-attendance', ['checkpoint' => 'out', 'photo' => UploadedFile::fake()->create('time-out.jpg', 100, 'image/jpeg')])->assertSessionHas('success');

        $entry = DB::table('staff_attendance')->where('user_id', $admin->id)->first();
        $this->assertNotNull($entry->time_in_photo_path);
        $this->assertNotNull($entry->time_out_photo_path);
        Storage::disk('local')->assertExists($entry->time_in_photo_path);
        Storage::disk('local')->assertExists($entry->time_out_photo_path);
        Carbon::setTestNow();
    }

    public function test_staff_attendance_prevents_duplicates_and_requires_time_in_first(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $photo = fn () => UploadedFile::fake()->create('attendance.jpg', 100, 'image/jpeg');

        $this->actingAs($admin)->post('/staff-attendance', ['checkpoint' => 'out', 'photo' => $photo()])->assertSessionHasErrors('staff_attendance');
        $this->actingAs($admin)->post('/staff-attendance', ['checkpoint' => 'in', 'photo' => $photo()])->assertSessionHas('success');
        $this->actingAs($admin)->post('/staff-attendance', ['checkpoint' => 'in', 'photo' => $photo()])->assertSessionHasErrors('staff_attendance');
        $this->assertSame(1, DB::table('staff_attendance')->where('user_id', $admin->id)->count());
    }

    public function test_only_super_admin_can_correct_staff_attendance(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $attendance = DB::table('staff_attendance')->insertGetId(['user_id' => $admin->id, 'work_date' => '2026-10-14', 'time_in' => '2026-10-14 08:00:00', 'status' => 'Present', 'created_at' => now(), 'updated_at' => now()]);
        $change = ['time_in' => '2026-10-14 08:15:00', 'time_out' => '2026-10-14 17:00:00', 'status' => 'Present', 'admin_note' => 'Corrected after reviewing the office log.'];

        $this->actingAs($otherAdmin)->patch("/staff-attendance/{$attendance}", $change)->assertForbidden();
        $this->actingAs($superAdmin)->patch("/staff-attendance/{$attendance}", $change)->assertSessionHas('success');
        $this->assertDatabaseHas('staff_attendance', ['id' => $attendance, 'corrected_by' => $superAdmin->id, 'admin_note' => 'Corrected after reviewing the office log.']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $superAdmin->id, 'action' => 'Corrected staff attendance', 'target' => (string) $attendance]);
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
