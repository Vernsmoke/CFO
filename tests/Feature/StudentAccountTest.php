<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetStudentPassword;
use App\Notifications\VerifyStudentEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudentAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_registration_is_available_from_public_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('Create Student Account')->assertSee(route('student.register'));
        $this->get('/login')->assertOk()->assertSee('Create Student Account')->assertSee('Forgot Password?');
        $this->get('/register/student')->assertOk()
            ->assertSee('Create Student Account')
            ->assertSee('Format: 00-00000')
            ->assertDontSee('(Example: **-*****)')
            ->assertDontSee('and this format: L.N., F.N. M.I.')
            ->assertDontSee('24-11263')
            ->assertSee('COLLEGE OF ACCOUNTANCY')
            ->assertSee('Bachelor of Science in Accountancy')
            ->assertSee('Human Resources Management')
            ->assertSee('This email address will be used to log in to the system')
            ->assertSee('Terms and Conditions')
            ->assertSee('Privacy Policy');
    }

    public function test_student_registration_rejects_invalid_identity_and_academic_fields(): void
    {
        $this->post('/register/student', [
            'student_id' => '2026 12',
            'name' => '123 !!!',
            'department' => 'Unknown department',
            'course' => 'Unknown course',
            'year' => '0',
            'section' => 'AA',
            'email' => '@',
            'phone' => '12345',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['student_id', 'name', 'department', 'course', 'year', 'section', 'email', 'phone', 'terms']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_creates_only_a_pending_student_and_sends_verification_email(): void
    {
        Notification::fake();

        $this->post('/register/student', $this->validRegistration([
            'name' => '  DELA-CRUZ,   ANA B.  ',
            'email' => '  ANA.Student@Example.COM ',
            'phone' => '0917 123 4567',
        ]))->assertRedirect(route('student.verification.pending'));

        $user = User::where('university_id', '24-11263')->firstOrFail();
        $this->assertSame('student', $user->role);
        $this->assertFalse($user->active);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Pending Verification', $user->student_account_status);
        $this->assertSame('DELA-CRUZ, ANA B.', $user->name);
        $this->assertSame('ana.student@example.com', $user->email);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'department' => 'COLLEGE OF INFORMATION TECHNOLOGY',
            'course' => 'Bachelor of Science in Information Technology',
            'major' => null,
            'year' => '3',
            'section' => 'A',
            'contact' => '+639171234567',
        ]);
        Notification::assertSentTo($user, VerifyStudentEmail::class);
    }

    public function test_course_must_belong_to_the_selected_department(): void
    {
        $this->post('/register/student', $this->validRegistration([
            'department' => 'COLLEGE OF ACCOUNTANCY',
            'course' => 'Bachelor of Science in Information Technology',
        ]))->assertSessionHasErrors('course');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_major_is_required_and_restricted_for_courses_that_offer_majors(): void
    {
        $businessRegistration = $this->validRegistration([
            'department' => 'COLLEGE OF BUSINESS EDUCATION',
            'course' => 'Bachelor of Science in Business Administration',
        ]);

        $this->post('/register/student', $businessRegistration)->assertSessionHasErrors('major');
        $this->post('/register/student', [...$businessRegistration, 'major' => 'Software Engineering'])->assertSessionHasErrors('major');
        $this->post('/register/student', [...$businessRegistration, 'major' => 'Human Resources Management'])
            ->assertRedirect(route('student.verification.pending'));

        $user = User::where('university_id', '24-11263')->firstOrFail();
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'department' => 'COLLEGE OF BUSINESS EDUCATION',
            'course' => 'Bachelor of Science in Business Administration',
            'major' => 'Human Resources Management',
        ]);
    }

    public function test_names_must_use_uppercase_last_name_first_format_and_passwords_must_match(): void
    {
        $this->post('/register/student', $this->validRegistration([
            'name' => 'Ana Dela Cruz',
            'password_confirmation' => 'different-password',
        ]))->assertSessionHasErrors(['name', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_verification_link_is_single_use_and_activates_the_student(): void
    {
        Notification::fake();
        $url = null;
        $this->post('/register/student', $this->validRegistration());
        $user = User::where('university_id', '24-11263')->firstOrFail();
        Notification::assertSentTo($user, VerifyStudentEmail::class, function (VerifyStudentEmail $notification) use (&$url) {
            $url = $notification->verificationUrl;

            return true;
        });
        $path = parse_url($url, PHP_URL_PATH);

        $this->get($path)->assertOk()->assertSee('Student account successfully created');
        $user->refresh();
        $this->assertTrue($user->active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Active', $user->student_account_status);

        $this->get($path)->assertOk()->assertSee('Verification link invalid or expired');
    }

    public function test_expired_verification_link_cannot_activate_an_account(): void
    {
        $student = User::factory()->create(['active' => false, 'email_verified_at' => null, 'student_account_status' => 'Pending Verification']);
        $token = 'expired-verification-token';
        DB::table('student_email_verifications')->insert(['user_id' => $student->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->subMinute(), 'last_sent_at' => now()->subHour(), 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('student.verification.verify', $token))->assertOk()->assertSee('invalid or expired');
        $this->assertFalse($student->fresh()->active);
        $this->assertNull($student->fresh()->email_verified_at);
    }

    public function test_duplicate_pending_registration_is_not_created_and_can_be_resent_after_cooldown(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->post('/register/student', $this->validRegistration());
        $this->post('/register/student', $this->validRegistration())->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('university_id', '24-11263')->count());

        $this->post('/student-email/resend', ['email' => 'student@example.com'])->assertSessionHasErrors('email');
        Carbon::setTestNow('2026-10-15 10:01:01');
        $this->post('/student-email/resend', ['email' => 'student@example.com'])->assertSessionHas('success');
        Notification::assertSentToTimes(User::where('university_id', '24-11263')->first(), VerifyStudentEmail::class, 2);
        Carbon::setTestNow();
    }

    public function test_pending_students_cannot_login_but_verified_email_login_is_case_insensitive(): void
    {
        $pending = User::factory()->create(['email' => 'pending@example.com', 'active' => false, 'email_verified_at' => null, 'student_account_status' => 'Pending Verification', 'password' => 'password123']);
        $this->post('/login', ['login' => ' PENDING@EXAMPLE.COM ', 'password' => 'password123'])
            ->assertSessionHasErrors('login')
            ->assertSessionHas('pending_verification_email', $pending->email);
        $this->assertGuest();

        $verified = User::factory()->create(['email' => 'verified@example.com', 'password' => 'password123']);
        $this->post('/login', ['login' => ' VERIFIED@EXAMPLE.COM ', 'password' => 'password123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($verified);
    }

    public function test_verified_student_can_reset_password_with_single_use_link(): void
    {
        Notification::fake();
        $student = User::factory()->create(['email' => 'reset@example.com', 'password' => 'old-password']);
        $url = null;

        $this->post('/forgot-password', ['email' => ' RESET@EXAMPLE.COM '])->assertSessionHas('success');
        Notification::assertSentTo($student, ResetStudentPassword::class, function (ResetStudentPassword $notification) use (&$url) {
            $url = $notification->resetUrl;

            return true;
        });
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertSee('Reset password');
        $this->post('/reset-password', ['token' => $token, 'password' => 'new-password123', 'password_confirmation' => 'new-password123'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-password123', $student->fresh()->password));
        $this->post('/reset-password', ['token' => $token, 'password' => 'another-password123', 'password_confirmation' => 'another-password123'])->assertSessionHasErrors('token');
    }

    public function test_student_can_update_contact_but_cannot_change_identity_or_academic_details(): void
    {
        $student = User::factory()->create(['name' => 'ORIGINAL NAME', 'email' => 'original@example.test']);
        DB::table('profiles')->insert([
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

        $this->actingAs($student)->post('/profile', [
            'email' => 'original@example.test',
            'contact' => 'Messenger: Original Student',
            'name' => 'STUDENT CHANGED NAME',
            'department' => 'COLLEGE OF NURSING AND ALLIED HEALTH SCIENCES',
            'course' => 'Bachelor of Science in Nursing',
            'year' => '4',
            'section' => 'Z',
        ])->assertSessionHas('success', 'Contact details saved.');

        $this->assertDatabaseHas('users', ['id' => $student->id, 'name' => 'ORIGINAL NAME']);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $student->id,
            'department' => 'COLLEGE OF INFORMATION TECHNOLOGY',
            'course' => 'Bachelor of Science in Information Technology',
            'year' => '3',
            'section' => 'A',
            'contact' => 'Messenger: Original Student',
            'status' => 'Verified',
        ]);
    }

    public function test_new_email_must_be_verified_before_it_becomes_the_login_email(): void
    {
        Notification::fake();
        $student = User::factory()->create([
            'email' => 'current@example.test',
            'password' => 'password123',
        ]);
        DB::table('profiles')->insert([
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

        $verificationUrl = null;
        $this->actingAs($student)->post('/profile', [
            'email' => 'New.Login@Example.test',
            'contact' => '09171111111',
        ])->assertSessionHas('success');
        $student->refresh();
        $this->assertSame('current@example.test', $student->email);
        $this->assertSame('new.login@example.test', $student->pending_email);
        $this->assertSame('new.login@example.test', $student->routeNotificationForMail());
        $this->actingAs($student)->get('/dashboard/profile')
            ->assertSee('Email verification pending')
            ->assertSee('new.login@example.test')
            ->assertSee('continue signing in with current@example.test');
        $this->post('/register/student', $this->validRegistration([
            'student_id' => '24-99999',
            'email' => 'new.login@example.test',
        ]))->assertSessionHasErrors('email');
        Notification::assertSentTo($student, VerifyStudentEmail::class, function (VerifyStudentEmail $notification) use (&$verificationUrl) {
            $verificationUrl = $notification->verificationUrl;

            return $notification->emailChange;
        });

        $this->post('/logout');
        $this->post('/login', ['login' => 'current@example.test', 'password' => 'password123'])->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->post('/login', ['login' => 'new.login@example.test', 'password' => 'password123'])->assertSessionHasErrors('login');

        $this->get(parse_url($verificationUrl, PHP_URL_PATH))->assertOk()->assertSee('Email address successfully verified');
        $student->refresh();
        $this->assertSame('new.login@example.test', $student->email);
        $this->assertNull($student->pending_email);
        $this->post('/login', ['login' => 'current@example.test', 'password' => 'password123'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => 'NEW.LOGIN@EXAMPLE.TEST', 'password' => 'password123'])->assertRedirect('/dashboard');
    }

    private function validRegistration(array $overrides = []): array
    {
        return array_merge([
            'student_id' => '24-11263',
            'name' => 'DELA CRUZ, ANA B.',
            'department' => 'COLLEGE OF INFORMATION TECHNOLOGY',
            'course' => 'Bachelor of Science in Information Technology',
            'major' => '',
            'year' => '3',
            'section' => 'A',
            'email' => 'student@example.com',
            'phone' => '09171234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ], $overrides);
    }
}
