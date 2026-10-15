<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudentEmailVerificationService;
use App\Support\FormationRegistrationFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function __construct(private StudentEmailVerificationService $emailVerification) {}

    public function profile(Request $request)
    {
        abort_unless($request->user()->role === 'student', 403);
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'contact' => trim((string) $request->input('contact')),
        ]);
        $student = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($student) {
                $email = Str::lower((string) $value);
                $inUse = User::where('id', '!=', $student->id)
                    ->where(function ($query) use ($email) {
                        $query->whereRaw('LOWER(email) = ?', [$email])
                            ->orWhereRaw('LOWER(pending_email) = ?', [$email]);
                    })
                    ->exists();
                if ($inUse) {
                    $fail('Another account already uses or is verifying this email address.');
                }
            }],
            'contact' => ['required', 'string', 'max:150'],
        ]);
        $profileExists = DB::table('profiles')->where('user_id', $student->id)->exists();
        if (! $profileExists) {
            return back()->withErrors(['contact' => 'Your academic record must be set up by CFO Staff before contact details can be saved.']);
        }

        $emailChanged = $data['email'] !== Str::lower((string) $student->email);
        DB::table('profiles')->where('user_id', $student->id)->update([
            'contact' => $data['contact'],
            'updated_at' => now(),
        ]);
        if ($emailChanged) {
            $student->forceFill(['pending_email' => $data['email']])->save();
            $this->emailVerification->send($student->fresh());
        }
        $this->audit($request, 'Updated own contact details', $student->university_id, $emailChanged ? 'Email verification requested' : null);

        return back()->with('success', $emailChanged
            ? "Contact details saved. Verify {$data['email']} before it becomes your sign-in email."
            : 'Contact details saved.');
    }

    public function updateProfileDetails(Request $request, int $profile)
    {
        $this->staff($request);
        $record = DB::table('profiles as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.id', $profile)
            ->where('u.role', 'student')
            ->select('p.*', 'u.university_id')
            ->first();
        abort_unless($record, 404);

        $request->merge([
            'name' => preg_replace('/\s+/u', ' ', trim((string) $request->input('name'))),
            'department' => trim((string) $request->input('department')),
            'course' => trim((string) $request->input('course')),
            'major' => trim((string) $request->input('major')),
            'year' => trim((string) $request->input('year')),
            'section' => strtoupper(trim((string) $request->input('section'))),
        ]);
        $catalog = config('student_registration.catalog', []);
        $department = $request->input('department');
        $course = $request->input('course');
        $majors = $catalog[$department][$course] ?? [];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', Rule::in(array_keys($catalog))],
            'course' => ['required', Rule::in(array_keys($catalog[$department] ?? []))],
            'major' => [Rule::requiredIf($majors !== []), 'nullable', 'string', 'max:150', function (string $attribute, mixed $value, \Closure $fail) use ($majors) {
                if ($majors === [] && filled($value)) {
                    $fail('The selected course does not have a major option.');
                } elseif ($majors !== [] && ! in_array($value, $majors, true)) {
                    $fail('Select an official major for the selected course.');
                }
            }],
            'year' => ['required', 'regex:/^[1-9]$/'],
            'section' => ['required', 'regex:/^[A-Z]$/'],
        ]);
        $data['major'] = $data['major'] ?: null;

        DB::transaction(function () use ($record, $data) {
            DB::table('users')->where('id', $record->user_id)->update([
                'name' => $data['name'],
                'updated_at' => now(),
            ]);
            DB::table('profiles')->where('id', $record->id)->update([
                'department' => $data['department'],
                'course' => $data['course'],
                'major' => $data['major'],
                'year' => $data['year'],
                'section' => $data['section'],
                'updated_at' => now(),
            ]);
        });
        $this->notify($record->user_id, 'CFO Staff updated your name or academic details.');
        $this->audit($request, 'Updated student identity and academic details', $record->university_id);

        return back()->with('success', 'Student identity and academic details updated.');
    }

    public function reviewProfile(Request $request, int $profile)
    {
        $this->staff($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['Verified', 'Requires correction'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('status') === 'Requires correction')],
        ]);
        $record = DB::table('profiles')->find($profile);
        abort_unless($record, 404);
        DB::table('profiles')->where('id', $profile)->update([...$data, 'updated_at' => now()]);
        $this->notify($record->user_id, "Your profile status is now {$data['status']}.");
        $this->audit($request, 'Reviewed profile', (string) $profile, $data['status']);

        return back()->with('success', 'Profile review saved.');
    }

    public function activity(Request $request)
    {
        $this->staff($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['Mass', 'Recollection', 'Retreat'])],
            'description' => ['required', 'string', 'max:5000'],
            'activity_date' => ['required', 'date'],
            'activity_time' => ['required'],
            'venue' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:150', 'not_in:All departments'],
            'target_year' => ['required', 'string', 'max:20'],
            'target_section' => ['required', 'string', 'max:50'],
            'participation_type' => ['required', Rule::in(['Required', 'Optional'])],
            'deadline' => ['required', 'date', 'before_or_equal:activity_date'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'required_documents' => ['nullable', 'string', 'max:2000'],
            'registration_questions' => ['nullable', 'string', 'max:5000'],
            'mass_reflection_required' => ['nullable', 'boolean'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', Rule::in(['Draft', 'Published', 'Postponed', 'Cancelled', 'Archived'])],
            'intent' => ['nullable', Rule::in(['preview', 'create'])],
            'target_confirmation' => ['nullable', 'string'],
        ]);

        $target = ['department' => $data['department'], 'year' => $data['target_year'], 'section' => $data['target_section']];
        $matchedStudents = $this->targetedStudents($target)->get(['u.id', 'u.name']);
        if (($data['intent'] ?? null) === 'preview') {
            return back()->withInput()->with('activity_target_preview', [
                ...$target,
                'count' => $matchedStudents->count(),
                'token' => Crypt::encryptString(json_encode([...$target, 'creator' => $request->user()->id, 'count' => $matchedStudents->count(), 'issued_at' => now()->timestamp])),
            ]);
        }
        if ($data['status'] === 'Published') {
            $confirmed = $this->confirmedActivityTarget($data['target_confirmation'] ?? null, $request->user()->id, $matchedStudents->count());
            if ($confirmed !== $target) {
                return back()->withInput()->withErrors(['target_confirmation' => 'Review the exact target group and matched student count before publishing.']);
            }
            if ($matchedStudents->isEmpty()) {
                return back()->withInput()->withErrors(['department' => 'No active students match this exact course, year level, and section.']);
            }
        }

        $documents = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['required_documents'] ?? ''))));
        $questions = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['registration_questions'] ?? ''))));
        $standardFields = $this->formationRegistrationFields($data['type']);
        $massReflectionRequired = $request->boolean('mass_reflection_required');
        foreach (['required_documents', 'registration_questions', 'mass_reflection_required', 'intent', 'target_confirmation'] as $key) {
            unset($data[$key]);
        }
        $id = DB::transaction(function () use ($request, $data, $documents, $questions, $standardFields, $massReflectionRequired) {
            $id = DB::table('activities')->insertGetId([...$data, 'required_documents' => json_encode($documents), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($standardFields as $order => $field) {
                DB::table('activity_form_fields')->insert(['activity_id' => $id, 'label' => $field['label'], 'field_type' => $field['type'], 'options' => isset($field['options']) ? json_encode($field['options']) : null, 'section_name' => $field['section'], 'required' => $field['required'], 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($questions as $index => $label) {
                DB::table('activity_form_fields')->insert(['activity_id' => $id, 'label' => $label, 'field_type' => 'text', 'section_name' => 'Additional information', 'required' => true, 'sort_order' => count($standardFields) + $index, 'created_at' => now(), 'updated_at' => now()]);
            }
            $order = 0;
            if ($standardFields) {
                DB::table('activity_requirements')->insert(['activity_id' => $id, 'name' => 'Payment receipt', 'type' => 'receipt', 'instructions' => 'Upload a PDF, PNG, or JPG file up to 5 MB during submission.', 'required' => true, 'sort_order' => $order++, 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($data['type'] === 'Mass') {
                DB::table('activity_requirements')->insert(['activity_id' => $id, 'name' => 'Real-time attendance photo', 'type' => 'attendance_photo', 'instructions' => 'Capture a live photo with the in-app camera during submission.', 'required' => true, 'sort_order' => $order++, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('activity_requirements')->insert(['activity_id' => $id, 'name' => 'Reflection attachment', 'type' => 'reflection_document', 'instructions' => 'Upload a PDF, DOC, or DOCX file up to 5 MB.', 'required' => $massReflectionRequired, 'sort_order' => $order++, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($documents as $name) {
                DB::table('activity_requirements')->insert(['activity_id' => $id, 'name' => $name, 'type' => 'document', 'required' => true, 'sort_order' => $order++, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        });
        if ($data['status'] === 'Published') {
            foreach ($matchedStudents as $student) {
                $this->notify($student->id, "A new {$data['type']} activity was assigned to your class: {$data['title']}.");
            }
        }
        $this->audit($request, 'Created activity', (string) $id, $data['title']);

        return back()->with('success', 'Activity created.');
    }

    public function registration(Request $request, int $activity)
    {
        abort_unless($request->user()->role === 'student', 403);

        $record = DB::table('activities')
            ->where('id', $activity)
            ->where('status', 'Published')
            ->where('activity_date', '>=', now()->timezone('Asia/Manila')->toDateString())
            ->first();
        $profile = DB::table('profiles')->where('user_id', $request->user()->id)->first();
        abort_unless($record && $this->studentCanAccessActivity($record, $profile), 404);

        $fields = DB::table('activity_form_fields')->where('activity_id', $activity)->orderBy('sort_order')->get();
        $requirements = DB::table('activity_requirements')->where('activity_id', $activity)->orderBy('sort_order')->get();
        $existingRegistration = DB::table('registrations')
            ->where('activity_id', $activity)
            ->where('user_id', $request->user()->id)
            ->where('status', '!=', 'Withdrawn')
            ->first();
        $registered = DB::table('registrations')
            ->where('activity_id', $activity)
            ->where('status', '!=', 'Withdrawn')
            ->count();

        return view('activity-registration', [
            'activity' => $record,
            'profile' => $profile,
            'fields' => $fields,
            'requirements' => $requirements,
            'existingRegistration' => $existingRegistration,
            'remaining' => max(0, $record->capacity - $registered),
            'registrationOpen' => now()->timezone('Asia/Manila')->toDateString() <= $record->deadline && $registered < $record->capacity,
            'formationForm' => $fields->whereNotNull('section_name')->isNotEmpty(),
        ]);
    }

    public function register(Request $request, int $activity)
    {
        abort_unless($request->user()->role === 'student', 403);
        $result = DB::transaction(function () use ($request, $activity) {
            // Lock the activity so concurrent registrations cannot all pass the capacity check.
            $record = DB::table('activities')->where('id', $activity)->lockForUpdate()->first();
            abort_unless($record && $record->status === 'Published', 404);

            $profile = DB::table('profiles')->where('user_id', $request->user()->id)->first();
            abort_unless($this->studentCanAccessActivity($record, $profile), 404);

            $existing = DB::table('registrations')
                ->where('activity_id', $activity)
                ->where('user_id', $request->user()->id)
                ->first();
            if ($existing && $existing->status !== 'Withdrawn') {
                return ['already_registered' => true];
            }

            if (now()->toDateString() > $record->deadline) {
                return ['error' => 'The registration deadline has passed.'];
            }

            $fields = DB::table('activity_form_fields')->where('activity_id', $activity)->orderBy('sort_order')->get();
            if ($fields->whereNotNull('section_name')->isNotEmpty()) {
                $verifiedProfile = DB::table('profiles')->where('user_id', $request->user()->id)->where('status', 'Verified')->exists();
                if (! $verifiedProfile) {
                    return ['error' => 'Your student profile must be verified before you can register for this activity.'];
                }
            }
            $rules = [];
            foreach ($fields as $field) {
                $fieldRules = match ($field->field_type) {
                    'number' => ['integer', 'min:1', 'max:120'],
                    'checkbox' => ['accepted'],
                    'select' => ['string', Rule::in(json_decode($field->options ?: '[]', true))],
                    default => ['string', 'max:3000'],
                };
                $rules["answers.{$field->id}"] = [$field->required ? 'required' : 'nullable', ...$fieldRules];
            }
            $requirements = DB::table('activity_requirements')->where('activity_id', $activity)->orderBy('sort_order')->get();
            $receiptRequirement = $requirements->firstWhere('type', 'receipt');
            $attendancePhotoRequirement = $requirements->firstWhere('type', 'attendance_photo');
            $reflectionRequirement = $requirements->firstWhere('type', 'reflection_document');
            $documentRequirements = $requirements->where('type', 'document');
            if ($receiptRequirement) {
                $rules['receipt'] = ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'];
            }
            if ($attendancePhotoRequirement) {
                $rules['attendance_photo'] = ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'];
                $rules['capture_source'] = ['required', Rule::in(['live_camera'])];
            }
            if ($reflectionRequirement) {
                $rules['reflection_attachment'] = [$reflectionRequirement->required ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'];
            }
            foreach ($documentRequirements as $requirement) {
                $rules["requirement_files.{$requirement->id}"] = [$requirement->required ? 'required' : 'nullable', 'file', 'mimes:pdf,png,jpg,jpeg,doc,docx', 'max:5120'];
            }
            $attributes = [
                'receipt' => "{$record->type} payment receipt",
                'attendance_photo' => 'Mass real-time attendance photo',
                'capture_source' => 'Mass camera capture',
                'reflection_attachment' => 'Mass reflection attachment',
            ];
            foreach ($fields as $field) {
                $attributes["answers.{$field->id}"] = "{$record->type} form: {$field->label}";
            }
            foreach ($documentRequirements as $requirement) {
                $attributes["requirement_files.{$requirement->id}"] = $requirement->name;
            }
            $validated = $request->validate($rules, [], $attributes);
            $answers = $validated['answers'] ?? [];

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
            foreach ($fields as $field) {
                DB::table('registration_answers')->updateOrInsert(['registration_id' => $registrationId, 'field_id' => $field->id], ['answer' => $field->field_type === 'checkbox' ? (isset($answers[$field->id]) ? 'Yes' : 'No') : ($answers[$field->id] ?? null), 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($receiptRequirement) {
                $this->storeRequirementFile($request->user()->id, $registrationId, $receiptRequirement, $validated['receipt']);
            }
            if ($attendancePhotoRequirement) {
                $this->storeRequirementFile($request->user()->id, $registrationId, $attendancePhotoRequirement, $validated['attendance_photo']);
            }
            if ($reflectionRequirement && isset($validated['reflection_attachment'])) {
                $this->storeRequirementFile($request->user()->id, $registrationId, $reflectionRequirement, $validated['reflection_attachment']);
            }
            foreach ($documentRequirements as $requirement) {
                if (isset($validated['requirement_files'][$requirement->id])) {
                    $this->storeRequirementFile($request->user()->id, $registrationId, $requirement, $validated['requirement_files'][$requirement->id]);
                }
            }

            return ['title' => $record->title];
        });

        if (isset($result['error'])) {
            return back()->withErrors(['registration' => $result['error']]);
        }
        if (isset($result['already_registered'])) {
            return back()->with('success', 'You are already registered for this activity.');
        }

        $this->audit($request, 'Registered for activity', (string) $activity, $result['title']);

        return back()->with('success', $result['title'].' submission received successfully.');
    }

    public function reviewRegistration(Request $request, int $registration)
    {
        $this->staff($request);
        $data = $request->validate(['status' => ['required', Rule::in(['Under review', 'Requires correction', 'Accepted', 'Withdrawn'])], 'note' => ['nullable', 'string', 'max:2000']]);
        $record = DB::table('registrations')->find($registration);
        abort_unless($record, 404);
        DB::table('registrations')->where('id', $registration)->update([...$data, 'updated_at' => now()]);
        $this->notify($record->user_id, "Your registration status is now {$data['status']}.");
        $this->audit($request, 'Reviewed registration', (string) $registration, $data['status']);

        return back()->with('success', 'Registration updated.');
    }

    public function upload(Request $request, int $registration)
    {
        abort_unless($request->user()->role === 'student', 403);
        $record = DB::table('registrations')->where('id', $registration)->where('user_id', $request->user()->id)->first();
        abort_unless($record, 404);
        $data = $request->validate(['document' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'], 'requirement' => ['nullable', 'string', 'max:255']]);
        $file = $data['document'];
        $path = $file->store('student-documents', 'local');
        DB::table('documents')->insert(['registration_id' => $registration, 'user_id' => $request->user()->id, 'original_name' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'requirement' => $data['requirement'] ?? null, 'status' => 'Pending review', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('registrations')->where('id', $registration)->update(['status' => 'Under review', 'updated_at' => now()]);
        $this->audit($request, 'Uploaded document', (string) $registration, $file->getClientOriginalName());

        return back()->with('success', 'Document uploaded securely.');
    }

    public function reviewDocument(Request $request, int $document)
    {
        $this->staff($request);
        $data = $request->validate(['status' => ['required', Rule::in(['Accepted', 'Requires correction'])], 'note' => ['nullable', 'string', 'max:2000']]);
        $record = DB::table('documents')->find($document);
        abort_unless($record, 404);
        DB::table('documents')->where('id', $document)->update([...$data, 'reviewed_at' => now(), 'updated_at' => now()]);
        $this->notify($record->user_id, "A submitted document was marked {$data['status']}.");
        $this->audit($request, 'Reviewed document', (string) $document, $data['status']);

        return back()->with('success', 'Document review saved.');
    }

    public function submitRequirement(Request $request, int $registration, int $requirement)
    {
        abort_unless($request->user()->role === 'student', 403);
        $record = DB::table('registrations')->where('id', $registration)->where('user_id', $request->user()->id)->first();
        abort_unless($record, 404);
        $required = DB::table('activity_requirements')->where('id', $requirement)->where('activity_id', $record->activity_id)->first();
        abort_unless($required, 404);
        $data = match ($required->type) {
            'reflection' => $request->validate(['text_content' => ['required', 'string', 'max:10000']]),
            'reflection_document' => $request->validate(['submission_file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120']]),
            'attendance_photo' => abort(403, 'Attendance photos can only be captured during the original Mass submission.'),
            default => $request->validate(['submission_file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120']]),
        };
        $existing = DB::table('requirement_submissions')->where('registration_id', $registration)->where('requirement_id', $requirement)->first();
        $values = ['user_id' => $request->user()->id, 'text_content' => $data['text_content'] ?? null, 'status' => 'Submitted', 'note' => null, 'reviewed_at' => null, 'updated_at' => now()];
        if (isset($data['submission_file'])) {
            $file = $data['submission_file'];
            $values += ['original_name' => $file->getClientOriginalName(), 'path' => $file->store('requirement-submissions', 'local'), 'mime' => $file->getMimeType()];
            if ($existing?->path) {
                Storage::disk('local')->delete($existing->path);
            }
        }
        if ($existing) {
            DB::table('requirement_submissions')->where('id', $existing->id)->update($values);
        } else {
            DB::table('requirement_submissions')->insert(['registration_id' => $registration, 'requirement_id' => $requirement, ...$values, 'created_at' => now()]);
        }
        DB::table('registrations')->where('id', $registration)->where('status', 'Requires correction')->update(['status' => 'Under review', 'updated_at' => now()]);
        $this->audit($request, 'Submitted activity requirement', (string) $registration, $required->name);

        return back()->with('success', $required->name.' submitted for review.');
    }

    public function reviewRequirementSubmission(Request $request, int $submission)
    {
        $this->staff($request);
        $data = $request->validate(['status' => ['required', Rule::in(['Accepted', 'Requires correction'])], 'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('status') === 'Requires correction')]]);
        $record = DB::table('requirement_submissions')->find($submission);
        abort_unless($record, 404);
        DB::table('requirement_submissions')->where('id', $submission)->update([...$data, 'reviewed_at' => now(), 'updated_at' => now()]);
        $this->notify($record->user_id, "An activity requirement was marked {$data['status']}.");
        $this->audit($request, 'Reviewed activity requirement', (string) $submission, $data['status']);

        return back()->with('success', 'Requirement review saved.');
    }

    public function facility(Request $request)
    {
        $this->staff($request);
        $data = $request->validate(['facility' => ['required', 'string', 'max:150'], 'purpose' => ['required', 'string', 'max:3000'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'participants' => ['required', 'integer', 'min:1', 'max:10000']]);
        DB::table('facility_requests')->insert([...$data, 'user_id' => $request->user()->id, 'status' => 'Pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'Requested facility', $data['facility']);

        return back()->with('success', 'Facility request submitted.');
    }

    public function decideFacility(Request $request, int $facility)
    {
        $this->staff($request);
        $data = $request->validate(['status' => ['required', Rule::in(['Approved', 'Rejected', 'Returned for correction'])], 'note' => ['nullable', 'string', 'max:2000']]);
        $record = DB::table('facility_requests')->find($facility);
        abort_unless($record, 404);
        if ($data['status'] === 'Approved') {
            $conflict = DB::table('facility_requests')->where('facility', $record->facility)->where('status', 'Approved')->where('id', '!=', $facility)->where('starts_at', '<', $record->ends_at)->where('ends_at', '>', $record->starts_at)->exists();
            if ($conflict) {
                return back()->withErrors(['facility' => 'This approved time overlaps another booking.']);
            }
        }
        DB::table('facility_requests')->where('id', $facility)->update([...$data, 'updated_at' => now()]);
        $this->notify($record->user_id, "Your {$record->facility} request is now {$data['status']}.");
        $this->audit($request, 'Reviewed facility request', (string) $facility, $data['status']);

        return back()->with('success', 'Facility request updated.');
    }

    public function announcement(Request $request)
    {
        $this->staff($request);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:5000'], 'department' => ['required', 'string', 'max:150'], 'target_year' => ['nullable', 'string', 'max:20'], 'target_section' => ['nullable', 'string', 'max:50']]);
        $data['target_year'] = ($data['target_year'] ?? null) ?: null;
        $data['target_section'] = ($data['target_section'] ?? null) ?: null;
        DB::table('announcements')->insert([...$data, 'author_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $students = DB::table('users as u')->leftJoin('profiles as p', 'p.user_id', '=', 'u.id')->where('u.role', 'student')->where('u.active', true)
            ->when($data['department'] !== 'All departments', fn ($query) => $query->where('p.department', $data['department']))
            ->when($data['target_year'], fn ($query, $year) => $query->where('p.year', $year))
            ->when($data['target_section'], fn ($query, $section) => $query->where('p.section', $section))->pluck('u.id');
        foreach ($students as $studentId) {
            $this->notify($studentId, 'New announcement: '.$data['title']);
        }
        $this->audit($request, 'Published announcement', $data['title']);

        return back()->with('success', 'Announcement published.');
    }

    public function readNotifications(Request $request)
    {
        DB::table('notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Notifications marked as read.');
    }

    public function account(Request $request, int $user)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($request->user()->id === $user, 422, 'You cannot change your own access here.');
        abort_unless(DB::table('users')->where('id', $user)->exists(), 404);
        $data = $request->validate(['role' => ['required', Rule::in(['student', 'admin', 'super_admin'])], 'active' => ['required', 'boolean'], 'password' => ['nullable', 'string', 'min:10', 'max:128']]);
        $update = ['role' => $data['role'], 'active' => $data['active'], 'updated_at' => now()];
        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }
        DB::table('users')->where('id', $user)->update($update);
        DB::table('sessions')->where('user_id', $user)->delete();
        $this->audit($request, 'Updated account', (string) $user, $data['role']);

        return back()->with('success', 'Account updated and existing sessions revoked.');
    }

    public function createAccount(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'university_id' => ['required', 'string', 'max:50', 'unique:users'], 'email' => ['nullable', 'email', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
            $email = Str::lower(trim((string) $value));
            if ($value && DB::table('users')->where(fn ($query) => $query
                ->whereRaw('LOWER(email) = ?', [$email])
                ->orWhereRaw('LOWER(pending_email) = ?', [$email]))->exists()) {
                $fail('An account already uses this email address.');
            }
        }], 'role' => ['required', Rule::in(['student', 'admin'])], 'password' => ['required', 'string', 'min:10', 'max:128']]);
        $email = isset($data['email']) && trim($data['email']) !== '' ? Str::lower(trim($data['email'])) : null;
        $studentState = $data['role'] === 'student' ? ['email_verified_at' => now(), 'student_account_status' => 'Active'] : [];
        $id = DB::table('users')->insertGetId([...$data, ...$studentState, 'email' => $email, 'active' => true, 'password' => Hash::make($data['password']), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'Created account', (string) $id, $data['role']);

        return back()->with('success', 'Account created. The user can now sign in and complete their profile.');
    }

    private function formationRegistrationFields(string $type): array
    {
        return FormationRegistrationFields::forType($type);
    }

    private function studentCanAccessActivity(object $activity, ?object $profile): bool
    {
        return $profile
            && $activity->department === ($profile->course ?: $profile->department)
            && (string) $activity->target_year === (string) $profile->year
            && $activity->target_section === $profile->section;
    }

    private function targetedStudents(array $target)
    {
        return DB::table('users as u')
            ->join('profiles as p', 'p.user_id', '=', 'u.id')
            ->where('u.role', 'student')
            ->where('u.active', true)
            ->where(function ($query) use ($target) {
                $query->where('p.course', $target['department'])
                    ->orWhere(function ($legacy) use ($target) {
                        $legacy->whereNull('p.course')->where('p.department', $target['department']);
                    });
            })
            ->where('p.year', $target['year'])
            ->where('p.section', $target['section']);
    }

    private function confirmedActivityTarget(?string $token, int $creatorId, int $currentCount): ?array
    {
        if (! $token) {
            return null;
        }
        try {
            $target = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (($target['creator'] ?? null) !== $creatorId
            || ($target['count'] ?? null) !== $currentCount
            || ! isset($target['issued_at'])
            || now()->timestamp - (int) $target['issued_at'] > 1800) {
            return null;
        }

        return [
            'department' => $target['department'] ?? null,
            'year' => $target['year'] ?? null,
            'section' => $target['section'] ?? null,
        ];
    }

    private function storeRequirementFile(int $userId, int $registrationId, object $requirement, object $file): void
    {
        $existing = DB::table('requirement_submissions')
            ->where('registration_id', $registrationId)
            ->where('requirement_id', $requirement->id)
            ->first();
        if ($existing?->path) {
            Storage::disk('local')->delete($existing->path);
        }
        $values = [
            'user_id' => $userId,
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('requirement-submissions', 'local'),
            'mime' => $file->getMimeType(),
            'text_content' => null,
            'status' => 'Submitted',
            'note' => null,
            'reviewed_at' => null,
            'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('requirement_submissions')->where('id', $existing->id)->update($values);
        } else {
            DB::table('requirement_submissions')->insert(['registration_id' => $registrationId, 'requirement_id' => $requirement->id, ...$values, 'created_at' => now()]);
        }
    }

    private function staff(Request $request): void
    {
        abort_unless($request->user()->isStaff(), 403);
    }

    private function notify(int $userId, string $message): void
    {
        DB::table('notifications')->insert(['user_id' => $userId, 'message' => $message, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function audit(Request $request, string $action, string $target, ?string $detail = null): void
    {
        DB::table('audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'target' => $target, 'detail' => $detail, 'created_at' => now(), 'updated_at' => now()]);
    }
}
