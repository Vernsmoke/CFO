<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\FormationRegistrationFields;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('CFO_DEMO_PASSWORD');
        if (! is_string($password) || trim($password) === '') {
            throw new \RuntimeException('CFO_DEMO_PASSWORD must be set before running the database seeder.');
        }
        if (app()->environment('production') && in_array($password, ['ChangeMe123!', 'choose-a-strong-local-demo-password'], true)) {
            throw new \RuntimeException('CFO_DEMO_PASSWORD must use a private value in production.');
        }
        $student = User::updateOrCreate(['university_id' => '2024-0001'], ['name' => 'Alex Rivera', 'email' => 'alex@example.invalid', 'email_verified_at' => now(), 'terms_accepted_at' => now(), 'role' => 'student', 'student_account_status' => 'Active', 'active' => true, 'password' => Hash::make($password)]);
        User::updateOrCreate(['university_id' => '2024-0002'], ['name' => 'Jamie Santos', 'email' => 'jamie@example.invalid', 'email_verified_at' => now(), 'terms_accepted_at' => now(), 'role' => 'student', 'student_account_status' => 'Active', 'active' => true, 'password' => Hash::make($password)]);
        $admin = User::updateOrCreate(['university_id' => 'CFO-001'], ['name' => 'Maria Dela Cruz', 'role' => 'admin', 'active' => true, 'password' => Hash::make($password)]);
        User::updateOrCreate(['university_id' => 'ADM-001'], ['name' => 'System Administrator', 'role' => 'super_admin', 'active' => true, 'password' => Hash::make($password)]);

        DB::table('profiles')->updateOrInsert(['user_id' => $student->id], ['department' => 'COLLEGE OF INFORMATION TECHNOLOGY', 'course' => 'Bachelor of Science in Information Technology', 'major' => null, 'year' => '3', 'section' => 'A', 'contact' => 'alex@example.invalid', 'status' => 'Verified', 'note' => 'Fictional pilot profile.', 'created_at' => now(), 'updated_at' => now()]);
        if (! DB::table('activities')->exists()) {
            DB::table('activities')->insert([
                ['title' => 'A pause. A prayer. A new beginning.', 'type' => 'Recollection', 'description' => 'Step away from the everyday and make room for reflection, meaningful conversations, and a renewed sense of purpose.', 'activity_date' => '2026-11-06', 'activity_time' => '08:00', 'venue' => 'University Chapel', 'department' => 'Bachelor of Science in Information Technology', 'target_year' => '3', 'target_section' => 'A', 'participation_type' => 'Required', 'deadline' => '2026-11-03', 'requirements' => 'Reflection form; parent or guardian consent', 'required_documents' => json_encode(['Consent form']), 'status' => 'Published', 'capacity' => 120, 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()],
                ['title' => 'Rooted in faith, growing together', 'type' => 'Retreat', 'description' => 'A day of community and guided reflection.', 'activity_date' => '2026-11-20', 'activity_time' => '07:30', 'venue' => 'Formation Center', 'department' => 'Bachelor of Science in Information Technology', 'target_year' => '3', 'target_section' => 'A', 'participation_type' => 'Optional', 'deadline' => '2026-11-15', 'requirements' => 'Consent form and registration form', 'required_documents' => json_encode(['Consent form', 'Registration form']), 'status' => 'Published', 'capacity' => 80, 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
        foreach (DB::table('activities')->whereIn('type', ['Recollection', 'Retreat'])->get() as $activity) {
            foreach (FormationRegistrationFields::forType($activity->type) as $order => $field) {
                DB::table('activity_form_fields')->updateOrInsert(
                    ['activity_id' => $activity->id, 'label' => $field['label']],
                    ['field_type' => $field['type'], 'options' => isset($field['options']) ? json_encode($field['options']) : null, 'section_name' => $field['section'], 'required' => $field['required'], 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]
                );
            }
            DB::table('activity_requirements')->updateOrInsert(
                ['activity_id' => $activity->id, 'type' => 'receipt'],
                ['name' => 'Payment receipt', 'instructions' => 'Upload a PDF, PNG, or JPG file up to 5 MB during submission.', 'required' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]
            );
        }
        if (! DB::table('announcements')->exists()) {
            DB::table('announcements')->insert(['title' => 'A little preparation goes a long way', 'body' => 'Please check your activity requirements before the registration deadline.', 'department' => 'All departments', 'author_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('settings')->updateOrInsert(['key' => 'office_name'], ['value' => 'Christian Formation Office', 'created_at' => now(), 'updated_at' => now()]);
    }
}
