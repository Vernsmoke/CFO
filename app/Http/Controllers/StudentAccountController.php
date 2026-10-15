<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ResetStudentPassword;
use App\Services\StudentEmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentAccountController extends Controller
{
    public function __construct(private StudentEmailVerificationService $emailVerification) {}

    public function create()
    {
        return view('auth.student-register', ['catalog' => $this->catalog()]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'student_id' => trim((string) $request->input('student_id')),
            'name' => preg_replace('/\s+/u', ' ', trim((string) $request->input('name'))),
            'department' => trim((string) $request->input('department')),
            'course' => trim((string) $request->input('course')),
            'major' => trim((string) $request->input('major')),
            'year' => trim((string) $request->input('year')),
            'section' => trim((string) $request->input('section')),
            'email' => Str::lower(trim((string) $request->input('email'))),
            'phone' => $this->normalizePhone((string) $request->input('phone')),
        ]);

        $existingById = User::where('university_id', $request->input('student_id'))->first();
        $existingByEmail = User::where(function ($query) use ($request) {
            $email = $request->input('email');
            $query->whereRaw('LOWER(email) = ?', [$email])
                ->orWhereRaw('LOWER(pending_email) = ?', [$email]);
        })->first();
        $pending = collect([$existingById, $existingByEmail])->filter()->first(fn (User $user) => $user->role === 'student' && ! $user->email_verified_at);
        if ($pending) {
            return back()->withInput($request->except(['password', 'password_confirmation']))
                ->with('pending_registration', true)
                ->withErrors(['email' => 'A pending student registration already exists. Use Resend Verification Email instead of creating a duplicate.']);
        }

        $catalog = $this->catalog();
        $department = $request->input('department');
        $course = $request->input('course');
        $majors = $catalog[$department][$course] ?? [];
        $data = $request->validate([
            'student_id' => ['required', 'regex:/^\d{2}-\d{5}$/', Rule::unique('users', 'university_id')],
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\p{Lu}][\p{Lu}\p{M}\s.\'’\-]+,\s*[\p{Lu}][\p{Lu}\p{M}\s.\'’\-]+$/u',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (mb_strtoupper((string) $value, 'UTF-8') !== $value) {
                        $fail('Enter the student name using uppercase letters.');
                    }
                },
            ],
            'department' => ['required', Rule::in(array_keys($catalog))],
            'course' => ['required', Rule::in(array_keys($catalog[$department] ?? []))],
            'major' => [
                Rule::requiredIf($majors !== []),
                'nullable',
                'string',
                'max:150',
                function (string $attribute, mixed $value, \Closure $fail) use ($majors) {
                    if ($majors === [] && filled($value)) {
                        $fail('The selected course does not have a major option.');
                    } elseif ($majors !== [] && ! in_array($value, $majors, true)) {
                        $fail('Select an official major for the selected course.');
                    }
                },
            ],
            'year' => ['required', 'regex:/^[1-9]$/'],
            'section' => ['required', 'regex:/^[A-Z]$/'],
            'email' => [
                'required',
                'email:rfc',
                'regex:/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $email = Str::lower((string) $value);
                    if (User::where(fn ($query) => $query
                        ->whereRaw('LOWER(email) = ?', [$email])
                        ->orWhereRaw('LOWER(pending_email) = ?', [$email]))->exists()) {
                        $fail('An account already uses this email address.');
                    }
                },
            ],
            'phone' => ['required', 'regex:/^\+639\d{9}$/'],
            'password' => ['required', 'string', 'min:10', 'max:128', 'confirmed'],
            'terms' => ['accepted'],
        ], [
            'student_id.regex' => 'The Student ID must use the required 00-00000 format.',
            'name.regex' => 'Enter a valid uppercase student name with the last name before the comma.',
            'department.in' => 'Select an official department from the list.',
            'course.in' => 'Select a course under the chosen department.',
            'major.required' => 'Select a major for the chosen course.',
            'year.regex' => 'The year level must be one digit from 1 to 9.',
            'section.regex' => 'The section must be one uppercase letter from A to Z.',
            'email.email' => 'Enter a complete email address with a valid username and domain.',
            'email.regex' => 'Enter a complete email address with a valid domain, such as student@example.com.',
            'phone.regex' => 'Enter a Philippine mobile number in the format 09** *** **** or +63 9** *** ****.',
            'password.confirmed' => 'The password and confirmation password must match.',
            'terms.accepted' => 'You must agree to the Terms and Conditions and Privacy Policy.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'university_id' => $data['student_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => 'student',
                'active' => false,
                'student_account_status' => 'Pending Verification',
                'terms_accepted_at' => now(),
                'password' => $data['password'],
            ]);
            DB::table('profiles')->insert([
                'user_id' => $user->id,
                'department' => $data['department'],
                'course' => $data['course'],
                'major' => $data['major'] ?: null,
                'year' => $data['year'],
                'section' => $data['section'],
                'contact' => $data['phone'],
                'status' => 'Pending verification',
                'note' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        $this->emailVerification->send($user);

        return redirect()->route('student.verification.pending')
            ->with('verification_email', $user->email)
            ->with('success', 'Registration received. Check your email to verify and activate your student account.');
    }

    public function pending()
    {
        return view('auth.student-verification-pending');
    }

    public function resend(Request $request)
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $request->merge(['email' => $email]);
        $request->validate(['email' => ['required', 'email:rfc', 'regex:/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', 'max:255']]);

        $key = 'student-verification-resend|'.$email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withInput()->withErrors(['email' => 'Please wait '.RateLimiter::availableIn($key).' seconds before requesting another verification email.']);
        }

        $user = User::where('role', 'student')
            ->where(function ($query) use ($email) {
                $query->whereRaw('LOWER(email) = ?', [$email])
                    ->orWhereRaw('LOWER(pending_email) = ?', [$email]);
            })
            ->first();
        $canResend = $user && (! $user->email_verified_at || Str::lower((string) $user->pending_email) === $email);
        if ($canResend) {
            $record = DB::table('student_email_verifications')->where('user_id', $user->id)->first();
            $cooldown = config('student_registration.resend_cooldown_seconds');
            if ($record && now()->diffInSeconds($record->last_sent_at, true) < $cooldown) {
                $remaining = max(1, $cooldown - now()->diffInSeconds($record->last_sent_at, true));

                return back()->withInput()->withErrors(['email' => "Please wait {$remaining} seconds before requesting another verification email."]);
            }
            $this->emailVerification->send($user);
        }
        RateLimiter::hit($key, config('student_registration.resend_cooldown_seconds'));

        return back()->with('verification_email', $email)->with('success', 'If that address is awaiting verification, a new verification message has been sent.');
    }

    public function verify(string $token)
    {
        $result = DB::transaction(function () use ($token) {
            $record = DB::table('student_email_verifications')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $record || $record->used_at || now()->greaterThanOrEqualTo($record->expires_at)) {
                return null;
            }
            $user = User::where('id', $record->user_id)->where('role', 'student')->lockForUpdate()->first();
            if (! $user || ($user->email_verified_at && ! $user->pending_email)) {
                return null;
            }
            $emailChanged = filled($user->pending_email);
            $user->forceFill([
                'email' => $emailChanged ? $user->pending_email : $user->email,
                'pending_email' => null,
                'email_verified_at' => now(),
                'active' => true,
                'student_account_status' => 'Active',
            ])->save();
            DB::table('student_email_verifications')->where('id', $record->id)->update(['used_at' => now(), 'updated_at' => now()]);

            return $emailChanged ? 'email_changed' : 'registered';
        });

        return view('auth.student-verification-result', [
            'verified' => $result !== null,
            'emailChanged' => $result === 'email_changed',
        ]);
    }

    public function forgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordReset(Request $request)
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $request->merge(['email' => $email]);
        $request->validate(['email' => ['required', 'email:rfc', 'regex:/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', 'max:255']]);
        $key = 'student-password-reset|'.$email.'|'.$request->ip();
        if (! RateLimiter::tooManyAttempts($key, 1)) {
            $user = User::where('role', 'student')->where('active', true)->whereNotNull('email_verified_at')->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user) {
                $this->sendResetLink($user);
            }
            RateLimiter::hit($key, 60);
        }

        return back()->with('success', 'If a verified student account matches that email, password recovery instructions have been sent.');
    }

    public function resetPasswordForm(string $token)
    {
        $valid = DB::table('student_password_resets')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->exists();

        return view('auth.reset-password', compact('token', 'valid'));
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'max:128', 'confirmed'],
        ]);

        $reset = DB::transaction(function () use ($data) {
            $record = DB::table('student_password_resets')->where('token_hash', hash('sha256', $data['token']))->lockForUpdate()->first();
            if (! $record || $record->used_at || now()->greaterThanOrEqualTo($record->expires_at)) {
                return false;
            }
            $user = User::where('id', $record->user_id)->where('role', 'student')->where('active', true)->whereNotNull('email_verified_at')->lockForUpdate()->first();
            if (! $user) {
                return false;
            }
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
            DB::table('student_password_resets')->where('id', $record->id)->update(['used_at' => now(), 'updated_at' => now()]);
            DB::table('sessions')->where('user_id', $user->id)->delete();

            return true;
        });

        if (! $reset) {
            throw ValidationException::withMessages(['token' => 'This password reset link is invalid, expired, or already used.']);
        }

        return redirect()->route('login')->with('success', 'Your password has been updated. You can now sign in.');
    }

    private function sendResetLink(User $user): void
    {
        DB::table('student_password_resets')->where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);
        $token = Str::random(64);
        $minutes = config('student_registration.password_reset_expire_minutes');
        DB::table('student_password_resets')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($minutes),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->notify(new ResetStudentPassword(route('student.password.reset.form', $token), $minutes));
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s()\-]/', '', trim($phone));
        if (str_starts_with($phone, '09')) {
            return '+63'.substr($phone, 1);
        }

        return $phone;
    }

    private function catalog(): array
    {
        $catalog = config('student_registration.catalog', []);
        ksort($catalog, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($catalog as &$courses) {
            ksort($courses, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($courses as &$majors) {
                sort($majors, SORT_NATURAL | SORT_FLAG_CASE);
            }
            unset($majors);
        }
        unset($courses);

        return $catalog;
    }
}
