@extends('layouts.app')
@section('title', 'Sign in | CFO')
@section('body')
<main class="login-page">
    <section class="login-art"><a class="brand light" href="{{ route('home') }}"><img src="{{ asset('images/cfo-logo.png') }}" alt="CFO logo"><span>Christian Formation Office</span></a><div><p class="eyebrow">Your formation workspace</p><h1>Welcome back.</h1><p>Continue your journey, review what needs attention, and keep your formation records together.</p></div></section>
    <section class="login-panel">
        <form method="post" action="{{ route('login') }}" class="auth-card">
            @csrf
            <p class="eyebrow">Secure access</p><h2>Sign in to your account</h2><p class="muted">Use the University ID Number provided for your account.</p>
            @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
            <label>University ID Number<input name="university_id" value="{{ old('university_id') }}" autocomplete="username" required autofocus></label>
            <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="button full" type="submit">Sign in</button>
            <a class="text-link" href="{{ route('home') }}">Return to the public page</a>
        </form>
    </section>
</main>
@endsection
