<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('author_id');
            $table->index(['archived_at', 'created_at'], 'announcements_history_idx');
        });
        Schema::table('activities', fn (Blueprint $table) => $table->index(['status', 'type', 'activity_date'], 'activities_filter_idx'));
        Schema::table('registrations', fn (Blueprint $table) => $table->index(['activity_id', 'status'], 'registrations_filter_idx'));
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at'], 'notifications_read_idx');
            $table->index(['user_id', 'category'], 'notifications_category_idx');
        });

        DB::table('notifications')
            ->where('category', 'facility')
            ->where('action_section', 'facilities')
            ->update(['action_section' => 'activities']);
    }

    public function down(): void
    {
        DB::table('notifications')
            ->where('category', 'facility')
            ->where('action_section', 'activities')
            ->update(['action_section' => 'facilities']);

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_read_idx');
            $table->dropIndex('notifications_category_idx');
        });
        Schema::table('registrations', fn (Blueprint $table) => $table->dropIndex('registrations_filter_idx'));
        Schema::table('activities', fn (Blueprint $table) => $table->dropIndex('activities_filter_idx'));
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('announcements_history_idx');
            $table->dropColumn('archived_at');
        });
    }
};
