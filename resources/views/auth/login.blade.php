@extends('layouts.app')
@section('title', 'Sign in | CFO')
@section('body')
<main class="login-page">
    <section class="login-art"><a class="brand light" href="{{ route('home') }}"><img src="{{ asset('images/cfo-logo.png') }}" alt="CFO logo"><span>Christian Formation Office</span></a><div><p class="eyebrow">Your formation workspace</p><h1>Welcome back.</h1><p>Continue your journey, review what needs attention, and keep your formation records together.</p></div></section>
    <section class="login-panel">
        <form method="post" action="{{ route('login') }}" class="auth-card">
            @csrf
            <p class="eyebrow">Secure access</p><h2>Sign in to your account</h2><p class="muted">Students may use a verified email address or Student ID. CFO Staff and Super Admin accounts use their University ID.</p>
            @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
            <label>Email address or University ID<input name="login" value="{{ old('login', old('university_id')) }}" autocomplete="username" required autofocus></label>
            <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="button full" type="submit">Sign in</button>
            <div class="auth-links"><a class="text-link" href="{{ route('student.password.forgot') }}">Forgot Password?</a><a class="text-link" href="{{ route('student.register') }}">Create Student Account</a><a class="text-link" href="{{ route('home') }}">Return to the public page</a></div>
        </form>
        @if(session('pending_verification_email'))<form method="post" action="{{ route('student.verification.resend') }}" class="auth-card compact-auth-form">@csrf<input type="hidden" name="email" value="{{ session('pending_verification_email') }}"><p>Email verification is required for this account.</p><button class="button secondary full">Resend Verification Email</button></form>@endif
    </section>
</main>
@endsection
