@extends('layouts.app')
@section('title', 'Christian Formation Office')
@section('body')
<div class="public-shell" id="top">
    <header class="public-nav wrap">
        <a class="brand light" href="{{ route('home') }}" aria-label="Christian Formation Office home"><img src="{{ asset('images/cfo-logo.png') }}" alt=""><span>Christian Formation Office</span></a>
        <nav class="public-links" aria-label="Primary navigation">
            <a href="#services">Services</a>
            <a href="#journey">Your journey</a>
            <a class="button small" href="{{ route('student.register') }}">Create Student Account</a>
        </nav>
    </header>

    <main>
        <section class="hero wrap" aria-labelledby="hero-title">
            <div class="hero-copy" data-reveal>
                <p class="eyebrow">University of La Salette, Inc. / CFO</p>
                <h1 id="hero-title">Formation, <span class="highlight">where you are.</span></h1>
                <p class="lead">Find activities, submit requirements, and follow every update in one secure workspace.</p>
                <a class="hero-explore" href="#services">Explore what the office can help you do <span aria-hidden="true">↓</span></a>
            </div>

            <div class="hero-visual" data-reveal>
                <div class="hero-access-card">
                    <figure class="hero-access-photo">
                        <img src="{{ asset('images/chapel.webp') }}" alt="A peaceful university chapel">
                        <figcaption><strong>Faith in action</strong><span>Campus formation, connected.</span></figcaption>
                    </figure>
                    <form method="post" action="{{ route('login') }}" class="hero-login" id="sign-in" aria-labelledby="sign-in-title">
                        @csrf
                        <div class="hero-login-heading">
                            <div><p class="eyebrow">Secure access</p><h2 id="sign-in-title">Sign in to your workspace</h2></div>
                            <span>Student and staff accounts</span>
                        </div>
                        @if($errors->any())<div class="hero-login-error" role="alert">{{ $errors->first() }}</div>@endif
                        <div class="hero-login-fields">
                            <label>Email address or University ID<input name="login" value="{{ old('login', old('university_id')) }}" autocomplete="username" placeholder="student@example.com or CFO-001" required></label>
                            <label>Password<input type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required></label>
                            <button class="button" type="submit">Sign in <span aria-hidden="true">→</span></button>
                        </div>
                        <div class="hero-account-links"><a href="{{ route('student.register') }}">Create Student Account</a><a href="{{ route('student.password.forgot') }}">Forgot Password?</a></div>
                    </form>
                </div>
                <span class="visual-stamp stamp-one">Belong</span>
            </div>

            <dl class="hero-summary" aria-label="Workspace summary" data-reveal>
                <div><dt>Find</dt><dd>Activities made for you</dd></div>
                <div><dt>Share</dt><dd>Requirements in one place</dd></div>
                <div><dt>Follow</dt><dd>Clear status updates</dd></div>
            </dl>
        </section>
    </main>
</div>

<section id="services" class="services-section" aria-labelledby="services-title">
    <div class="wrap">
        <div class="section-heading" data-reveal>
            <p class="eyebrow">One connected office</p>
            <h2 id="services-title">Everything your formation journey needs.</h2>
            <p>Three simple ways to stay connected with the Christian Formation Office.</p>
        </div>

        <div class="service-showcase">
            <article class="service-card service-primary" data-reveal>
                <img src="{{ asset('images/retreat.webp') }}" alt="Students gathered for a formation retreat">
                <div class="service-body"><span>01 / Discover</span><h3>Meet the moment.</h3><p>Find retreats, recollections, liturgies, and programs intended for your department and year.</p></div>
            </article>
            <article class="service-card" data-reveal>
                <img src="{{ asset('images/reflection.webp') }}" alt="A quiet moment of reflection">
                <div class="service-body"><span>02 / Submit</span><h3>Keep every requirement together.</h3><p>Register and send required documents through your private workspace.</p></div>
            </article>
            <article class="service-card service-accent" data-reveal>
                <div class="service-body"><span>03 / Follow</span><h3>Know what happens next.</h3><p>See review results, corrections, reminders, and office announcements without chasing updates.</p><a class="text-link" href="#sign-in">Sign in</a></div>
            </article>
        </div>
    </div>
</section>

<section id="journey" class="journey-section" aria-labelledby="journey-title">
    <div class="wrap journey-layout">
        <div class="journey-intro" data-reveal>
            <h2 id="journey-title">One path.<br><span>Less friction.</span></h2>
            <p>The workspace keeps the practical details out of the way so you can focus on showing up.</p>
        </div>
        <ol class="journey-steps">
            <li data-reveal><span>01</span><div><h3>Choose an activity</h3><p>See the programs available to you and check every requirement before registering.</p></div></li>
            <li data-reveal><span>02</span><div><h3>Complete your record</h3><p>Submit details and documents securely, then correct anything the office sends back.</p></div></li>
            <li data-reveal><span>03</span><div><h3>Stay connected</h3><p>Return for confirmations, reminders, and the next opportunity to take part.</p></div></li>
        </ol>
    </div>
</section>

<section class="closing-section">
    <div class="wrap closing-inner" data-reveal>
        <p>Your formation workspace is ready.</p>
        <h2>Take the next step.</h2>
        <a class="button" href="#sign-in">Sign in</a>
    </div>
</section>

<footer class="public-footer">
    <div class="wrap"><a class="brand" href="#top"><img src="{{ asset('images/cfo-logo.png') }}" alt=""><span>Christian Formation Office</span></a><p>University of La Salette, Inc.</p><a href="#top">Back to top</a></div>
</footer>
@endsection
