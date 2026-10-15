<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request, string $section = 'overview')
    {
        $user = $request->user();
        $staff = $user->isStaff();
        $allowed = $staff ? ['overview', 'my-attendance', 'activities', 'submissions', 'facilities', 'announcements', 'reports', 'notifications'] : ['overview', 'profile', 'activities', 'submissions', 'announcements', 'notifications'];
        if ($user->isSuperAdmin()) {
            $allowed = array_merge($allowed, ['staff-attendance', 'accounts', 'settings', 'audit']);
        }
        abort_unless(in_array($section, $allowed, true), 404);
        $profile = $user->role === 'student'
            ? DB::table('profiles')->where('user_id', $user->id)->first()
            : null;

        $activities = DB::table('activities')->orderBy('activity_date');
        $announcements = DB::table('announcements')->orderByDesc('created_at');
        if ($user->role === 'student') {
            $activities->where('status', 'Published')->where('activity_date', '>=', now()->timezone('Asia/Manila')->toDateString());
            $this->forActivityAudience($activities, $profile);
            $this->forAudience($announcements, $profile);
        }

        $data = [
            'section' => $section,
            'academicCatalog' => config('student_registration.catalog', []),
            'activities' => $activities->get(),
            'announcements' => $announcements->get(),
            'notifications' => DB::table('notifications')->where('user_id', $user->id)->orderByDesc('created_at')->get(),
        ];
        if ($user->role === 'student') {
            $data['profile'] = $profile;
            $data['registrations'] = DB::table('registrations as r')->join('activities as a', 'a.id', '=', 'r.activity_id')->where('r.user_id', $user->id)->select('r.*', 'a.title', 'a.type as activity_type', 'a.activity_date', 'a.activity_time', 'a.venue')->orderByDesc('r.created_at')->get();
            $data['documents'] = DB::table('documents')->where('user_id', $user->id)->orderByDesc('created_at')->get();
            $data['requirementSubmissions'] = DB::table('requirement_submissions')->where('user_id', $user->id)->get();
        } else {
            $activeStudentProfiles = DB::table('profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')->where('u.role', 'student')->where('u.active', true);
            $data['academicCourses'] = (clone $activeStudentProfiles)
                ->where(fn ($query) => $query->whereNotNull('p.course')->orWhereNotNull('p.department'))
                ->selectRaw('COALESCE(p.course, p.department) as academic_course')
                ->distinct()
                ->orderBy('academic_course')
                ->pluck('academic_course');
            $data['academicYears'] = (clone $activeStudentProfiles)->whereNotNull('p.year')->distinct()->orderBy('p.year')->pluck('p.year');
            $data['academicSections'] = (clone $activeStudentProfiles)->whereNotNull('p.section')->distinct()->orderBy('p.section')->pluck('p.section');
            $data['profiles'] = DB::table('profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')->select('p.*', 'u.name as student_name', 'u.university_id', 'u.email')->orderBy('u.name')->get();
            $data['registrations'] = DB::table('registrations as r')->join('users as u', 'u.id', '=', 'r.user_id')->join('activities as a', 'a.id', '=', 'r.activity_id')->select('r.*', 'u.name as student_name', 'u.university_id', 'a.title', 'a.type as activity_type', 'a.activity_date')->orderByDesc('r.created_at')->get();
            $data['facilities'] = DB::table('facility_requests as f')->join('users as u', 'u.id', '=', 'f.user_id')->select('f.*', 'u.name as requester')->orderByDesc('starts_at')->get();
            $data['documents'] = DB::table('documents as d')->join('users as u', 'u.id', '=', 'd.user_id')->select('d.*', 'u.name as student_name')->orderByDesc('d.created_at')->get();
            $data['requirementSubmissions'] = DB::table('requirement_submissions as s')->join('activity_requirements as q', 'q.id', '=', 's.requirement_id')->join('users as u', 'u.id', '=', 's.user_id')->select('s.*', 'q.name as requirement_name', 'q.type as requirement_type', 'u.name as student_name')->orderByDesc('s.updated_at')->get();
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
            if ($section === 'staff-attendance') {
                $validated = $request->validate([
                    'attendance_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                ]);
                $data += $this->staffAttendanceMonitor(
                    $validated['attendance_date'] ?? now()->timezone(config('app.timezone'))->toDateString()
                );
            }
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
            'profileNeedsAction' => ! $data['profile'] || in_array($data['profile']->status, ['Not submitted', 'Requires correction'], true),
            'profilePending' => $data['profile']?->status === 'Pending verification',
            'registrationCorrections' => $data['registrations']->where('status', 'Requires correction')->count(),
            'documentCorrections' => $data['documents']->where('status', 'Requires correction')->count(),
            'requirementCorrections' => $data['requirementSubmissions']->where('status', 'Requires correction')->count(),
            'unreadNotifications' => $data['notifications']->whereNull('read_at')->count(),
            'nextRegistration' => $nextRegistration,
            'nextAvailableActivity' => $data['activities']
                ->filter(fn ($activity) => $activity->activity_date >= $today && $activity->deadline >= $today && ! $registrationIds->contains($activity->id))
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
        $overview = [
            'pendingProfiles' => $data['profiles']->where('status', 'Pending verification')->count(),
            'pendingRegistrations' => $data['registrations']->whereIn('status', ['Submitted', 'Under review'])->count(),
            'pendingDocuments' => $data['documents']->where('status', 'Pending review')->count(),
            'pendingRequirements' => $data['requirementSubmissions']->where('status', 'Submitted')->count(),
            'pendingFacilities' => $data['facilities']->where('status', 'Pending')->count(),
            'unreadNotifications' => $data['notifications']->whereNull('read_at')->count(),
            'todayActivities' => $todayActivities,
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
            if ($profile?->department) {
                $query->orWhere('department', $profile->department);
            }
        })->where(function ($query) use ($profile) {
            $query->whereNull('target_year');
            if ($profile?->year) {
                $query->orWhere('target_year', $profile->year);
            }
        })->where(function ($query) use ($profile) {
            $query->whereNull('target_section');
            if ($profile?->section) {
                $query->orWhere('target_section', $profile->section);
            }
        });
    }

    private function forActivityAudience($query, ?object $profile): void
    {
        if (! $profile) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where('department', $profile->course ?: $profile->department)
            ->where('target_year', $profile->year)
            ->where('target_section', $profile->section);
    }

    private function staffAttendanceMonitor(string $date): array
    {
        $timezone = config('app.timezone');
        $selectedDate = Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();
        $today = now()->timezone($timezone)->startOfDay();
        $workdayStart = config('app.attendance.workday_start', '08:00');
        $graceMinutes = max(0, (int) config('app.attendance.late_grace_minutes', 15));
        $lateAfter = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$workdayStart}", $timezone)
            ->addMinutes($graceMinutes);

        $roster = DB::table('users as u')
            ->leftJoin('staff_attendance as s', function ($join) use ($date) {
                $join->on('s.user_id', '=', 'u.id')->where('s.work_date', '=', $date);
            })
            ->where('u.role', 'admin')
            ->where('u.active', true)
            ->select(
                'u.id as staff_user_id',
                'u.name as staff_name',
                'u.university_id',
                's.id as attendance_id',
                's.work_date',
                's.time_in',
                's.time_out',
                's.time_in_photo_path',
                's.time_out_photo_path',
                's.status',
                's.admin_note'
            )
            ->orderBy('u.name')
            ->get()
            ->map(function ($entry) use ($selectedDate, $today, $lateAfter, $timezone) {
                $entry->monitor_status = $this->attendanceStatus($entry, $selectedDate, $today, $lateAfter, $timezone);

                return $entry;
            });

        return [
            'staffAttendanceDate' => $date,
            'staffAttendanceRoster' => $roster,
            'staffAttendanceSummary' => [
                'total' => $roster->count(),
                'present' => $roster->whereIn('monitor_status', ['Present', 'Timed in'])->count(),
                'late' => $roster->where('monitor_status', 'Late')->count(),
                'absent' => $roster->where('monitor_status', 'Absent')->count(),
                'incomplete' => $roster->where('monitor_status', 'Incomplete')->count(),
                'excused' => $roster->where('monitor_status', 'Excused')->count(),
            ],
            'staffAttendanceLateAfter' => $lateAfter->format('g:i A'),
        ];
    }

    private function attendanceStatus(object $entry, Carbon $selectedDate, Carbon $today, Carbon $lateAfter, string $timezone): string
    {
        if ($entry->status === 'Excused') {
            return 'Excused';
        }
        if ($entry->status === 'Incomplete') {
            return 'Incomplete';
        }

        if (! $entry->attendance_id) {
            if ($selectedDate->isWeekend()) {
                return 'Not scheduled';
            }
            if ($selectedDate->lt($today) || ($selectedDate->equalTo($today) && now()->timezone($timezone)->gte($lateAfter))) {
                return 'Absent';
            }

            return 'Pending';
        }

        if (! $entry->time_in) {
            return 'Incomplete';
        }
        if (Carbon::parse($entry->time_in, $timezone)->gt($lateAfter)) {
            return 'Late';
        }
        if (! $entry->time_out) {
            return $selectedDate->lt($today) ? 'Incomplete' : 'Timed in';
        }

        return 'Present';
    }
}
