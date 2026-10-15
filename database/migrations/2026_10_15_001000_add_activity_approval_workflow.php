<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->time('activity_end_time')->nullable()->after('activity_time');
            $table->timestamp('submitted_at')->nullable()->after('created_by');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
        });

        DB::table('activities')->whereNull('activity_end_time')->get(['id', 'activity_time'])->each(function (object $activity) {
            $endTime = Carbon::parse($activity->activity_time)->addHours(2);
            DB::table('activities')->where('id', $activity->id)->update([
                'activity_end_time' => (int) substr($activity->activity_time, 0, 2) >= 22 ? '23:59:00' : $endTime->format('H:i:s'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['activity_end_time', 'submitted_at', 'reviewed_at', 'review_note']);
        });
    }
};
