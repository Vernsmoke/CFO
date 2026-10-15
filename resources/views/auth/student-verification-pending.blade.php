@extends('layouts.app')
@section('title', 'Verify Your Email | CFO')
@section('body')
<main class="login-page">
    <section class="login-art"><a class="brand light" href="{{ route('home') }}"><img src="{{ asset('images/cfo-logo.png') }}" alt="CFO logo"><span>Christian Formation Office</span></a><div><p class="eyebrow">One more step</p><h1>Check your inbox.</h1><p>Your student account stays protected and inactive until the email address is verified.</p></div></section>
    <section class="login-panel"><div class="auth-card"><p class="eyebrow">Pending verification</p><h2>Registration received</h2>@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif<p>Use the secure verification button sent to <strong>{{ session('verification_email', 'your email address') }}</strong>. The link is single-use and expires for your protection.</p><form method="post" action="{{ route('student.verification.resend') }}" class="resend-form">@csrf<label>Email address<input type="email" name="email" value="{{ old('email', session('verification_email')) }}" required>@error('email')<span class="field-error">{{ $message }}</span>@enderror</label><button class="button secondary full">Resend Verification Email</button></form><div class="auth-links"><a class="text-link" href="{{ route('login') }}">Go to sign in</a><a class="text-link" href="{{ route('home') }}">Return to the public page</a></div></div></section>
</main>
@endsection
