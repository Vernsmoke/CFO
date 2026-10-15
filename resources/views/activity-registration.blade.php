@extends('layouts.app')
@section('title', 'Register for '.$activity->title.' | CFO')
@section('body')
@php
    $user = auth()->user();
    $receiptRequirement = $requirements->firstWhere('type', 'receipt');
    $attendancePhotoRequirement = $requirements->firstWhere('type', 'attendance_photo');
    $reflectionDocumentRequirement = $requirements->firstWhere('type', 'reflection_document');
    $documentRequirements = $requirements->where('type', 'document');
@endphp
<div class="registration-page-shell">
    <header class="registration-page-header">
        <div class="registration-page-header-inner">
            <a class="brand registration-brand" href="{{ route('dashboard') }}" aria-label="Christian Formation Office overview">
                <img src="{{ asset('images/cfo-logo.png') }}" alt="">
                <span>Christian Formation Office</span>
            </a>
            <div class="registration-account">
                <span><strong>{{ $user->name }}</strong><small>{{ $user->university_id }}</small></span>
                <a href="{{ route('dashboard.section', 'activities') }}">Back to activities</a>
            </div>
        </div>
    </header>

    <main class="registration-page" id="main-content">
        @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="alert error" role="alert"><strong>Please check your registration.</strong><span>{{ $errors->first() }}</span></div>
        @endif

        <div class="registration-page-grid">
            <aside class="registration-event-summary" aria-labelledby="activity-title">
                <div class="activity-labels"><span>{{ $activity->type }}</span><span>{{ $activity->participation_type }}</span></div>
                <h1 id="activity-title">{{ $activity->title }}</h1>
                <p>{{ $activity->description }}</p>

                <dl class="registration-event-meta">
                    <div><dt>Date</dt><dd>{{ \Carbon\Carbon::parse($activity->activity_date)->format('F j, Y') }}</dd></div>
                    <div><dt>Time</dt><dd>{{ \Carbon\Carbon::parse($activity->activity_time)->format('g:i A') }}</dd></div>
                    <div><dt>Venue</dt><dd>{{ $activity->venue }}</dd></div>
                    <div><dt>Deadline</dt><dd>{{ \Carbon\Carbon::parse($activity->deadline)->format('F j, Y') }}</dd></div>
                    <div><dt>Availability</dt><dd>{{ $remaining }} {{ Illuminate\Support\Str::plural('place', $remaining) }} remaining</dd></div>
                </dl>

                @if($requirements->isNotEmpty() || $activity->requirements)
                    <section class="registration-checklist">
                        <h2>Before you begin</h2>
                        @if($activity->requirements)<p>{{ $activity->requirements }}</p>@endif
                        @if($requirements->isNotEmpty())
                            <ul>@foreach($requirements as $requirement)<li>{{ $requirement->name }}{{ $requirement->required ? '' : ' (optional)' }}</li>@endforeach</ul>
                        @endif
                    </section>
                @endif
            </aside>

            <section class="registration-form-panel" aria-labelledby="registration-title">
                @if($existingRegistration)
                    <div class="registration-complete-state">
                        <span class="status">{{ $existingRegistration->status }}</span>
                        <h2 id="registration-title">Your registration is already submitted</h2>
                        <p>Track the review status, uploaded files, and any correction requests from My submissions.</p>
                        <div class="registration-page-actions">
                            <a class="button" href="{{ route('dashboard.section', 'submissions') }}">View submission</a>
                            <a class="button secondary" href="{{ route('dashboard.section', 'activities') }}">Return to activities</a>
                        </div>
                    </div>
                @elseif(!$registrationOpen)
                    <div class="registration-complete-state">
                        <span class="status">Unavailable</span>
                        <h2 id="registration-title">{{ $remaining ? 'Registration has closed' : 'This activity is full' }}</h2>
                        <p>{{ $remaining ? 'The registration deadline has passed.' : 'All available places have been taken.' }}</p>
                        <a class="button secondary" href="{{ route('dashboard.section', 'activities') }}">Return to activities</a>
                    </div>
                @elseif($formationForm && $profile?->status !== 'Verified')
                    <div class="registration-complete-state">
                        <span class="status">Profile required</span>
                        <h2 id="registration-title">Verify your student profile first</h2>
                        <p>The CFO must verify your academic and contact information before you can submit this form.</p>
                        <div class="registration-page-actions">
                            <a class="button" href="{{ route('dashboard.section', 'profile') }}">Open profile</a>
                            <a class="button secondary" href="{{ route('dashboard.section', 'activities') }}">Return to activities</a>
                        </div>
                    </div>
                @else
                    <form method="post" action="{{ route('activities.register', $activity->id) }}" class="registration-form dedicated-registration-form" enctype="multipart/form-data">
                        @csrf
                        @if($formationForm)
                            <div class="registration-heading">
                                <p class="eyebrow">Registration form</p>
                                <h2 id="registration-title">{{ $activity->type }} details</h2>
                                <p>Your information is private and will be used only for this {{ strtolower($activity->type) }}.</p>
                            </div>
                            <section class="registration-profile-snapshot">
                                <h3>Verified student information</h3>
                                <dl>
                                    <div><dt>Name</dt><dd>{{ $user->name }}</dd></div>
                                    <div><dt>Student ID</dt><dd>{{ $user->university_id }}</dd></div>
                                    <div><dt>Department</dt><dd>{{ $profile?->department }}</dd></div>
                                    <div><dt>Course or program</dt><dd>{{ $profile?->course ?: $profile?->department }}@if($profile?->major), Major in {{ $profile->major }}@endif</dd></div>
                                    <div><dt>Year and section</dt><dd>Year {{ $profile?->year }}, Section {{ $profile?->section }}</dd></div>
                                    <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
                                    <div><dt>Contact number</dt><dd>{{ $profile?->contact }}</dd></div>
                                </dl>
                            </section>
                        @elseif($activity->type === 'Mass')
                            <div class="registration-heading">
                                <p class="eyebrow">Attendance submission</p>
                                <h2 id="registration-title">Mass attendance evidence</h2>
                                <p>Your identity and academic details will be recorded automatically.</p>
                            </div>
                        @else
                            <div class="registration-heading">
                                <p class="eyebrow">Registration form</p>
                                <h2 id="registration-title">Confirm your participation</h2>
                                <p>Your identity and academic details will be included automatically.</p>
                            </div>
                        @endif

                        @foreach($fields->groupBy(fn ($field) => $field->section_name ?: 'Registration information') as $sectionName => $sectionFields)
                            <fieldset class="registration-section">
                                <legend>{{ $sectionName }}</legend>
                                <div class="registration-fields">
                                    @foreach($sectionFields as $field)
                                        @php $options = json_decode($field->options ?: '[]', true); @endphp
                                        @if($field->field_type === 'textarea')
                                            <label class="field-wide">{{ $field->label }}@unless($field->required)<small>Optional</small>@endunless<textarea name="answers[{{ $field->id }}]" @required($field->required) @class(['has-error' => $errors->has('answers.'.$field->id)])>{{ old('answers.'.$field->id) }}</textarea>@error('answers.'.$field->id)<small class="field-error">{{ $message }}</small>@enderror</label>
                                        @elseif($field->field_type === 'select')
                                            <label>{{ $field->label }}<select name="answers[{{ $field->id }}]" @required($field->required) @class(['has-error' => $errors->has('answers.'.$field->id)])><option value="">Select an answer</option>@foreach($options as $option)<option @selected(old('answers.'.$field->id) === $option)>{{ $option }}</option>@endforeach</select>@error('answers.'.$field->id)<small class="field-error">{{ $message }}</small>@enderror</label>
                                        @elseif($field->field_type === 'checkbox')
                                            <label class="registration-check field-wide"><input type="checkbox" name="answers[{{ $field->id }}]" value="yes" @checked(old('answers.'.$field->id)) @required($field->required)><span>{{ $field->label }}@unless($field->required)<small>Optional</small>@endunless @error('answers.'.$field->id)<small class="field-error">{{ $message }}</small>@enderror</span></label>
                                        @else
                                            <label>{{ $field->label }}@unless($field->required)<small>Optional</small>@endunless<input type="{{ $field->field_type === 'number' ? 'number' : 'text' }}" name="answers[{{ $field->id }}]" value="{{ old('answers.'.$field->id) }}" @required($field->required) @class(['has-error' => $errors->has('answers.'.$field->id)])>@error('answers.'.$field->id)<small class="field-error">{{ $message }}</small>@enderror</label>
                                        @endif
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach

                        @if($receiptRequirement)
                            <fieldset class="registration-section receipt-section">
                                <legend>Payment</legend>
                                <div data-file-preview>
                                    <label>Upload Payment Receipt<input type="file" name="receipt" accept=".pdf,image/png,image/jpeg" required data-preview-input><small>PDF, PNG, JPG, or JPEG. Maximum file size: 5 MB.</small>@error('receipt')<small class="field-error">{{ $message }}</small>@enderror</label>
                                    <div class="file-preview" data-preview-box hidden><img data-preview-image alt="Payment receipt preview" hidden><span data-preview-name></span><button class="button secondary small" type="button" data-preview-remove>Remove attachment</button></div>
                                </div>
                            </fieldset>
                        @endif

                        @if($attendancePhotoRequirement)
                            <fieldset class="registration-section mass-camera" data-mass-camera>
                                <legend>Real-time attendance proof</legend>
                                <p>Use this device camera now. Existing gallery images cannot be selected.</p>
                                <p class="camera-message" data-mass-camera-message>Camera access has not started.</p>
                                <div class="camera-frame"><video data-mass-camera-video autoplay muted playsinline></video><canvas data-mass-camera-canvas hidden></canvas><img data-mass-camera-preview alt="Captured Mass attendance photo" hidden></div>
                                <div class="camera-actions"><button class="button secondary" type="button" data-mass-camera-start>Open camera</button><button class="button" type="button" data-mass-camera-capture disabled>Capture photo</button><button class="button secondary" type="button" data-mass-camera-retake hidden>Retake photo</button></div>
                                <input type="file" name="attendance_photo" accept="image/png,image/jpeg" data-mass-camera-file hidden required><input type="hidden" name="capture_source" data-mass-camera-source>
                                @error('attendance_photo')<small class="field-error">{{ $message }}</small>@enderror
                            </fieldset>
                        @endif

                        @if($reflectionDocumentRequirement)
                            <fieldset class="registration-section">
                                <legend>Reflection attachment <small>{{ $reflectionDocumentRequirement->required ? 'Required' : 'Optional' }}</small></legend>
                                <div data-file-preview>
                                    <label>Upload reflection document<input type="file" name="reflection_attachment" accept=".pdf,.doc,.docx" @required($reflectionDocumentRequirement->required) data-preview-input><small>PDF, DOC, or DOCX. Maximum file size: 5 MB.</small>@error('reflection_attachment')<small class="field-error">{{ $message }}</small>@enderror</label>
                                    <div class="file-preview" data-preview-box hidden><span data-preview-name></span><button class="button secondary small" type="button" data-preview-remove>Remove attachment</button></div>
                                </div>
                            </fieldset>
                        @endif

                        @if($documentRequirements->isNotEmpty())
                            <fieldset class="registration-section required-file-list">
                                <legend>Required files</legend>
                                @foreach($documentRequirements as $documentRequirement)
                                    <div data-file-preview>
                                        <label>{{ $documentRequirement->name }} <small>{{ $documentRequirement->required ? 'Required' : 'Optional' }}</small><input type="file" name="requirement_files[{{ $documentRequirement->id }}]" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx" @required($documentRequirement->required) data-preview-input><small>PDF, PNG, JPG, JPEG, DOC, or DOCX. Maximum file size: 5 MB.</small>@error('requirement_files.'.$documentRequirement->id)<small class="field-error">{{ $message }}</small>@enderror</label>
                                        <div class="file-preview" data-preview-box hidden><span data-preview-name></span><button class="button secondary small" type="button" data-preview-remove>Remove attachment</button></div>
                                    </div>
                                @endforeach
                            </fieldset>
                        @endif

                        <footer class="registration-submit-bar">
                            <p>Submission date: {{ now()->timezone('Asia/Manila')->format('F j, Y') }}</p>
                            <button class="button" type="submit">Submit registration</button>
                        </footer>
                    </form>
                @endif
            </section>
        </div>
    </main>
</div>
@endsection
