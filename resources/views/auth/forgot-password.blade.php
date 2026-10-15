@extends('layouts.app')
@section('title', 'Forgot Password | CFO')
@section('body')
<main class="login-page">
    <section class="login-art"><a class="brand light" href="{{ route('home') }}"><img src="{{ asset('images/cfo-logo.png') }}" alt="CFO logo"><span>Christian Formation Office</span></a><div><p class="eyebrow">Secure recovery</p><h1>Let’s get you back.</h1><p>Password recovery is available for active students with a verified email address.</p></div></section>
    <section class="login-panel"><form method="post" action="{{ route('student.password.email') }}" class="auth-card">@csrf<p class="eyebrow">Student accounts</p><h2>Forgot your password?</h2><p class="muted">Enter your verified email. If it matches an active student account, we’ll send a secure reset link.</p>@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif<label>Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>@error('email')<span class="field-error">{{ $message }}</span>@enderror</label><button class="button full">Send recovery instructions</button><a class="text-link" href="{{ route('login') }}">Return to sign in</a></form></section>
</main>
@endsection
