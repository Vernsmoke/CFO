<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function test_student_activity_attendance_page_is_not_available(): void
    {
        $student = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($student)->get('/dashboard/attendance')->assertNotFound();
        $this->actingAs($admin)->get('/dashboard/attendance')->assertNotFound();
        $this->actingAs($admin)->post('/registrations/1/attendance', ['checkpoint' => 'in'])->assertNotFound();
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertDontSee('Activity attendance');
    }

    public function test_student_profile_page_shows_identity_status_and_grouped_fields(): void
    {
        $student = User::factory()->create(['name' => 'Alex Rivera', 'university_id' => '2026-0101', 'email' => 'alex@example.test']);
        DB::table('profiles')->insert(['user_id' => $student->id, 'department' => 'Information Technology', 'year' => '3', 'section' => 'A', 'contact' => '09171234567', 'status' => 'Verified', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($student)->get('/dashboard/profile')->assertOk()
            ->assertSee('Alex Rivera')->assertSee('2026-0101')->assertSee('Verified')
            ->assertSee('Contact details')->assertSee('Identity and academic details')->assertSee('View only')
            ->assertSee('Save contact details')
            ->assertDontSee('name="department"', false)
            ->assertDontSee('name="course"', false)
            ->assertDontSee('name="year"', false)
            ->assertDontSee('name="section"', false);
    }

    public function test_registration_respects_activity_capacity(): void
    {
        $admin = User::factory()->admin()->create();
        $firstStudent = User::factory()->create();
        $secondStudent = User::factory()->create();
        $activity = $this->createActivity($admin, 1);
        $this->createProfile($secondStudent);

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
        $this->createProfile($student);

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
        $this->assertStringNotContainsString('Time in', $content);
        $this->assertStringNotContainsString('Time out', $content);
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
        $this->createActivity($admin, 10, 'Hidden all-departments activity', 'Published', 'All departments');
        $this->createActivity($admin, 10, 'Hidden draft activity', 'Draft', 'CIT');
        $this->createActivity($admin, 10, 'Hidden other-department activity', 'Published', 'Nursing');

        $this->actingAs($student)->get('/dashboard/activities')
            ->assertSee('Visible department activity')
            ->assertDontSee('Hidden all-departments activity')
            ->assertDontSee('Hidden draft activity')
            ->assertDontSee('Hidden other-department activity');
    }

    public function test_student_cannot_register_for_an_activity_outside_their_audience(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        DB::table('profiles')->insert([
            'user_id' => $student->id, 'department' => 'CIT', 'year' => '3', 'section' => 'A',
            'contact' => 'student@example.invalid', 'status' => 'Verified', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $activity = $this->createActivity($admin, 10, 'Nursing-only activity', 'Published', 'Nursing');

        $this->actingAs($student)->post("/activities/{$activity}/register")->assertNotFound();

        $this->assertDatabaseMissing('registrations', [
            'user_id' => $student->id,
            'activity_id' => $activity,
        ]);
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
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'message' => 'Your profile status is now Verified.',
            'category' => 'profile',
            'action_section' => 'profile',
        ]);
    }

    public function test_student_can_open_and_individually_read_an_actionable_notification(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $notification = DB::table('notifications')->insertGetId([
            'user_id' => $student->id,
            'message' => 'Your registration needs a correction.',
            'category' => 'registration',
            'action_section' => 'submissions',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($student)->get('/dashboard/notifications')
            ->assertOk()
            ->assertSee('1 unread update')
            ->assertSee('View submission')
            ->assertSee('Mark as read');

        $this->actingAs($otherStudent)->patch("/notifications/{$notification}/read")->assertNotFound();
        $this->actingAs($student)->patch("/notifications/{$notification}/read")->assertSessionHas('success');
        $this->assertDatabaseMissing('notifications', ['id' => $notification, 'read_at' => null]);
    }

    public function test_empty_student_submission_page_guides_student_to_activities(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/dashboard/submissions')
            ->assertOk()
            ->assertSee('Your activity record will appear here.')
            ->assertSee('Browse activities');
    }

    public function test_student_submission_page_shows_review_progress_and_next_step(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $this->createProfile($student);
        $activity = $this->createActivity($admin, 30, 'Student progress retreat');

        $this->actingAs($student)->post("/activities/{$activity}/register")->assertSessionHas('success');

        $this->actingAs($student)->get('/dashboard/submissions')
            ->assertOk()
            ->assertSee('Registration progress')
            ->assertSee('Student progress retreat')
            ->assertSee('CFO review')
            ->assertSee('Wait for CFO review');
    }

    public function test_only_staff_can_change_student_name_and_academic_details(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create(['name' => 'OLD NAME']);
        $profile = DB::table('profiles')->insertGetId([
            'user_id' => $student->id,
            'department' => 'COLLEGE OF INFORMATION TECHNOLOGY',
            'course' => 'Bachelor of Science in Information Technology',
            'major' => null,
            'year' => '3',
            'section' => 'A',
            'contact' => '09170000000',
            'status' => 'Verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $changes = [
            'name' => 'UPDATED NAME',
            'department' => 'COLLEGE OF INFORMATION TECHNOLOGY',
            'course' => 'Bachelor of Library and Information Science',
            'major' => '',
            'year' => '4',
            'section' => 'B',
            'email' => 'staff-cannot-change-this@example.test',
            'contact' => 'staff-cannot-change-this',
        ];

        $this->actingAs($student)->patch("/profiles/{$profile}/details", $changes)->assertForbidden();
        $this->actingAs($admin)->patch("/profiles/{$profile}/details", $changes)->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'name' => 'UPDATED NAME', 'email' => $student->email]);
        $this->assertDatabaseHas('profiles', [
            'id' => $profile,
            'course' => 'Bachelor of Library and Information Science',
            'year' => '4',
            'section' => 'B',
            'contact' => '09170000000',
            'status' => 'Verified',
        ]);
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
            ->assertSee('Here is what the office needs today.')
            ->assertSee('Review queue')
            ->assertSee('Coming up')
            ->assertDontSee('Account oversight');

        $this->actingAs($superAdmin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Account oversight')
            ->assertSee('Changed recently');
    }

    public function test_staff_submission_center_separates_activity_and_profile_reviews(): void
    {
        $admin = User::factory()->admin()->create();
        $pendingStudent = User::factory()->create(['name' => 'Pending Student']);
        $verifiedStudent = User::factory()->create(['name' => 'Verified Student']);
        $pendingActivity = $this->createActivity($admin, 20, 'Pending retreat');
        $completedActivity = $this->createActivity($admin, 20, 'Completed retreat');

        DB::table('profiles')->insert([
            ['user_id' => $pendingStudent->id, 'department' => 'CIT', 'year' => '3', 'section' => 'A', 'contact' => '09170000001', 'status' => 'Pending verification', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $verifiedStudent->id, 'department' => 'CIT', 'year' => '3', 'section' => 'B', 'contact' => '09170000002', 'status' => 'Verified', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('registrations')->insert([
            ['user_id' => $pendingStudent->id, 'activity_id' => $pendingActivity, 'status' => 'Submitted', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $verifiedStudent->id, 'activity_id' => $completedActivity, 'status' => 'Accepted', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($admin)->get('/dashboard/submissions')
            ->assertOk()
            ->assertSee('Student review center')
            ->assertSee('Pending Student')
            ->assertSee('Pending retreat')
            ->assertDontSee('Verified Student')
            ->assertDontSee('Completed retreat');

        $this->actingAs($admin)->get("/dashboard/submissions?review=all&review_activity={$completedActivity}")
            ->assertOk()
            ->assertSee('Verified Student')
            ->assertSee('Completed retreat');

        $this->actingAs($admin)->get('/dashboard/submissions?review=all&review_area=profiles')
            ->assertOk()
            ->assertSee('Pending Student')
            ->assertSee('Verified Student')
            ->assertDontSee('Pending retreat');
    }

    public function test_facilities_page_redirects_to_activities_and_legacy_writes_are_removed(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard/facilities')->assertRedirect('/dashboard/activities');
        $this->actingAs($admin)->post('/facilities')->assertNotFound();
        $this->actingAs($admin)->patch('/facilities/1')->assertNotFound();
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertDontSee('>Facilities<', false);
    }

    public function test_archived_announcement_moves_to_staff_history_and_is_hidden_from_students(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $this->createProfile($student);
        $announcement = DB::table('announcements')->insertGetId([
            'title' => 'Formation reminder',
            'body' => 'Bring your journal.',
            'department' => 'CIT',
            'author_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->patch("/announcements/{$announcement}/archive", ['action' => 'archive'])->assertSessionHas('success');
        $this->actingAs($student)->get('/dashboard/announcements')->assertOk()->assertDontSee('Formation reminder');
        $this->actingAs($admin)->get('/dashboard/announcements?announcement_state=archived')->assertOk()
            ->assertSee('Formation reminder')->assertSee('Archived');
    }

    public function test_report_page_and_csv_apply_the_same_activity_type_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $firstStudent = User::factory()->create(['name' => 'Retreat Student']);
        $secondStudent = User::factory()->create(['name' => 'Mass Student']);
        $this->createProfile($firstStudent);
        $this->createProfile($secondStudent);
        $retreat = $this->createActivity($admin, 20, 'Filtered retreat');
        $mass = $this->createActivity($admin, 20, 'Hidden mass');
        DB::table('activities')->where('id', $mass)->update(['type' => 'Mass']);
        DB::table('registrations')->insert([
            ['user_id' => $firstStudent->id, 'activity_id' => $retreat, 'status' => 'Accepted', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $secondStudent->id, 'activity_id' => $mass, 'status' => 'Accepted', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($admin)->get('/dashboard/reports?report_type=Retreat')->assertOk()
            ->assertSee('Retreat Student')->assertDontSee('Mass Student');
        $csv = $this->actingAs($admin)->get('/reports/export?report_type=Retreat')->streamedContent();
        $this->assertStringContainsString('Filtered retreat', $csv);
        $this->assertStringNotContainsString('Hidden mass', $csv);
    }

    public function test_mark_filtered_notifications_read_only_updates_matching_category(): void
    {
        $admin = User::factory()->admin()->create();
        DB::table('notifications')->insert([
            ['user_id' => $admin->id, 'message' => 'Activity approved', 'category' => 'activity', 'action_section' => 'activities', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $admin->id, 'message' => 'Profile waiting', 'category' => 'profile', 'action_section' => 'submissions', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($admin)->post('/notifications/read', [
            'scope' => 'filtered',
            'notification_state' => 'unread',
            'notification_category' => 'activity',
        ])->assertSessionHas('success');

        $this->assertNotNull(DB::table('notifications')->where('category', 'activity')->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('category', 'profile')->value('read_at'));
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
        $this->actingAs($student)->get("/activities/{$hidden}/register")->assertNotFound();
        $this->actingAs($student)->post("/activities/{$hidden}/register")->assertNotFound();
    }

    public function test_publishing_requires_target_confirmation_and_notifies_only_exact_matches(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $matching = User::factory()->create();
        $otherSection = User::factory()->create();
        $this->createProfile($matching, 'BSIT', '3', 'A');
        $this->createProfile($otherSection, 'BSIT', '3', 'B');
        $payload = $this->activityPayload('BSIT 3A Mass', 'Mass', 'BSIT', '3', 'A');

        $this->actingAs($admin)->post('/activities', [...$payload, 'intent' => 'create'])
            ->assertSessionHasErrors('target_confirmation');

        $this->actingAs($admin)->post('/activities', [...$payload, 'intent' => 'preview'])
            ->assertSessionHas('activity_target_preview.count', 1);
        $token = session('activity_target_preview.token');
        $this->actingAs($admin)->post('/activities', [...$payload, 'intent' => 'create', 'target_confirmation' => $token])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activities', ['title' => 'BSIT 3A Mass', 'created_by' => $admin->id, 'department' => 'BSIT', 'target_year' => '3', 'target_section' => 'A']);
        $this->assertDatabaseHas('notifications', ['user_id' => $matching->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $otherSection->id]);
    }

    public function test_staff_activity_requires_super_admin_approval_before_students_can_see_it(): void
    {
        $staff = User::factory()->admin()->create(['name' => 'CFO Planner']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $student = User::factory()->create();
        $this->createProfile($student, 'BSIT', '3', 'A');
        $payload = $this->activityPayload('Approval workflow retreat', 'Retreat', 'BSIT', '3', 'A');
        $payload['status'] = 'Pending approval';

        $this->actingAs($staff)->post('/activities', [...$payload, 'intent' => 'preview'])
            ->assertSessionHas('activity_target_preview.count', 1);
        $token = session('activity_target_preview.token');
        $this->actingAs($staff)->post('/activities', [...$payload, 'intent' => 'create', 'target_confirmation' => $token])
            ->assertSessionHas('success', 'Activity submitted for Super Admin approval.');

        $activity = DB::table('activities')->where('title', 'Approval workflow retreat')->first();
        $this->assertSame('Pending approval', $activity->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $superAdmin->id, 'category' => 'activity']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $student->id, 'category' => 'activity']);
        $this->actingAs($student)->get('/dashboard/activities')->assertDontSee('Approval workflow retreat');
        $this->actingAs($staff)->patch("/activities/{$activity->id}/decision", ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($superAdmin)->patch("/activities/{$activity->id}/decision", ['decision' => 'approve'])
            ->assertSessionHas('success', 'Activity approved and published.');
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'status' => 'Published', 'reviewed_by' => $superAdmin->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $student->id, 'category' => 'activity']);
        $this->assertDatabaseHas('notifications', ['user_id' => $staff->id, 'category' => 'activity']);
        $this->actingAs($student)->get('/dashboard/activities')->assertSee('Approval workflow retreat');
    }

    public function test_activity_venue_check_reports_confirmed_and_pending_conflicts(): void
    {
        $staff = User::factory()->admin()->create();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $date = now()->addDays(20)->toDateString();
        $published = $this->createActivity($superAdmin, 50, 'Existing chapel activity');
        DB::table('activities')->where('id', $published)->update(['activity_date' => $date, 'activity_time' => '09:00', 'activity_end_time' => '11:00', 'venue' => 'University Chapel']);

        $this->actingAs($staff)->getJson('/activities/availability?'.http_build_query([
            'venue' => 'University Chapel',
            'activity_date' => $date,
            'activity_time' => '10:00',
            'activity_end_time' => '12:00',
        ]))->assertOk()->assertJson(['status' => 'occupied', 'confirmed_conflicts' => 1]);

        DB::table('activities')->where('id', $published)->update(['status' => 'Pending approval']);
        $this->actingAs($staff)->getJson('/activities/availability?'.http_build_query([
            'venue' => 'University Chapel',
            'activity_date' => $date,
            'activity_time' => '10:00',
            'activity_end_time' => '12:00',
        ]))->assertOk()->assertJson(['status' => 'warning', 'pending_conflicts' => 1]);
    }

    public function test_super_admin_cannot_approve_an_activity_that_conflicts_with_a_facility_booking(): void
    {
        $staff = User::factory()->admin()->create();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $student = User::factory()->create();
        $this->createProfile($student, 'BSIT', '3', 'A');
        $date = now()->addDays(20)->toDateString();
        $activity = $this->createActivity($staff, 50, 'Conflicting retreat', 'Pending approval', 'BSIT');
        DB::table('activities')->where('id', $activity)->update(['activity_date' => $date, 'activity_time' => '10:00', 'activity_end_time' => '12:00', 'venue' => 'Formation Center']);
        DB::table('facility_requests')->insert([
            'user_id' => $staff->id,
            'facility' => 'Formation Center',
            'purpose' => 'Office program',
            'starts_at' => "{$date} 09:30:00",
            'ends_at' => "{$date} 11:00:00",
            'participants' => 40,
            'status' => 'Approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)->patch("/activities/{$activity}/decision", ['decision' => 'approve'])
            ->assertSessionHasErrors('activity');
        $this->assertDatabaseHas('activities', ['id' => $activity, 'status' => 'Pending approval']);
    }

    public function test_staff_can_correct_and_resubmit_a_returned_activity(): void
    {
        $staff = User::factory()->admin()->create();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $student = User::factory()->create();
        $this->createProfile($student, 'BSIT', '3', 'A');
        $activity = $this->createActivity($staff, 50, 'Activity needing correction', 'Pending approval', 'BSIT');

        $this->actingAs($superAdmin)->patch("/activities/{$activity}/decision", [
            'decision' => 'changes_requested',
            'review_note' => 'Move this to the Formation Center.',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('activities', ['id' => $activity, 'status' => 'Changes requested', 'review_note' => 'Move this to the Formation Center.']);

        $record = DB::table('activities')->find($activity);
        $this->actingAs($staff)->patch("/activities/{$activity}", [
            'title' => $record->title,
            'description' => $record->description,
            'activity_date' => $record->activity_date,
            'activity_time' => '09:00',
            'activity_end_time' => '13:00',
            'venue' => 'Formation Center',
            'department' => $record->department,
            'target_year' => $record->target_year,
            'target_section' => $record->target_section,
            'participation_type' => $record->participation_type,
            'deadline' => $record->deadline,
            'requirements' => $record->requirements,
            'capacity' => $record->capacity,
        ])->assertSessionHas('success');
        $this->actingAs($staff)->patch("/activities/{$activity}/submit")->assertSessionHas('success');

        $this->assertDatabaseHas('activities', [
            'id' => $activity,
            'status' => 'Pending approval',
            'venue' => 'Formation Center',
            'review_note' => null,
        ]);
    }

    public function test_recollection_and_retreat_forms_use_activity_specific_labels_without_signature(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $student = User::factory()->create(['email' => 'forms@example.test']);
        $this->createProfile($student, 'BSIT', '3', 'A');

        $activities = [];
        foreach (['Recollection', 'Retreat'] as $type) {
            $payload = $this->activityPayload("{$type} for BSIT 3A", $type, 'BSIT', '3', 'A');
            if ($type === 'Retreat') {
                $payload['venue'] = 'Formation Center';
            }
            $this->actingAs($admin)->post('/activities', [...$payload, 'intent' => 'preview']);
            $this->actingAs($admin)->post('/activities', [...$payload, 'intent' => 'create', 'target_confirmation' => session('activity_target_preview.token')]);
            $activities[$type] = DB::table('activities')->where('title', "{$type} for BSIT 3A")->value('id');
        }

        $response = $this->actingAs($student)->get('/dashboard/activities');
        $response->assertOk()
            ->assertSee('Open registration')
            ->assertDontSee('Upload Payment Receipt')
            ->assertDontSee('Type your full name as your signature');

        foreach ($activities as $type => $activity) {
            $this->actingAs($student)->get("/activities/{$activity}/register")
                ->assertOk()
                ->assertSee("{$type} details")
                ->assertSee('Verified student information')
                ->assertSee('Upload Payment Receipt')
                ->assertDontSee('Type your full name as your signature');
        }
        $this->assertDatabaseMissing('activity_form_fields', ['label' => 'Type your full name as your signature']);
    }

    public function test_mass_requires_live_photo_and_honors_required_reflection_setting(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $this->createProfile($student);
        $activity = $this->createActivity($admin, 50, 'Required reflection Mass');
        DB::table('activities')->where('id', $activity)->update(['type' => 'Mass']);
        $photoRequirement = DB::table('activity_requirements')->insertGetId(['activity_id' => $activity, 'name' => 'Real-time attendance photo', 'type' => 'attendance_photo', 'required' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $reflectionRequirement = DB::table('activity_requirements')->insertGetId(['activity_id' => $activity, 'name' => 'Reflection attachment', 'type' => 'reflection_document', 'required' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($student)->post("/activities/{$activity}/register")
            ->assertSessionHasErrors(['attendance_photo', 'reflection_attachment']);
        $this->actingAs($student)->post("/activities/{$activity}/register", [
            'attendance_photo' => UploadedFile::fake()->image('mass.jpg'),
            'capture_source' => 'live_camera',
            'reflection_attachment' => UploadedFile::fake()->create('reflection.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHas('success');

        $registration = DB::table('registrations')->where('activity_id', $activity)->where('user_id', $student->id)->value('id');
        $this->assertDatabaseHas('requirement_submissions', ['registration_id' => $registration, 'requirement_id' => $photoRequirement, 'status' => 'Submitted']);
        $this->assertDatabaseHas('requirement_submissions', ['registration_id' => $registration, 'requirement_id' => $reflectionRequirement, 'status' => 'Submitted']);
    }

    public function test_mass_allows_submission_without_an_optional_reflection(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $this->createProfile($student);
        $activity = $this->createActivity($admin, 50, 'Optional reflection Mass');
        DB::table('activities')->where('id', $activity)->update(['type' => 'Mass']);
        DB::table('activity_requirements')->insert(['activity_id' => $activity, 'name' => 'Real-time attendance photo', 'type' => 'attendance_photo', 'required' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('activity_requirements')->insert(['activity_id' => $activity, 'name' => 'Reflection attachment', 'type' => 'reflection_document', 'required' => false, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($student)->post("/activities/{$activity}/register", [
            'attendance_photo' => UploadedFile::fake()->image('mass.jpg'),
            'capture_source' => 'live_camera',
        ])->assertSessionHas('success');
    }

    public function test_registration_questions_are_required_and_answers_are_saved(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $activity = $this->createActivity($admin, 50);
        $this->createProfile($student);
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
        $this->createProfile($student);
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
        $admin = User::factory()->create(['role' => 'super_admin']);
        $student = User::factory()->create();
        $this->createProfile($student, 'CIT', '4', 'A');
        $payload = ['title' => 'Senior retreat', 'type' => 'Retreat', 'description' => 'A guided Catholic retreat.', 'activity_date' => now()->addDays(20)->toDateString(), 'activity_time' => '08:00', 'activity_end_time' => '16:00', 'venue' => 'Formation Center', 'department' => 'CIT', 'target_year' => '4', 'target_section' => 'A', 'participation_type' => 'Required', 'deadline' => now()->addDays(10)->toDateString(), 'requirements' => '', 'required_documents' => '', 'registration_questions' => '', 'capacity' => 80, 'status' => 'Published'];

        $this->actingAs($admin)->post('/activities', [...$payload, 'intent' => 'preview'])->assertSessionHas('activity_target_preview');
        $token = session('activity_target_preview.token');
        $this->actingAs($admin)->post('/activities', [...$payload, 'intent' => 'create', 'target_confirmation' => $token])->assertSessionHas('success');
        $activity = DB::table('activities')->where('title', 'Senior retreat')->value('id');
        $this->assertDatabaseHas('activity_form_fields', ['activity_id' => $activity, 'label' => 'Emergency contact person', 'section_name' => 'Emergency information']);
        $this->assertDatabaseHas('activity_form_fields', ['activity_id' => $activity, 'label' => 'Is this your first Catholic retreat?', 'field_type' => 'select']);
        $this->assertDatabaseHas('activity_requirements', ['activity_id' => $activity, 'name' => 'Payment receipt', 'type' => 'receipt']);
    }

    public function test_verified_student_submits_formation_form_with_receipt(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'super_admin']);
        $student = User::factory()->create(['email' => 'student@example.test']);
        DB::table('profiles')->insert(['user_id' => $student->id, 'department' => 'Nursing', 'year' => '2', 'section' => 'B', 'contact' => '09170000000', 'status' => 'Verified', 'created_at' => now(), 'updated_at' => now()]);
        $payload = ['title' => 'Nursing recollection', 'type' => 'Recollection', 'description' => 'A guided recollection.', 'activity_date' => now()->addDays(20)->toDateString(), 'activity_time' => '08:00', 'activity_end_time' => '12:00', 'venue' => 'University Chapel', 'department' => 'Nursing', 'target_year' => '2', 'target_section' => 'B', 'participation_type' => 'Required', 'deadline' => now()->addDays(10)->toDateString(), 'requirements' => '', 'required_documents' => '', 'registration_questions' => '', 'capacity' => 80, 'status' => 'Published'];
        $this->actingAs($admin)->post('/activities', [...$payload, 'intent' => 'preview']);
        $token = session('activity_target_preview.token');
        $this->actingAs($admin)->post('/activities', [...$payload, 'intent' => 'create', 'target_confirmation' => $token]);
        $activity = DB::table('activities')->where('title', 'Nursing recollection')->value('id');
        $fields = DB::table('activity_form_fields')->where('activity_id', $activity)->get();
        $answers = [];
        foreach ($fields as $field) {
            if (! $field->required && $field->field_type !== 'checkbox') {
                continue;
            }
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

    public function test_super_admin_can_create_an_account_without_an_email_field(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->post('/accounts', [
            'name' => 'No Email Student',
            'university_id' => '2026-9002',
            'role' => 'student',
            'password' => 'Temporary123!',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'university_id' => '2026-9002',
            'email' => null,
        ]);
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

    public function test_my_attendance_identifies_the_signed_in_staff_member(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Maria Dela Cruz',
            'university_id' => 'CFO-001',
        ]);

        $this->actingAs($admin)->get('/dashboard/my-attendance')
            ->assertOk()
            ->assertSee('Attendance account')
            ->assertSee('This checkpoint will be recorded for')
            ->assertSee('Staff member')
            ->assertSee('Maria Dela Cruz')
            ->assertSee('CFO-001');
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

    public function test_only_super_admin_can_open_the_staff_attendance_monitor(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->get('/dashboard/staff-attendance')->assertNotFound();
        $this->actingAs($superAdmin)->get('/dashboard/staff-attendance')->assertOk();
    }

    public function test_super_admin_can_monitor_present_late_absent_incomplete_and_excused_staff(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        config([
            'app.attendance.workday_start' => '08:00',
            'app.attendance.late_grace_minutes' => 15,
        ]);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $present = User::factory()->admin()->create(['name' => 'Present Staff', 'university_id' => 'CFO-101']);
        $late = User::factory()->admin()->create(['name' => 'Late Staff', 'university_id' => 'CFO-102']);
        $absent = User::factory()->admin()->create(['name' => 'Absent Staff', 'university_id' => 'CFO-103']);
        $incomplete = User::factory()->admin()->create(['name' => 'Incomplete Staff', 'university_id' => 'CFO-104']);
        $excused = User::factory()->admin()->create(['name' => 'Excused Staff', 'university_id' => 'CFO-105']);

        DB::table('staff_attendance')->insert([
            ['user_id' => $present->id, 'work_date' => '2026-10-15', 'time_in' => '2026-10-15 07:55:00', 'time_out' => '2026-10-15 17:00:00', 'status' => 'Present', 'admin_note' => null, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $late->id, 'work_date' => '2026-10-15', 'time_in' => '2026-10-15 08:30:00', 'time_out' => null, 'status' => 'Present', 'admin_note' => null, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $incomplete->id, 'work_date' => '2026-10-15', 'time_in' => '2026-10-15 08:00:00', 'time_out' => null, 'status' => 'Incomplete', 'admin_note' => null, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $excused->id, 'work_date' => '2026-10-15', 'time_in' => null, 'time_out' => null, 'status' => 'Excused', 'admin_note' => 'Approved leave.', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($superAdmin)->get('/dashboard/staff-attendance?attendance_date=2026-10-15');

        $response->assertOk()
            ->assertSee('CFO Staff attendance monitor')
            ->assertSee('Late begins after 8:15 AM')
            ->assertSee('Present Staff')->assertSee('CFO-101')
            ->assertSee('Late Staff')->assertSee('CFO-102')
            ->assertSee('Absent Staff')->assertSee('CFO-103')
            ->assertSee('Incomplete Staff')->assertSee('CFO-104')
            ->assertSee('Excused Staff')->assertSee('CFO-105')
            ->assertSee('data-attendance-state="present"', false)
            ->assertSee('data-attendance-state="late"', false)
            ->assertSee('data-attendance-state="absent"', false)
            ->assertSee('data-attendance-state="incomplete"', false)
            ->assertSee('data-attendance-state="excused"', false);

        Carbon::setTestNow();
    }

    public function test_staff_attendance_monitor_rejects_future_dates(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->get('/dashboard/staff-attendance?attendance_date=2026-10-16')
            ->assertSessionHasErrors('attendance_date');

        Carbon::setTestNow();
    }

    private function createActivity(User $admin, int $capacity, string $title = 'Test activity', string $status = 'Published', string $department = 'CIT'): int
    {
        return DB::table('activities')->insertGetId([
            'title' => $title,
            'type' => 'Retreat',
            'description' => 'Test description',
            'activity_date' => now()->addDays(10)->toDateString(),
            'activity_time' => '08:00',
            'activity_end_time' => '12:00',
            'venue' => 'University Chapel',
            'department' => $department,
            'target_year' => '3',
            'target_section' => 'A',
            'participation_type' => 'Optional',
            'deadline' => now()->addDays(5)->toDateString(),
            'status' => $status,
            'capacity' => $capacity,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProfile(User $student, string $department = 'CIT', string $year = '3', string $section = 'A'): void
    {
        DB::table('profiles')->insert([
            'user_id' => $student->id,
            'department' => $department,
            'year' => $year,
            'section' => $section,
            'contact' => '09170000000',
            'status' => 'Verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function activityPayload(string $title, string $type, string $department, string $year, string $section): array
    {
        return [
            'title' => $title,
            'type' => $type,
            'description' => 'A formation activity for an exact academic group.',
            'activity_date' => now()->addDays(20)->toDateString(),
            'activity_time' => '08:00',
            'activity_end_time' => '12:00',
            'venue' => 'University Chapel',
            'department' => $department,
            'target_year' => $year,
            'target_section' => $section,
            'participation_type' => 'Required',
            'deadline' => now()->addDays(10)->toDateString(),
            'requirements' => '',
            'required_documents' => '',
            'registration_questions' => '',
            'capacity' => 80,
            'status' => 'Published',
        ];
    }
}
