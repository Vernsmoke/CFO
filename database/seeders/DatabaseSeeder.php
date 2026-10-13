<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('CFO_DEMO_PASSWORD');
        if (!is_string($password) || trim($password) === '') {
            throw new \RuntimeException('CFO_DEMO_PASSWORD must be set before running the database seeder.');
        }
        if (app()->environment('production') && in_array($password, ['ChangeMe123!', 'choose-a-strong-local-demo-password'], true)) {
            throw new \RuntimeException('CFO_DEMO_PASSWORD must use a private value in production.');
        }
        $student = User::updateOrCreate(['university_id' => '2024-0001'], ['name' => 'Alex Rivera', 'role' => 'student', 'active' => true, 'password' => Hash::make($password)]);
        User::updateOrCreate(['university_id' => '2024-0002'], ['name' => 'Jamie Santos', 'role' => 'student', 'active' => true, 'password' => Hash::make($password)]);
        $admin = User::updateOrCreate(['university_id' => 'CFO-001'], ['name' => 'Maria Dela Cruz', 'role' => 'admin', 'active' => true, 'password' => Hash::make($password)]);
        User::updateOrCreate(['university_id' => 'ADM-001'], ['name' => 'System Administrator', 'role' => 'super_admin', 'active' => true, 'password' => Hash::make($password)]);

        DB::table('profiles')->updateOrInsert(['user_id' => $student->id], ['department' => 'College of Information Technology', 'year' => '3', 'section' => 'A', 'contact' => 'alex@example.invalid', 'status' => 'Verified', 'note' => 'Fictional pilot profile.', 'created_at' => now(), 'updated_at' => now()]);
        if (!DB::table('activities')->exists()) {
            DB::table('activities')->insert([
                ['title' => 'A pause. A prayer. A new beginning.', 'type' => 'Recollection', 'description' => 'Step away from the everyday and make room for reflection, meaningful conversations, and a renewed sense of purpose.', 'activity_date' => '2026-11-06', 'activity_time' => '08:00', 'venue' => 'University Chapel', 'department' => 'All departments', 'deadline' => '2026-11-03', 'requirements' => 'Reflection form; parent or guardian consent', 'required_documents' => json_encode(['Consent form']), 'status' => 'Published', 'capacity' => 120, 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()],
                ['title' => 'Rooted in faith, growing together', 'type' => 'Retreat', 'description' => 'A day of community and guided reflection.', 'activity_date' => '2026-11-20', 'activity_time' => '07:30', 'venue' => 'Formation Center', 'department' => 'College of Information Technology', 'deadline' => '2026-11-15', 'requirements' => 'Consent form and registration form', 'required_documents' => json_encode(['Consent form','Registration form']), 'status' => 'Published', 'capacity' => 80, 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
        if (!DB::table('announcements')->exists()) DB::table('announcements')->insert(['title' => 'A little preparation goes a long way', 'body' => 'Please check your activity requirements before the registration deadline.', 'department' => 'All departments', 'author_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'office_name'], ['value' => 'Christian Formation Office', 'created_at' => now(), 'updated_at' => now()]);
    }
}
