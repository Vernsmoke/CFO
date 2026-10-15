@extends('layouts.app')
@section('title', $verified ? 'Account Verified | CFO' : 'Verification Link Invalid | CFO')
@section('body')
<main class="login-page">
    <section class="login-art"><a class="brand light" href="{{ route('home') }}"><img src="{{ asset('images/cfo-logo.png') }}" alt="CFO logo"><span>Christian Formation Office</span></a><div><p class="eyebrow">Student account</p><h1>{{ $verified ? 'You’re verified.' : 'Link unavailable.' }}</h1><p>{{ $verified ? 'Your formation workspace is ready when you are.' : 'Account links are time-limited and can only be used once.' }}</p></div></section>
    <section class="login-panel"><div class="auth-card"><p class="eyebrow">Email verification</p><h2>{{ $verified ? 'Student account successfully created' : 'Verification link invalid or expired' }}</h2>@if($verified)<div class="alert success">Your email is verified and your student account is now active.</div><a class="button full" href="{{ route('login') }}">Continue to sign in</a>@else<div class="alert error">This link is invalid, expired, or has already been used. It did not activate an account.</div><a class="button secondary full" href="{{ route('student.verification.pending') }}">Request another verification email</a>@endif</div></section>
</main>
@endsection
