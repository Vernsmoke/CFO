<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('department'); $table->string('year', 20); $table->string('section', 50);
            $table->string('contact'); $table->string('status')->default('Not submitted');
            $table->text('note')->nullable(); $table->timestamps();
        });
        Schema::create('activities', function (Blueprint $table) {
            $table->id(); $table->string('title'); $table->string('type'); $table->text('description');
            $table->date('activity_date'); $table->time('activity_time'); $table->string('venue');
            $table->string('department')->default('All departments'); $table->date('deadline');
            $table->text('requirements')->nullable(); $table->json('required_documents')->nullable();
            $table->string('status')->default('Draft'); $table->unsignedInteger('capacity')->default(100);
            $table->foreignId('created_by')->constrained('users'); $table->timestamps();
        });
        Schema::create('registrations', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('Submitted'); $table->text('note')->nullable();
            $table->string('group_name')->nullable(); $table->string('facilitator')->nullable(); $table->timestamps();
            $table->unique(['user_id', 'activity_id']);
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id(); $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('original_name');
            $table->string('path'); $table->string('mime', 100); $table->string('requirement')->nullable();
            $table->string('status')->default('Pending review'); $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->nullable(); $table->timestamps();
        });
        Schema::create('attendance', function (Blueprint $table) {
            $table->id(); $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('time_in')->nullable(); $table->timestamp('time_out')->nullable(); $table->timestamps();
        });
        Schema::create('facility_requests', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('facility'); $table->text('purpose'); $table->dateTime('starts_at');
            $table->dateTime('ends_at'); $table->unsignedInteger('participants');
            $table->string('status')->default('Pending'); $table->text('note')->nullable(); $table->timestamps();
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id(); $table->string('title'); $table->text('body');
            $table->string('department')->default('All departments');
            $table->foreignId('author_id')->constrained('users'); $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message'); $table->timestamp('read_at')->nullable(); $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); $table->string('target'); $table->text('detail')->nullable(); $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary(); $table->text('value')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['settings','audit_logs','notifications','announcements','facility_requests','attendance','documents','registrations','activities','profiles'] as $table) Schema::dropIfExists($table);
    }
};
