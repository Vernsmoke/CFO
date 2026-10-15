@extends('layouts.app')
@section('title', 'Create Student Account | CFO')
@section('body')
<main class="login-page registration-page">
    <section class="login-art"><a class="brand light" href="{{ route('home') }}"><img src="{{ asset('images/cfo-logo.png') }}" alt="CFO logo"><span>Christian Formation Office</span></a><div><p class="eyebrow">Your formation workspace</p><h1>Begin here.</h1><p>Create your student account, verify your email, and keep your formation records in one secure place.</p></div></section>
    <section class="login-panel registration-panel">
        <form method="post" action="{{ route('student.register.store') }}" class="auth-card student-registration-card" data-student-account-form>
            @csrf
            <p class="eyebrow">Student registration</p>
            <h2>Create Student Account</h2>
            <p class="muted">Complete the required details below. Your account remains pending until your email is verified.</p>
            @if(session('pending_registration'))<div class="alert error" role="alert">A pending registration already exists. Use the resend option below.</div>@endif
            @php $registrationMajors = $catalog[old('department')][old('course')] ?? []; @endphp
            <div class="student-account-fields">
                <label>Student ID<input name="student_id" value="{{ old('student_id') }}" inputmode="text" pattern="\d{2}-\d{5}" placeholder="**-*****" maxlength="8" required autofocus><small>Format: 00-00000</small>@error('student_id')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Student name<input name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" placeholder="LAST NAME, FIRST NAME M.I." data-uppercase-name required><small>Use uppercase letters.</small>@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label class="field-wide">Department<select name="department" data-student-department required><option value="">Select department</option>@foreach(array_keys($catalog) as $department)<option value="{{ $department }}" @selected(old('department')===$department)>{{ $department }}</option>@endforeach</select><small>Your department controls which courses are available.</small>@error('department')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label class="field-wide">Course<select name="course" data-student-course data-selected="{{ old('course') }}" required><option value="">Select a department first</option></select><small>Only courses under the selected department can be chosen.</small>@error('course')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label class="field-wide academic-major-field {{ $registrationMajors !== [] ? 'is-visible' : '' }}" data-student-major-field aria-hidden="{{ $registrationMajors !== [] ? 'false' : 'true' }}">Major<select name="major" data-student-major data-selected="{{ old('major') }}"><option value="">Select major</option></select><small>This course requires a major.</small>@error('major')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Year level<input name="year" value="{{ old('year') }}" inputmode="numeric" pattern="[1-9]" maxlength="1" placeholder="*" title="Enter one digit from 1 to 9." required><small>Enter one digit only.</small>@error('year')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Section<input name="section" value="{{ old('section') }}" inputmode="text" pattern="[A-Z]" maxlength="1" placeholder="A" data-uppercase-letter title="Enter one uppercase letter from A to Z." required><small>Enter one uppercase letter only.</small>@error('section')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Phone number<input name="phone" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" placeholder="09** *** ****" pattern="(?:09\d{9}|\+639\d{9})" title="Use the 09** *** **** or +63 9** *** **** format." required><small>Accepted format: 09** *** **** or +63 9** *** ****.</small>@error('phone')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label class="field-wide">Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="student@example.com" required><small>This email address will be used to log in to the system, verify your account, and receive notifications.</small>@error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Password<input type="password" name="password" autocomplete="new-password" minlength="10" maxlength="128" data-registration-password required><small>Use at least 10 characters. Both passwords must match.</small>@error('password')<span class="field-error">{{ $message }}</span>@enderror</label>
                <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" maxlength="128" data-registration-password-confirm required><small data-password-match-status aria-live="polite">Re-enter the same password.</small></label>
            </div>
            <label class="student-consent"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span>I have read and agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener">Terms and Conditions</a> and <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>.</span></label>
            @error('terms')<span class="field-error consent-error">{{ $message }}</span>@enderror
            <button class="button full" type="submit">Submit and Verify Email</button>
            <div class="auth-links"><a class="text-link" href="{{ route('login') }}">Already registered? Sign in</a><a class="text-link" href="{{ route('home') }}">Return to the public page</a></div>
            @if(session('pending_registration'))<section class="resend-inline"><h3>Resend Verification Email</h3><p>Enter the email used for the pending registration below.</p></section>@endif
        </form>
        @if(session('pending_registration'))<form method="post" action="{{ route('student.verification.resend') }}" class="auth-card compact-auth-form">@csrf<label>Email address<input type="email" name="email" value="{{ old('email') }}" required></label><button class="button secondary full">Resend Verification Email</button></form>@endif
    </section>
</main>
<script type="application/json" data-student-catalog>@json($catalog)</script>
@endsection
