@extends('layouts.app')
@section('title', 'Reset Password | CFO')
@section('body')
<main class="login-page">
    <section class="login-art"><a class="brand light" href="{{ route('home') }}"><img src="{{ asset('images/cfo-logo.png') }}" alt="CFO logo"><span>Christian Formation Office</span></a><div><p class="eyebrow">Secure recovery</p><h1>Choose a new password.</h1><p>Your reset link is single-use and time-limited.</p></div></section>
    <section class="login-panel"><div class="auth-card"><p class="eyebrow">Student account</p><h2>Reset password</h2>@if(!$valid)<div class="alert error">This password reset link is invalid, expired, or already used.</div><a class="button secondary full" href="{{ route('student.password.forgot') }}">Request a new reset link</a>@else<form method="post" action="{{ route('student.password.reset') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">@error('token')<div class="alert error">{{ $message }}</div>@enderror<label>New password<input type="password" name="password" autocomplete="new-password" minlength="10" maxlength="128" required autofocus><small>Use at least 10 characters.</small>@error('password')<span class="field-error">{{ $message }}</span>@enderror</label><label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" maxlength="128" required></label><button class="button full">Update password</button></form>@endif<a class="text-link" href="{{ route('login') }}">Return to sign in</a></div></section>
</main>
@endsection
