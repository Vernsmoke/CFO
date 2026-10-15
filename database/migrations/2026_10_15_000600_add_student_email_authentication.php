<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
            $table->string('student_account_status', 30)->nullable()->after('role');
        });

        DB::table('users')
            ->where('role', 'student')
            ->where('active', true)
            ->update(['email_verified_at' => now(), 'student_account_status' => 'Active']);
        DB::table('users')
            ->where('role', 'student')
            ->where('active', false)
            ->update(['student_account_status' => 'Pending Verification']);

        Schema::create('student_email_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            // DATETIME avoids legacy MySQL/MariaDB restrictions on multiple
            // required TIMESTAMP columns without implicit default values.
            $table->dateTime('expires_at');
            $table->dateTime('last_sent_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_password_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_password_resets');
        Schema::dropIfExists('student_email_verifications');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['email_verified_at', 'terms_accepted_at', 'student_account_status']));
    }
};
