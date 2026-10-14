<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_out')->nullable();
            $table->string('time_in_photo_path')->nullable();
            $table->string('time_out_photo_path')->nullable();
            $table->string('time_in_ip', 45)->nullable();
            $table->string('time_out_ip', 45)->nullable();
            $table->text('time_in_user_agent')->nullable();
            $table->text('time_out_user_agent')->nullable();
            $table->string('status')->default('Present');
            $table->text('admin_note')->nullable();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance');
    }
};
