<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyStudentEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentEmailVerificationService
{
    public function send(User $user): void
    {
        $token = Str::random(64);
        $minutes = config('student_registration.verification_expire_minutes');

        DB::table('student_email_verifications')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addMinutes($minutes),
                'last_sent_at' => now(),
                'used_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $user->notify(new VerifyStudentEmail(
            route('student.verification.verify', $token),
            $minutes,
            filled($user->pending_email)
        ));
    }
}
