<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function profile(Request $request)
    {
        abort_unless($request->user()->role === 'student', 403);
        $data = $request->validate(['department' => ['required','string','max:150'], 'year' => ['required','string','max:20'], 'section' => ['required','string','max:50'], 'contact' => ['required','string','max:150']]);
        DB::table('profiles')->updateOrInsert(['user_id' => $request->user()->id], [...$data, 'status' => 'Pending verification', 'note' => null, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'Submitted profile', $request->user()->university_id);
        return back()->with('success', 'Profile submitted for verification.');
    }

    public function activity(Request $request)
    {
        $this->staff($request);
        $data = $request->validate(['title' => ['required','string','max:255'], 'type' => ['required','string','max:80'], 'description' => ['required','string','max:5000'], 'activity_date' => ['required','date'], 'activity_time' => ['required'], 'venue' => ['required','string','max:255'], 'department' => ['required','string','max:150'], 'deadline' => ['required','date','before_or_equal:activity_date'], 'requirements' => ['nullable','string','max:5000'], 'required_documents' => ['nullable','string','max:2000'], 'capacity' => ['required','integer','min:1','max:10000'], 'status' => ['required', Rule::in(['Draft','Published','Postponed','Cancelled','Archived'])]]);
        $documents = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['required_documents'] ?? '')))); unset($data['required_documents']);
        $id = DB::table('activities')->insertGetId([...$data, 'required_documents' => json_encode($documents), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'Created activity', (string) $id, $data['title']);
        return back()->with('success', 'Activity created.');
    }

    public function register(Request $request, int $activity)
    {
        abort_unless($request->user()->role === 'student', 403);
        $result = DB::transaction(function () use ($request, $activity) {
            // Lock the activity so concurrent registrations cannot all pass the capacity check.
            $record = DB::table('activities')->where('id', $activity)->lockForUpdate()->first();
            abort_unless($record && $record->status === 'Published', 404);

            if (now()->toDateString() > $record->deadline) {
                return ['error' => 'The registration deadline has passed.'];
            }

            $existing = DB::table('registrations')
                ->where('activity_id', $activity)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($existing && $existing->status !== 'Withdrawn') {
                return ['already_registered' => true];
            }

            $count = DB::table('registrations')
                ->where('activity_id', $activity)
                ->whereNot('status', 'Withdrawn')
                ->count();

            if ($count >= $record->capacity) {
                return ['error' => 'This activity is already full.'];
            }

            if ($existing) {
                DB::table('registrations')->where('id', $existing->id)->update([
                    'status' => 'Submitted',
                    'note' => null,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('registrations')->insert([
                    'user_id' => $request->user()->id,
                    'activity_id' => $activity,
                    'status' => 'Submitted',
                    'note' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return ['title' => $record->title];
        });

        if (isset($result['error'])) return back()->withErrors(['registration' => $result['error']]);
        if (isset($result['already_registered'])) return back()->with('success', 'You are already registered for this activity.');

        $this->audit($request, 'Registered for activity', (string) $activity, $result['title']);
        return back()->with('success', 'Registration submitted.');
    }

    public function reviewRegistration(Request $request, int $registration)
    {
        $this->staff($request); $data = $request->validate(['status' => ['required', Rule::in(['Under review','Requires correction','Accepted','Withdrawn'])], 'note' => ['nullable','string','max:2000']]);
        $record = DB::table('registrations')->find($registration); abort_unless($record, 404);
        DB::table('registrations')->where('id', $registration)->update([...$data, 'updated_at' => now()]);
        $this->notify($record->user_id, "Your registration status is now {$data['status']}."); $this->audit($request, 'Reviewed registration', (string) $registration, $data['status']);
        return back()->with('success', 'Registration updated.');
    }

    public function upload(Request $request, int $registration)
    {
        abort_unless($request->user()->role === 'student', 403);
        $record = DB::table('registrations')->where('id', $registration)->where('user_id', $request->user()->id)->first(); abort_unless($record, 404);
        $data = $request->validate(['document' => ['required','file','mimes:pdf,png,jpg,jpeg','max:5120'], 'requirement' => ['nullable','string','max:255']]);
        $file = $data['document']; $path = $file->store('student-documents', 'local');
        DB::table('documents')->insert(['registration_id' => $registration, 'user_id' => $request->user()->id, 'original_name' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'requirement' => $data['requirement'] ?? null, 'status' => 'Pending review', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('registrations')->where('id', $registration)->update(['status' => 'Under review', 'updated_at' => now()]);
        $this->audit($request, 'Uploaded document', (string) $registration, $file->getClientOriginalName());
        return back()->with('success', 'Document uploaded securely.');
    }

    public function reviewDocument(Request $request, int $document)
    {
        $this->staff($request); $data = $request->validate(['status' => ['required', Rule::in(['Accepted','Requires correction'])], 'note' => ['nullable','string','max:2000']]);
        $record = DB::table('documents')->find($document); abort_unless($record, 404);
        DB::table('documents')->where('id', $document)->update([...$data, 'reviewed_at' => now(), 'updated_at' => now()]);
        $this->notify($record->user_id, "A submitted document was marked {$data['status']}."); $this->audit($request, 'Reviewed document', (string) $document, $data['status']);
        return back()->with('success', 'Document review saved.');
    }

    public function attendance(Request $request, int $registration)
    {
        $this->staff($request); $data = $request->validate(['checkpoint' => ['required', Rule::in(['in','out'])]]);
        $column = $data['checkpoint'] === 'in' ? 'time_in' : 'time_out';
        DB::table('attendance')->updateOrInsert(['registration_id' => $registration], [$column => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'Recorded attendance', (string) $registration, $data['checkpoint']);
        return back()->with('success', 'Attendance recorded.');
    }

    public function facility(Request $request)
    {
        $this->staff($request); $data = $request->validate(['facility' => ['required','string','max:150'], 'purpose' => ['required','string','max:3000'], 'starts_at' => ['required','date'], 'ends_at' => ['required','date','after:starts_at'], 'participants' => ['required','integer','min:1','max:10000']]);
        DB::table('facility_requests')->insert([...$data, 'user_id' => $request->user()->id, 'status' => 'Pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'Requested facility', $data['facility']); return back()->with('success', 'Facility request submitted.');
    }

    public function decideFacility(Request $request, int $facility)
    {
        $this->staff($request); $data = $request->validate(['status' => ['required', Rule::in(['Approved','Rejected','Returned for correction'])], 'note' => ['nullable','string','max:2000']]);
        $record = DB::table('facility_requests')->find($facility); abort_unless($record, 404);
        if ($data['status'] === 'Approved') {
            $conflict = DB::table('facility_requests')->where('facility', $record->facility)->where('status', 'Approved')->where('id', '!=', $facility)->where('starts_at', '<', $record->ends_at)->where('ends_at', '>', $record->starts_at)->exists();
            if ($conflict) return back()->withErrors(['facility' => 'This approved time overlaps another booking.']);
        }
        DB::table('facility_requests')->where('id', $facility)->update([...$data, 'updated_at' => now()]);
        $this->notify($record->user_id, "Your {$record->facility} request is now {$data['status']}."); $this->audit($request, 'Reviewed facility request', (string) $facility, $data['status']);
        return back()->with('success', 'Facility request updated.');
    }

    public function announcement(Request $request)
    {
        $this->staff($request); $data = $request->validate(['title' => ['required','string','max:255'], 'body' => ['required','string','max:5000'], 'department' => ['required','string','max:150']]);
        DB::table('announcements')->insert([...$data, 'author_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'Published announcement', $data['title']); return back()->with('success', 'Announcement published.');
    }

    public function readNotifications(Request $request)
    {
        DB::table('notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Notifications marked as read.');
    }

    public function account(Request $request, int $user)
    {
        abort_unless($request->user()->isSuperAdmin(), 403); abort_if($request->user()->id === $user, 422, 'You cannot change your own access here.');
        $data = $request->validate(['role' => ['required', Rule::in(['student','admin','super_admin'])], 'active' => ['required','boolean'], 'password' => ['nullable','string','min:10','max:128']]);
        $update = ['role' => $data['role'], 'active' => $data['active'], 'updated_at' => now()]; if (!empty($data['password'])) $update['password'] = Hash::make($data['password']);
        DB::table('users')->where('id', $user)->update($update); DB::table('sessions')->where('user_id', $user)->delete();
        $this->audit($request, 'Updated account', (string) $user, $data['role']); return back()->with('success', 'Account updated and existing sessions revoked.');
    }

    private function staff(Request $request): void { abort_unless($request->user()->isStaff(), 403); }
    private function notify(int $userId, string $message): void { DB::table('notifications')->insert(['user_id' => $userId, 'message' => $message, 'created_at' => now(), 'updated_at' => now()]); }
    private function audit(Request $request, string $action, string $target, ?string $detail = null): void { DB::table('audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'target' => $target, 'detail' => $detail, 'created_at' => now(), 'updated_at' => now()]); }
}
