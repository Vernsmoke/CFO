<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function profile(Request $request)
    {
        abort_unless($request->user()->role === 'student', 403);
        $data = $request->validate(['email' => ['required','email','max:255', Rule::unique('users')->ignore($request->user()->id)], 'department' => ['required','string','max:150'], 'year' => ['required','string','max:20'], 'section' => ['required','string','max:50'], 'contact' => ['required','string','max:150']]);
        DB::table('users')->where('id', $request->user()->id)->update(['email' => $data['email'], 'updated_at' => now()]);
        unset($data['email']);
        $existing = DB::table('profiles')->where('user_id', $request->user()->id)->exists();
        $values = [...$data, 'status' => 'Pending verification', 'note' => null, 'updated_at' => now()];
        if ($existing) DB::table('profiles')->where('user_id', $request->user()->id)->update($values);
        else DB::table('profiles')->insert(['user_id' => $request->user()->id, ...$values, 'created_at' => now()]);
        $this->audit($request, 'Submitted profile', $request->user()->university_id);
        return back()->with('success', 'Profile submitted for verification.');
    }

    public function reviewProfile(Request $request, int $profile)
    {
        $this->staff($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['Verified','Requires correction'])],
            'note' => ['nullable','string','max:2000', Rule::requiredIf(fn () => $request->input('status') === 'Requires correction')],
        ]);
        $record = DB::table('profiles')->find($profile); abort_unless($record, 404);
        DB::table('profiles')->where('id', $profile)->update([...$data, 'updated_at' => now()]);
        $this->notify($record->user_id, "Your profile status is now {$data['status']}.");
        $this->audit($request, 'Reviewed profile', (string) $profile, $data['status']);
        return back()->with('success', 'Profile review saved.');
    }

    public function activity(Request $request)
    {
        $this->staff($request);
        $data = $request->validate(['title' => ['required','string','max:255'], 'type' => ['required','string','max:80'], 'description' => ['required','string','max:5000'], 'activity_date' => ['required','date'], 'activity_time' => ['required'], 'venue' => ['required','string','max:255'], 'department' => ['required','string','max:150'], 'target_year' => ['nullable','string','max:20'], 'target_section' => ['nullable','string','max:50'], 'participation_type' => ['required', Rule::in(['Required','Optional'])], 'deadline' => ['required','date','before_or_equal:activity_date'], 'requirements' => ['nullable','string','max:5000'], 'required_documents' => ['nullable','string','max:2000'], 'registration_questions' => ['nullable','string','max:5000'], 'requires_receipt' => ['nullable','boolean'], 'requires_reflection' => ['nullable','boolean'], 'requires_evidence' => ['nullable','boolean'], 'capacity' => ['required','integer','min:1','max:10000'], 'status' => ['required', Rule::in(['Draft','Published','Postponed','Cancelled','Archived'])]]);
        $documents = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['required_documents'] ?? ''))));
        $questions = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['registration_questions'] ?? ''))));
        $standardFields = $this->formationRegistrationFields($data['type']);
        $requirementFlags = ['Payment receipt' => 'requires_receipt', 'Reflection' => 'requires_reflection', 'Attendance evidence' => 'requires_evidence'];
        foreach (['required_documents','registration_questions','requires_receipt','requires_reflection','requires_evidence'] as $key) unset($data[$key]);
        $data['target_year'] = $data['target_year'] ?: null; $data['target_section'] = $data['target_section'] ?: null;
        $id = DB::transaction(function () use ($request, $data, $documents, $questions, $requirementFlags, $standardFields) {
            $id = DB::table('activities')->insertGetId([...$data, 'required_documents' => json_encode($documents), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($standardFields as $order => $field) DB::table('activity_form_fields')->insert(['activity_id' => $id, 'label' => $field['label'], 'field_type' => $field['type'], 'options' => isset($field['options']) ? json_encode($field['options']) : null, 'section_name' => $field['section'], 'required' => $field['required'], 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($questions as $index => $label) DB::table('activity_form_fields')->insert(['activity_id' => $id, 'label' => $label, 'field_type' => 'text', 'section_name' => 'Additional information', 'required' => true, 'sort_order' => count($standardFields) + $index, 'created_at' => now(), 'updated_at' => now()]);
            $order = 0;
            foreach ($requirementFlags as $name => $flag) if ($request->boolean($flag) || ($flag === 'requires_receipt' && $standardFields)) DB::table('activity_requirements')->insert(['activity_id' => $id, 'name' => $name, 'type' => $flag === 'requires_reflection' ? 'reflection' : ($flag === 'requires_receipt' ? 'receipt' : 'evidence'), 'instructions' => $flag === 'requires_receipt' && $standardFields ? 'Required during registration.' : null, 'required' => true, 'sort_order' => $order++, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($documents as $name) DB::table('activity_requirements')->insert(['activity_id' => $id, 'name' => $name, 'type' => 'document', 'required' => true, 'sort_order' => $order++, 'created_at' => now(), 'updated_at' => now()]);
            return $id;
        });
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

            $fields = DB::table('activity_form_fields')->where('activity_id', $activity)->orderBy('sort_order')->get();
            if ($fields->whereNotNull('section_name')->isNotEmpty()) {
                $verifiedProfile = DB::table('profiles')->where('user_id', $request->user()->id)->where('status', 'Verified')->exists();
                if (!$verifiedProfile) return ['error' => 'Your student profile must be verified before you can register for this activity.'];
            }
            $rules = [];
            foreach ($fields as $field) {
                $fieldRules = match ($field->field_type) {
                    'number' => ['integer','min:1','max:120'],
                    'checkbox' => ['accepted'],
                    'select' => ['string', Rule::in(json_decode($field->options ?: '[]', true))],
                    default => ['string','max:3000'],
                };
                $rules["answers.{$field->id}"] = [$field->required ? 'required' : 'nullable', ...$fieldRules];
            }
            $receiptRequirement = DB::table('activity_requirements')->where('activity_id', $activity)->where('type', 'receipt')->first();
            if ($receiptRequirement) $rules['receipt'] = ['required','file','mimes:png,jpg,jpeg','max:5120'];
            $validated = $request->validate($rules);
            $answers = $validated['answers'] ?? [];

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
                $registrationId = DB::table('registrations')->insertGetId([
                    'user_id' => $request->user()->id,
                    'activity_id' => $activity,
                    'status' => 'Submitted',
                    'note' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $registrationId ??= $existing->id;
            foreach ($fields as $field) DB::table('registration_answers')->updateOrInsert(['registration_id' => $registrationId, 'field_id' => $field->id], ['answer' => $field->field_type === 'checkbox' ? (isset($answers[$field->id]) ? 'Yes' : 'No') : ($answers[$field->id] ?? null), 'created_at' => now(), 'updated_at' => now()]);
            if ($receiptRequirement) {
                $file = $validated['receipt'];
                $existingReceipt = DB::table('requirement_submissions')->where('registration_id', $registrationId)->where('requirement_id', $receiptRequirement->id)->first();
                if ($existingReceipt?->path) Storage::disk('local')->delete($existingReceipt->path);
                $values = ['user_id' => $request->user()->id, 'original_name' => $file->getClientOriginalName(), 'path' => $file->store('requirement-submissions', 'local'), 'mime' => $file->getMimeType(), 'text_content' => null, 'status' => 'Submitted', 'note' => null, 'reviewed_at' => null, 'updated_at' => now()];
                if ($existingReceipt) DB::table('requirement_submissions')->where('id', $existingReceipt->id)->update($values);
                else DB::table('requirement_submissions')->insert(['registration_id' => $registrationId, 'requirement_id' => $receiptRequirement->id, ...$values, 'created_at' => now()]);
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

    public function submitRequirement(Request $request, int $registration, int $requirement)
    {
        abort_unless($request->user()->role === 'student', 403);
        $record = DB::table('registrations')->where('id', $registration)->where('user_id', $request->user()->id)->first(); abort_unless($record, 404);
        $required = DB::table('activity_requirements')->where('id', $requirement)->where('activity_id', $record->activity_id)->first(); abort_unless($required, 404);
        $data = $required->type === 'reflection'
            ? $request->validate(['text_content' => ['required','string','max:10000']])
            : $request->validate(['submission_file' => ['required','file','mimes:pdf,png,jpg,jpeg','max:5120']]);
        $existing = DB::table('requirement_submissions')->where('registration_id', $registration)->where('requirement_id', $requirement)->first();
        $values = ['user_id' => $request->user()->id, 'text_content' => $data['text_content'] ?? null, 'status' => 'Submitted', 'note' => null, 'reviewed_at' => null, 'updated_at' => now()];
        if (isset($data['submission_file'])) {
            $file = $data['submission_file']; $values += ['original_name' => $file->getClientOriginalName(), 'path' => $file->store('requirement-submissions', 'local'), 'mime' => $file->getMimeType()];
            if ($existing?->path) Storage::disk('local')->delete($existing->path);
        }
        if ($existing) DB::table('requirement_submissions')->where('id', $existing->id)->update($values);
        else DB::table('requirement_submissions')->insert(['registration_id' => $registration, 'requirement_id' => $requirement, ...$values, 'created_at' => now()]);
        DB::table('registrations')->where('id', $registration)->where('status', 'Requires correction')->update(['status' => 'Under review', 'updated_at' => now()]);
        $this->audit($request, 'Submitted activity requirement', (string) $registration, $required->name);
        return back()->with('success', $required->name.' submitted for review.');
    }

    public function reviewRequirementSubmission(Request $request, int $submission)
    {
        $this->staff($request);
        $data = $request->validate(['status' => ['required', Rule::in(['Accepted','Requires correction'])], 'note' => ['nullable','string','max:2000', Rule::requiredIf(fn () => $request->input('status') === 'Requires correction')]]);
        $record = DB::table('requirement_submissions')->find($submission); abort_unless($record, 404);
        DB::table('requirement_submissions')->where('id', $submission)->update([...$data, 'reviewed_at' => now(), 'updated_at' => now()]);
        $this->notify($record->user_id, "An activity requirement was marked {$data['status']}.");
        $this->audit($request, 'Reviewed activity requirement', (string) $submission, $data['status']);
        return back()->with('success', 'Requirement review saved.');
    }

    public function attendance(Request $request, int $registration)
    {
        $this->staff($request); $data = $request->validate(['checkpoint' => ['required', Rule::in(['in','out'])]]);
        $column = $data['checkpoint'] === 'in' ? 'time_in' : 'time_out';
        $result = DB::transaction(function () use ($registration, $column) {
            $record = DB::table('registrations')->where('id', $registration)->lockForUpdate()->first();
            abort_unless($record, 404);
            if ($record->status !== 'Accepted') return ['error' => 'Attendance can only be recorded for accepted registrations.'];

            $entry = DB::table('attendance')->where('registration_id', $registration)->lockForUpdate()->first();
            if ($column === 'time_out' && !$entry?->time_in) return ['error' => 'Record time in before time out.'];
            if ($entry?->{$column}) return ['existing' => true];

            if ($entry) {
                DB::table('attendance')->where('id', $entry->id)->update([$column => now(), 'updated_at' => now()]);
            } else {
                DB::table('attendance')->insert(['registration_id' => $registration, 'time_in' => now(), 'time_out' => null, 'created_at' => now(), 'updated_at' => now()]);
            }
            return ['saved' => true];
        });
        if (isset($result['error'])) return back()->withErrors(['attendance' => $result['error']]);
        if (isset($result['existing'])) return back()->with('success', 'Attendance was already recorded.');
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
        $this->staff($request); $data = $request->validate(['title' => ['required','string','max:255'], 'body' => ['required','string','max:5000'], 'department' => ['required','string','max:150'], 'target_year' => ['nullable','string','max:20'], 'target_section' => ['nullable','string','max:50']]);
        $data['target_year'] = $data['target_year'] ?: null; $data['target_section'] = $data['target_section'] ?: null;
        DB::table('announcements')->insert([...$data, 'author_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $students = DB::table('users as u')->leftJoin('profiles as p', 'p.user_id', '=', 'u.id')->where('u.role', 'student')->where('u.active', true)
            ->when($data['department'] !== 'All departments', fn ($query) => $query->where('p.department', $data['department']))
            ->when($data['target_year'], fn ($query, $year) => $query->where('p.year', $year))
            ->when($data['target_section'], fn ($query, $section) => $query->where('p.section', $section))->pluck('u.id');
        foreach ($students as $studentId) $this->notify($studentId, 'New announcement: '.$data['title']);
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
        abort_unless(DB::table('users')->where('id', $user)->exists(), 404);
        $data = $request->validate(['role' => ['required', Rule::in(['student','admin','super_admin'])], 'active' => ['required','boolean'], 'password' => ['nullable','string','min:10','max:128']]);
        $update = ['role' => $data['role'], 'active' => $data['active'], 'updated_at' => now()]; if (!empty($data['password'])) $update['password'] = Hash::make($data['password']);
        DB::table('users')->where('id', $user)->update($update); DB::table('sessions')->where('user_id', $user)->delete();
        $this->audit($request, 'Updated account', (string) $user, $data['role']); return back()->with('success', 'Account updated and existing sessions revoked.');
    }

    public function createAccount(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['name' => ['required','string','max:255'], 'university_id' => ['required','string','max:50','unique:users'], 'email' => ['nullable','email','max:255','unique:users'], 'role' => ['required', Rule::in(['student','admin'])], 'password' => ['required','string','min:10','max:128']]);
        $id = DB::table('users')->insertGetId([...$data, 'email' => $data['email'] ?: null, 'active' => true, 'password' => Hash::make($data['password']), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'Created account', (string) $id, $data['role']);
        return back()->with('success', 'Account created. The user can now sign in and complete their profile.');
    }

    private function formationRegistrationFields(string $type): array
    {
        if (!in_array($type, ['Retreat', 'Recollection'], true)) return [];
        $event = strtolower($type);
        return [
            ['section' => 'Personal information', 'label' => 'Nickname', 'type' => 'text', 'required' => true],
            ['section' => 'Personal information', 'label' => 'Age', 'type' => 'number', 'required' => true],
            ['section' => 'Personal information', 'label' => 'Sex', 'type' => 'select', 'options' => ['Female', 'Male', 'Prefer not to say'], 'required' => true],
            ['section' => 'Emergency information', 'label' => 'Emergency contact person', 'type' => 'text', 'required' => true],
            ['section' => 'Emergency information', 'label' => 'Relationship to emergency contact', 'type' => 'text', 'required' => true],
            ['section' => 'Emergency information', 'label' => 'Emergency contact number', 'type' => 'text', 'required' => true],
            ['section' => 'Health and safety', 'label' => 'Medical conditions', 'type' => 'textarea', 'required' => false],
            ['section' => 'Health and safety', 'label' => 'Allergies', 'type' => 'textarea', 'required' => false],
            ['section' => 'Health and safety', 'label' => 'Medications currently taking', 'type' => 'textarea', 'required' => false],
            ['section' => 'Faith and sacramental background', 'label' => 'Religion', 'type' => 'text', 'required' => true],
            ['section' => 'Faith and sacramental background', 'label' => 'Parish, if any', 'type' => 'text', 'required' => false],
            ['section' => 'Faith and sacramental background', 'label' => 'Baptism received', 'type' => 'checkbox', 'required' => false],
            ['section' => 'Faith and sacramental background', 'label' => 'Holy Communion received', 'type' => 'checkbox', 'required' => false],
            ['section' => 'Faith and sacramental background', 'label' => 'Confirmation received', 'type' => 'checkbox', 'required' => false],
            ['section' => 'Faith and sacramental background', 'label' => 'Church or campus ministry involvement', 'type' => 'textarea', 'required' => false],
            ['section' => ucfirst($event).' experience', 'label' => "Is this your first Catholic {$event}?", 'type' => 'select', 'options' => ['Yes', 'No'], 'required' => true],
            ['section' => ucfirst($event).' experience', 'label' => 'Previous '.ucfirst($event).'s attended, if any', 'type' => 'textarea', 'required' => false],
            ['section' => 'Expectations and intentions', 'label' => "What do you hope to receive or experience during this {$event}?", 'type' => 'textarea', 'required' => true],
            ['section' => 'Personal reflection', 'label' => 'Prayer intentions, struggles, or concerns you wish to share', 'type' => 'textarea', 'required' => false],
            ['section' => 'Consent and commitment', 'label' => "I understand that this {$event} is a faith-based activity and I choose to participate with openness, respect, and confidentiality.", 'type' => 'checkbox', 'required' => true],
            ['section' => 'Consent and commitment', 'label' => "I understand that photos may be taken during this {$event} for University purposes.", 'type' => 'checkbox', 'required' => true],
            ['section' => 'Consent and commitment', 'label' => 'Type your full name as your signature', 'type' => 'text', 'required' => true],
        ];
    }

    private function staff(Request $request): void { abort_unless($request->user()->isStaff(), 403); }
    private function notify(int $userId, string $message): void { DB::table('notifications')->insert(['user_id' => $userId, 'message' => $message, 'created_at' => now(), 'updated_at' => now()]); }
    private function audit(Request $request, string $action, string $target, ?string $detail = null): void { DB::table('audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'target' => $target, 'detail' => $detail, 'created_at' => now(), 'updated_at' => now()]); }
}
