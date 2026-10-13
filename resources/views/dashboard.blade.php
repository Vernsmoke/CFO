@extends('layouts.app')
@section('title', ucfirst($section).' | CFO')
@section('body')
@php
    $user = auth()->user(); $staff = $user->isStaff();
    $nav = $staff
        ? ['overview'=>'Overview','activities'=>'Activities','submissions'=>'Submissions','attendance'=>'Attendance','facilities'=>'Facilities','announcements'=>'Announcements','reports'=>'Reports','notifications'=>'Notifications']
        : ['overview'=>'Overview','profile'=>'My profile','activities'=>'Activities','submissions'=>'My submissions','announcements'=>'Announcements','notifications'=>'Notifications'];
    if ($user->isSuperAdmin()) $nav += ['accounts'=>'Accounts','settings'=>'Settings','audit'=>'Audit history'];
@endphp
<div class="workspace">
    <aside class="sidebar" id="sidebar">
        <a class="brand light" href="{{ route('dashboard') }}"><img src="{{ asset('images/cfo-logo.png') }}" alt="CFO logo"><span>Christian Formation Office</span></a>
        <nav>@foreach($nav as $key => $label)<a class="{{ $section === $key ? 'active' : '' }}" href="{{ route('dashboard.section', $key) }}">{{ $label }}</a>@endforeach</nav>
        <div class="sidebar-user"><span>{{ $user->name }}</span><small>{{ str_replace('_', ' ', ucfirst($user->role)) }} | {{ $user->university_id }}</small><form method="post" action="{{ route('logout') }}">@csrf<button>Sign out</button></form></div>
    </aside>
    <main class="content">
        <header class="workspace-header"><button class="menu" type="button" data-menu aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="false"><span></span><span></span><span></span></button><div><p class="eyebrow">CFO Workspace</p><h1>{{ $nav[$section] ?? ucfirst($section) }}</h1></div><span class="date">{{ now()->timezone('Asia/Manila')->format('M j, Y') }}</span></header>
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
