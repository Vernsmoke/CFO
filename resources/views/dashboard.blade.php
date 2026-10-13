@extends('layouts.app')
@section('title', ucfirst($section).' | CFO')
@section('body')
@php
    $user = auth()->user(); $staff = $user->isStaff();
    $nav = $staff
        ? ['overview'=>['label'=>'Overview','icon'=>'overview'],'activities'=>['label'=>'Activities','icon'=>'activities'],'submissions'=>['label'=>'Submissions','icon'=>'submissions'],'attendance'=>['label'=>'Attendance','icon'=>'attendance'],'facilities'=>['label'=>'Facilities','icon'=>'facilities'],'announcements'=>['label'=>'Announcements','icon'=>'announcements'],'reports'=>['label'=>'Reports','icon'=>'reports'],'notifications'=>['label'=>'Notifications','icon'=>'notifications']]
        : ['overview'=>['label'=>'Overview','icon'=>'overview'],'profile'=>['label'=>'My profile','icon'=>'profile'],'activities'=>['label'=>'Activities','icon'=>'activities'],'submissions'=>['label'=>'My submissions','icon'=>'submissions'],'announcements'=>['label'=>'Announcements','icon'=>'announcements'],'notifications'=>['label'=>'Notifications','icon'=>'notifications']];
    if ($user->isSuperAdmin()) $nav += ['accounts'=>['label'=>'Accounts','icon'=>'accounts'],'settings'=>['label'=>'Settings','icon'=>'settings'],'audit'=>['label'=>'Audit history','icon'=>'audit']];
    $initials = collect(explode(' ', trim($user->name)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
@endphp
<div class="workspace" data-workspace>
    <svg class="nav-sprite" aria-hidden="true">
        <symbol id="nav-overview" viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9h13v-9M9.5 19v-5h5v5"/></symbol>
        <symbol id="nav-profile" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6"/></symbol>
        <symbol id="nav-activities" viewBox="0 0 24 24"><path d="M5 3v3M19 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/><path d="m8 14 2 2 5-5"/></symbol>
        <symbol id="nav-submissions" viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7zM14 3v5h5M10 12h5M10 16h5"/></symbol>
        <symbol id="nav-attendance" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></symbol>
        <symbol id="nav-facilities" viewBox="0 0 24 24"><path d="M3 20h18M5 20V9l7-5 7 5v11M9 20v-6h6v6"/></symbol>
        <symbol id="nav-announcements" viewBox="0 0 24 24"><path d="m4 13 12-5v8L4 11v2Z"/><path d="M7 14v5h4l-1-4M19 9v6"/></symbol>
        <symbol id="nav-reports" viewBox="0 0 24 24"><path d="M5 20V10M12 20V4M19 20v-7"/></symbol>
        <symbol id="nav-notifications" viewBox="0 0 24 24"><path d="M6 16h12l-1.5-2.5V10a4.5 4.5 0 0 0-9 0v3.5L6 16Z"/><path d="M10 19h4"/></symbol>
        <symbol id="nav-accounts" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="10" r="2"/><path d="M3 20c.5-4 2.5-6 6-6s5.5 2 6 6M15 15c3 0 4.5 1.5 5 4"/></symbol>
        <symbol id="nav-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.4 1a7 7 0 0 0-1.8-1L14.4 3h-4.8l-.3 3.1a7 7 0 0 0-1.8 1l-2.4-1-2 3.4 2 1.5a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.4-1a7 7 0 0 0 1.8 1l.3 3.1h4.8l.3-3.1a7 7 0 0 0 1.8-1l2.4 1 2-3.4-2-1.5a7 7 0 0 0 .1-1Z"/></symbol>
        <symbol id="nav-audit" viewBox="0 0 24 24"><path d="M6 4h12v16H6zM9 8h6M9 12h6M9 16h4"/></symbol>
        <symbol id="nav-collapse" viewBox="0 0 24 24"><path d="M4 5h16v14H4zM9 5v14M15 9l-3 3 3 3"/></symbol>
        <symbol id="nav-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
        <symbol id="nav-signout" viewBox="0 0 24 24"><path d="M10 5H5v14h5M14 8l4 4-4 4M8 12h10"/></symbol>
    </svg>
    <button class="sidebar-backdrop" type="button" data-menu-close aria-label="Close navigation" tabindex="-1"></button>
    <aside class="sidebar" id="sidebar" aria-label="Workspace navigation">
        <button class="sidebar-close" type="button" data-menu-close aria-label="Close navigation"><svg aria-hidden="true"><use href="#nav-close"></use></svg></button>
        <a class="brand light" href="{{ route('dashboard') }}" aria-label="Christian Formation Office overview"><img src="{{ asset('images/cfo-logo.png') }}" alt=""><span>Christian Formation Office</span></a>
        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Collapse navigation" aria-expanded="true" aria-controls="main-navigation"><svg aria-hidden="true"><use href="#nav-collapse"></use></svg><span>Collapse navigation</span></button>
        <nav id="main-navigation" aria-label="Main navigation">@foreach($nav as $key => $item)<a class="nav-link {{ $section === $key ? 'active' : '' }}" href="{{ route('dashboard.section', $key) }}" aria-label="{{ $item['label'] }}" @if($section === $key) aria-current="page" @endif data-label="{{ $item['label'] }}"><span class="nav-icon"><svg aria-hidden="true"><use href="#nav-{{ $item['icon'] }}"></use></svg></span><span class="nav-label">{{ $item['label'] }}</span></a>@endforeach</nav>
        <div class="sidebar-user"><span class="user-avatar" aria-hidden="true">{{ $initials }}</span><div class="user-details"><span>{{ $user->name }}</span><small>{{ str_replace('_', ' ', ucfirst($user->role)) }} | {{ $user->university_id }}</small></div><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" aria-label="Sign out" data-label="Sign out"><svg aria-hidden="true"><use href="#nav-signout"></use></svg><span>Sign out</span></button></form></div>
    </aside>
    <main class="content" id="main-content" tabindex="-1">
        <header class="workspace-header"><button class="menu" type="button" data-menu aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><span></span><span></span><span></span></button><div><p class="eyebrow">CFO Workspace</p><h1>{{ $nav[$section]['label'] ?? ucfirst($section) }}</h1></div><span class="date">{{ now()->timezone('Asia/Manila')->format('M j, Y') }}</span></header>
        @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert error"><strong>Please check your entry.</strong><span>{{ $errors->first() }}</span></div>@endif

        @if($section === 'overview')
            <section class="welcome-card"><div><p class="eyebrow">Good to see you</p><h2>{{ $user->name }}</h2><p>{{ $staff ? 'Review office workflows and keep formation activities moving.' : 'Your next formation step is ready when you are.' }}</p></div><img src="{{ asset('images/reflection.webp') }}" alt="Quiet reflection"></section>
            <div class="metrics">
                <article><span>Published activities</span><strong>{{ $activities->where('status','Published')->count() }}</strong></article>
                <article><span>{{ $staff ? 'Registrations' : 'My submissions' }}</span><strong>{{ $registrations->count() }}</strong></article>
                <article><span>Unread updates</span><strong>{{ $notifications->whereNull('read_at')->count() }}</strong></article>
            </div>
            <section class="panel"><div class="panel-heading"><div><p class="eyebrow">Coming up</p><h2>Formation activities</h2></div><a class="text-link" href="{{ route('dashboard.section','activities') }}">View all</a></div>
                <div class="cards">@forelse($activities->where('status','Published')->take(3) as $activity)<article class="activity-card"><span class="badge">{{ $activity->type }}</span><h3>{{ $activity->title }}</h3><p>{{ \Illuminate\Support\Str::limit($activity->description, 120) }}</p><small>{{ \Carbon\Carbon::parse($activity->activity_date)->format('M j, Y') }} | {{ $activity->venue }}</small></article>@empty<p class="empty">No published activities yet.</p>@endforelse</div>
            </section>

        @elseif($section === 'profile')
            <section class="panel narrow"><p class="eyebrow">Student record</p><h2>Keep your profile current</h2><p class="muted">Your department, year, and section help the office show the right activities.</p>
                <form method="post" action="{{ route('profile.save') }}" class="form-grid">@csrf
                    <label>Department<input name="department" value="{{ old('department', $profile->department ?? '') }}" required></label>
                    <label>Year level<input name="year" value="{{ old('year', $profile->year ?? '') }}" required></label>
                    <label>Section<input name="section" value="{{ old('section', $profile->section ?? '') }}" required></label>
                    <label>Contact information<input name="contact" value="{{ old('contact', $profile->contact ?? '') }}" required></label>
                    <div class="span-2"><button class="button">Submit profile</button>@if($profile)<span class="badge">{{ $profile->status }}</span>@endif</div>
                </form>
            </section>

        @elseif($section === 'activities')
            @if($staff)<details class="panel form-panel"><summary>Create an activity</summary><form method="post" action="{{ route('activities.create') }}" class="form-grid">@csrf
                <label>Title<input name="title" required></label><label>Type<select name="type"><option>Retreat</option><option>Recollection</option><option>Liturgy</option><option>Formation program</option></select></label>
                <label>Date<input type="date" name="activity_date" required></label><label>Time<input type="time" name="activity_time" required></label>
                <label>Venue<input name="venue" required></label><label>Registration deadline<input type="date" name="deadline" required></label>
                <label>Department<input name="department" value="All departments" required></label><label>Capacity<input type="number" name="capacity" value="100" min="1" required></label>
                <label>Status<select name="status"><option>Draft</option><option>Published</option><option>Postponed</option><option>Cancelled</option></select></label><label>Required documents<textarea name="required_documents" placeholder="One requirement per line"></textarea></label>
                <label class="span-2">Description<textarea name="description" required></textarea></label><label class="span-2">Other requirements<textarea name="requirements"></textarea></label><button class="button">Create activity</button>
            </form></details>@endif
            <div class="cards">@forelse($activities as $activity)<article class="activity-card"><div class="between"><span class="badge">{{ $activity->type }}</span><span class="status">{{ $activity->status }}</span></div><h2>{{ $activity->title }}</h2><p>{{ $activity->description }}</p><dl><div><dt>Date</dt><dd>{{ \Carbon\Carbon::parse($activity->activity_date)->format('M j, Y') }} at {{ \Carbon\Carbon::parse($activity->activity_time)->format('g:i A') }}</dd></div><div><dt>Venue</dt><dd>{{ $activity->venue }}</dd></div><div><dt>Requirements</dt><dd>{{ $activity->requirements ?: 'None listed' }}</dd></div></dl>@if(!$staff && $activity->status === 'Published')<form method="post" action="{{ route('activities.register',$activity->id) }}">@csrf<button class="button">Register</button></form>@endif</article>@empty<p class="empty">No activities yet.</p>@endforelse</div>

        @elseif($section === 'submissions')
            <div class="stack">@forelse($registrations as $registration)<article class="panel record"><div class="between"><div><p class="eyebrow">{{ $staff ? $registration->university_id : 'Activity registration' }}</p><h2>{{ $registration->title }}</h2>@if($staff)<p>{{ $registration->student_name }}</p>@endif</div><span class="badge">{{ $registration->status }}</span></div>
                @if($registration->note)<p class="note">{{ $registration->note }}</p>@endif
                @if($staff)<form method="post" action="{{ route('registrations.review',$registration->id) }}" class="inline-form">@csrf @method('PATCH')<select name="status"><option>Under review</option><option>Requires correction</option><option>Accepted</option><option>Withdrawn</option></select><input name="note" placeholder="Review note"><button class="button small">Save review</button></form>
                @else<form method="post" action="{{ route('documents.upload',$registration->id) }}" enctype="multipart/form-data" class="inline-form">@csrf<input name="requirement" placeholder="Requirement name"><input type="file" name="document" accept=".pdf,.png,.jpg,.jpeg" required><button class="button small">Upload document</button></form>@endif
                @foreach($documents->where('registration_id',$registration->id) as $document)<div class="document-row"><a href="{{ route('documents.download',$document->id) }}">{{ $document->original_name }}</a><span>{{ $document->status }}</span>@if($staff)<form method="post" action="{{ route('documents.review',$document->id) }}" class="inline-form">@csrf @method('PATCH')<select name="status"><option>Accepted</option><option>Requires correction</option></select><input name="note" placeholder="Document note"><button class="button small">Review</button></form>@endif</div>@endforeach
            </article>@empty<p class="empty panel">No registrations found.</p>@endforelse</div>

        @elseif($section === 'attendance')
            <section class="panel"><h2>Attendance checkpoints</h2><div class="table-wrap"><table><thead><tr><th>Student</th><th>Activity</th><th>Status</th><th>Time in</th><th>Time out</th><th>Action</th></tr></thead><tbody>@foreach($registrations as $registration)@php($entry=$attendance->get($registration->id))<tr><td>{{ $registration->student_name }}<small>{{ $registration->university_id }}</small></td><td>{{ $registration->title }}</td><td>{{ $registration->status }}</td><td>{{ $entry?->time_in ?? 'Not recorded' }}</td><td>{{ $entry?->time_out ?? 'Not recorded' }}</td><td><form method="post" action="{{ route('attendance.record',$registration->id) }}" class="button-group">@csrf<button class="button small" name="checkpoint" value="in">Time in</button><button class="button small secondary" name="checkpoint" value="out">Time out</button></form></td></tr>@endforeach</tbody></table></div></section>

        @elseif($section === 'facilities')
            <details class="panel form-panel"><summary>Request a facility</summary><form method="post" action="{{ route('facilities.create') }}" class="form-grid">@csrf<label>Facility<input name="facility" required></label><label>Expected participants<input type="number" min="1" name="participants" required></label><label>Start<input type="datetime-local" name="starts_at" required></label><label>End<input type="datetime-local" name="ends_at" required></label><label class="span-2">Purpose<textarea name="purpose" required></textarea></label><button class="button">Submit request</button></form></details>
            <div class="stack">@forelse($facilities as $facility)<article class="panel record"><div class="between"><div><h2>{{ $facility->facility }}</h2><p>{{ $facility->requester }} | {{ $facility->participants }} participants</p></div><span class="badge">{{ $facility->status }}</span></div><p>{{ $facility->purpose }}</p><small>{{ $facility->starts_at }} to {{ $facility->ends_at }}</small><form method="post" action="{{ route('facilities.decide',$facility->id) }}" class="inline-form">@csrf @method('PATCH')<select name="status"><option>Approved</option><option>Rejected</option><option>Returned for correction</option></select><input name="note" placeholder="Decision note"><button class="button small">Save decision</button></form></article>@empty<p class="panel empty">No facility requests.</p>@endforelse</div>

        @elseif($section === 'announcements')
            @if($staff)<details class="panel form-panel"><summary>Publish an announcement</summary><form method="post" action="{{ route('announcements.create') }}" class="form-grid">@csrf<label>Title<input name="title" required></label><label>Audience<input name="department" value="All departments" required></label><label class="span-2">Announcement<textarea name="body" required></textarea></label><button class="button">Publish</button></form></details>@endif
            <div class="stack">@forelse($announcements as $announcement)<article class="panel"><div class="between"><span class="eyebrow">CFO update</span><small>{{ \Carbon\Carbon::parse($announcement->created_at)->format('M j, Y') }}</small></div><h2>{{ $announcement->title }}</h2><p>{{ $announcement->body }}</p><span class="badge">{{ $announcement->department }}</span></article>@empty<p class="empty panel">No announcements.</p>@endforelse</div>

        @elseif($section === 'notifications')
            <form method="post" action="{{ route('notifications.read') }}" class="align-right">@csrf<button class="button secondary">Mark all as read</button></form><div class="stack">@forelse($notifications as $notification)<article class="panel notification {{ $notification->read_at ? '' : 'unread' }}"><p>{{ $notification->message }}</p><small>{{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}</small></article>@empty<p class="empty panel">No updates yet.</p>@endforelse</div>

        @elseif($section === 'reports')
            <div class="metrics"><article><span>Registrations</span><strong>{{ $registrations->count() }}</strong></article><article><span>Accepted</span><strong>{{ $registrations->where('status','Accepted')->count() }}</strong></article><article><span>Needs correction</span><strong>{{ $registrations->where('status','Requires correction')->count() }}</strong></article></div><section class="panel"><div class="panel-heading"><div><p class="eyebrow">Authorized records</p><h2>Participation report</h2></div><a class="button" href="{{ route('reports.export') }}">Export CSV</a></div><div class="table-wrap"><table><thead><tr><th>Student</th><th>Activity</th><th>Date</th><th>Status</th></tr></thead><tbody>@foreach($registrations as $registration)<tr><td>{{ $registration->student_name }}</td><td>{{ $registration->title }}</td><td>{{ $registration->activity_date }}</td><td>{{ $registration->status }}</td></tr>@endforeach</tbody></table></div></section>

        @elseif($section === 'accounts')
            <div class="stack">@foreach($users as $account)<article class="panel record"><div class="between"><div><h2>{{ $account->name }}</h2><p>{{ $account->university_id }}</p></div><span class="badge">{{ $account->active ? 'Active' : 'Disabled' }}</span></div>@if($account->id !== $user->id)<form method="post" action="{{ route('accounts.update',$account->id) }}" class="inline-form">@csrf @method('PATCH')<select name="role"><option value="student" @selected($account->role==='student')>Student</option><option value="admin" @selected($account->role==='admin')>Admin</option><option value="super_admin" @selected($account->role==='super_admin')>Super Admin</option></select><select name="active"><option value="1" @selected($account->active)>Active</option><option value="0" @selected(!$account->active)>Disabled</option></select><input type="password" name="password" placeholder="New password (optional)"><button class="button small">Update account</button></form>@else<p class="muted">Your own access cannot be changed here.</p>@endif</article>@endforeach</div>

        @elseif($section === 'audit')
            <section class="panel"><h2>Audit history</h2><div class="table-wrap"><table><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Detail</th></tr></thead><tbody>@foreach($audits as $audit)<tr><td>{{ $audit->created_at }}</td><td>{{ $audit->actor ?? 'System' }}</td><td>{{ $audit->action }}</td><td>{{ $audit->target }}</td><td>{{ $audit->detail }}</td></tr>@endforeach</tbody></table></div></section>

        @elseif($section === 'settings')
            <section class="panel narrow"><p class="eyebrow">Office configuration</p><h2>System settings</h2><p>Branding uses the existing CFO logo in <code>public/images/cfo-logo.png</code>. Database and mail settings are managed securely in the Laravel <code>.env</code> file.</p><div class="note">For production, configure backups, email delivery, approved facilities, and university identity procedures before launch.</div></section>
        @endif
    </main>
</div>
@endsection
