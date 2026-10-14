<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('name');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->string('target_year', 20)->nullable()->after('department');
            $table->string('target_section', 50)->nullable()->after('target_year');
            $table->string('participation_type', 20)->default('Optional')->after('target_section');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->string('target_year', 20)->nullable()->after('department');
            $table->string('target_section', 50)->nullable()->after('target_year');
        });

        Schema::create('activity_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('field_type', 30)->default('text');
            $table->boolean('required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('registration_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('activity_form_fields')->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'field_id']);
        });

        Schema::create('activity_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30);
            $table->text('instructions')->nullable();
            $table->boolean('required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('requirement_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained('activity_requirements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('text_content')->nullable();
            $table->string('original_name')->nullable();
            $table->string('path')->nullable();
            $table->string('mime', 100)->nullable();
            $table->string('status')->default('Submitted');
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['registration_id', 'requirement_id']);
        });

        foreach (DB::table('activities')->select('id', 'required_documents')->get() as $activity) {
            $documents = json_decode($activity->required_documents ?: '[]', true) ?: [];
            foreach (array_values($documents) as $order => $name) DB::table('activity_requirements')->insert(['activity_id' => $activity->id, 'name' => $name, 'type' => 'document', 'required' => true, 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_submissions');
        Schema::dropIfExists('activity_requirements');
        Schema::dropIfExists('registration_answers');
        Schema::dropIfExists('activity_form_fields');
        Schema::table('announcements', fn (Blueprint $table) => $table->dropColumn(['target_year', 'target_section']));
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn(['target_year', 'target_section', 'participation_type']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('email'));
    }
};
