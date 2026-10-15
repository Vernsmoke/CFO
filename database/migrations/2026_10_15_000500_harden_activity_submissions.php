<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_form_fields')
            ->where('label', 'Type your full name as your signature')
            ->delete();

        foreach (DB::table('activities')->where('type', 'Mass')->get() as $activity) {
            DB::table('activity_requirements')->updateOrInsert(
                ['activity_id' => $activity->id, 'type' => 'attendance_photo'],
                ['name' => 'Real-time attendance photo', 'instructions' => 'Capture a live photo with the in-app camera during submission.', 'required' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
            DB::table('activity_requirements')->updateOrInsert(
                ['activity_id' => $activity->id, 'type' => 'reflection_document'],
                ['name' => 'Reflection attachment', 'instructions' => 'Upload a PDF, DOC, or DOCX file up to 5 MB.', 'required' => false, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('activity_requirements')->whereIn('type', ['attendance_photo', 'reflection_document'])->delete();
    }
};
