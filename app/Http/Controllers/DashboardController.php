<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request, string $section = 'overview')
    {
        $user = $request->user(); $staff = $user->isStaff();
        $allowed = $staff ? ['overview','my-attendance','activities','submissions','attendance','facilities','announcements','reports','notifications'] : ['overview','profile','activities','submissions','announcements','notifications'];
        if ($user->isSuperAdmin()) $allowed = array_merge($allowed, ['staff-attendance','accounts','settings','audit']);
        abort_unless(in_array($section, $allowed, true), 404);
        $profile = $user->role === 'student'
            ? DB::table('profiles')->where('user_id', $user->id)->first()
            : null;

        $activities = DB::table('activities')->orderBy('activity_date');
        $announcements = DB::table('announcements')->orderByDesc('created_at');
        if ($user->role === 'student') {
            $activities->where('status', 'Published')->where('activity_date', '>=', now()->timezone('Asia/Manila')->toDateString());
            $this->forAudience($activities, $profile);
            $this->forAudience($announcements, $profile);
        }

        $data = [
            'section' => $section,
            'activities' => $activities->get(),
            'announcements' => $announcements->get(),
            'notifications' => DB::table('notifications')->where('user_id', $user->id)->orderByDesc('created_at')->get(),
        ];
        if ($user->role === 'student') {
            $data['profile'] = $profile;
            $data['registrations'] = DB::table('registrations as r')->join('activities as a', 'a.id', '=', 'r.activity_id')->where('r.user_id', $user->id)->select('r.*', 'a.title', 'a.activity_date', 'a.activity_time', 'a.venue')->orderByDesc('r.created_at')->get();
            $data['documents'] = DB::table('documents')->where('user_id', $user->id)->orderByDesc('created_at')->get();
            $data['requirementSubmissions'] = DB::table('requirement_submissions')->where('user_id', $user->id)->get();
        } else {
            $data['profiles'] = DB::table('profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')->select('p.*', 'u.name as student_name', 'u.university_id', 'u.email')->orderBy('u.name')->get();
            $data['registrations'] = DB::table('registrations as r')->join('users as u', 'u.id', '=', 'r.user_id')->join('activities as a', 'a.id', '=', 'r.activity_id')->select('r.*', 'u.name as student_name', 'u.university_id', 'a.title', 'a.activity_date')->orderByDesc('r.created_at')->get();
            $data['facilities'] = DB::table('facility_requests as f')->join('users as u', 'u.id', '=', 'f.user_id')->select('f.*', 'u.name as requester')->orderByDesc('starts_at')->get();
            $data['documents'] = DB::table('documents as d')->join('users as u', 'u.id', '=', 'd.user_id')->select('d.*', 'u.name as student_name')->orderByDesc('d.created_at')->get();
            $data['requirementSubmissions'] = DB::table('requirement_submissions as s')->join('activity_requirements as q', 'q.id', '=', 's.requirement_id')->join('users as u', 'u.id', '=', 's.user_id')->select('s.*', 'q.name as requirement_name', 'q.type as requirement_type', 'u.name as student_name')->orderByDesc('s.updated_at')->get();
            $data['attendance'] = DB::table('attendance')->get()->keyBy('registration_id');
            $data['staffAttendanceToday'] = DB::table('staff_attendance')->where('user_id', $user->id)->where('work_date', now()->timezone('Asia/Manila')->toDateString())->first();
            $data['staffAttendanceHistory'] = DB::table('staff_attendance')->where('user_id', $user->id)->orderByDesc('work_date')->limit(30)->get();
        }
        $data['activityFormFields'] = DB::table('activity_form_fields')->orderBy('sort_order')->get()->groupBy('activity_id');
        $data['activityRequirements'] = DB::table('activity_requirements')->orderBy('sort_order')->get()->groupBy('activity_id');
        $data['registrationAnswers'] = DB::table('registration_answers as a')->join('activity_form_fields as f', 'f.id', '=', 'a.field_id')->select('a.*', 'f.label', 'f.section_name')->orderBy('f.sort_order')->get()->groupBy('registration_id');
        $data['registrationCounts'] = DB::table('registrations')->where('status', '!=', 'Withdrawn')->select('activity_id', DB::raw('count(*) as total'))->groupBy('activity_id')->pluck('total', 'activity_id');
        if ($user->isSuperAdmin()) {
            $data['users'] = DB::table('users')->orderBy('name')->get();
            $data['audits'] = DB::table('audit_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.actor_id')->select('l.*', 'u.name as actor')->orderByDesc('l.created_at')->limit(200)->get();
            $data['staffAttendanceRecords'] = DB::table('staff_attendance as s')->join('users as u', 'u.id', '=', 's.user_id')->select('s.*', 'u.name as staff_name', 'u.university_id')->orderByDesc('s.work_date')->orderBy('u.name')->limit(300)->get();
        }
        if ($section === 'overview') {
            $today = now()->toDateString();
            $data['overview'] = $user->role === 'student'
                ? $this->studentOverview($data, $today)
                : $this->staffOverview($data, $today, $user->isSuperAdmin());
        }
        return view('dashboard', $data);
    }

    private function studentOverview(array $data, string $today): array
    {
        $registrationIds = $data['registrations']->pluck('activity_id');
        $nextRegistration = $data['registrations']
            ->where('status', 'Accepted')
            ->filter(fn ($registration) => $registration->activity_date >= $today)
            ->sortBy('activity_date')
            ->first();

        return [
            'profileNeedsAction' => !$data['profile'] || in_array($data['profile']->status, ['Not submitted', 'Requires correction'], true),
            'profilePending' => $data['profile']?->status === 'Pending verification',
            'registrationCorrections' => $data['registrations']->where('status', 'Requires correction')->count(),
            'documentCorrections' => $data['documents']->where('status', 'Requires correction')->count(),
            'requirementCorrections' => $data['requirementSubmissions']->where('status', 'Requires correction')->count(),
            'unreadNotifications' => $data['notifications']->whereNull('read_at')->count(),
            'nextRegistration' => $nextRegistration,
            'nextAvailableActivity' => $data['activities']
                ->filter(fn ($activity) => $activity->activity_date >= $today && $activity->deadline >= $today && !$registrationIds->contains($activity->id))
                ->sortBy('activity_date')
                ->first(),
            'latestAnnouncement' => $data['announcements']->first(),
        ];
    }

    private function staffOverview(array $data, string $today, bool $superAdmin): array
    {
        $todayActivities = $data['activities']
            ->where('status', 'Published')
            ->where('activity_date', $today)
            ->values();
        $acceptedToday = $data['registrations']
            ->where('status', 'Accepted')
            ->where('activity_date', $today);

        $overview = [
            'pendingProfiles' => $data['profiles']->where('status', 'Pending verification')->count(),
            'pendingRegistrations' => $data['registrations']->whereIn('status', ['Submitted', 'Under review'])->count(),
            'pendingDocuments' => $data['documents']->where('status', 'Pending review')->count(),
            'pendingRequirements' => $data['requirementSubmissions']->where('status', 'Submitted')->count(),
            'pendingFacilities' => $data['facilities']->where('status', 'Pending')->count(),
            'unreadNotifications' => $data['notifications']->whereNull('read_at')->count(),
            'todayActivities' => $todayActivities,
            'attendanceNeeded' => $acceptedToday->filter(function ($registration) use ($data) {
                $entry = $data['attendance']->get($registration->id);
                return !$entry?->time_in || !$entry?->time_out;
            })->count(),
            'upcomingActivities' => $data['activities']
                ->where('status', 'Published')
                ->filter(fn ($activity) => $activity->activity_date > $today)
                ->sortBy('activity_date')
                ->take(3)
                ->values(),
        ];

        if ($superAdmin) {
            $overview['accounts'] = [
                'total' => $data['users']->count(),
                'disabled' => $data['users']->where('active', false)->count(),
                'admins' => $data['users']->whereIn('role', ['admin', 'super_admin'])->count(),
            ];
            $overview['recentAudits'] = $data['audits']->take(4)->values();
        }

        return $overview;
    }

    private function forAudience($query, ?object $profile): void
    {
        $query->where(function ($query) use ($profile) {
            $query->where('department', 'All departments');
            if ($profile?->department) $query->orWhere('department', $profile->department);
        })->where(function ($query) use ($profile) {
            $query->whereNull('target_year');
            if ($profile?->year) $query->orWhere('target_year', $profile->year);
        })->where(function ($query) use ($profile) {
            $query->whereNull('target_section');
            if ($profile?->section) $query->orWhere('target_section', $profile->section);
        });
    }
}
