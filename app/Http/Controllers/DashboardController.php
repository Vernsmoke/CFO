<?php

namespace App\Http\Controllers;

use App\Support\RegistrationReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(Request $request, string $section = 'overview')
    {
        $user = $request->user();
        $staff = $user->isStaff();
        $allowed = $staff ? ['overview', 'my-attendance', 'activities', 'submissions', 'announcements', 'reports', 'notifications'] : ['overview', 'profile', 'activities', 'submissions', 'announcements', 'notifications'];
        if ($user->isSuperAdmin()) {
            $allowed = array_merge($allowed, ['staff-attendance', 'accounts', 'settings', 'audit']);
        }
        abort_unless(in_array($section, $allowed, true), 404);
        $profile = $user->role === 'student'
            ? DB::table('profiles')->where('user_id', $user->id)->first()
            : null;

        $activities = DB::table('activities as a')
            ->leftJoin('users as creator', 'creator.id', '=', 'a.created_by')
            ->select('a.*', 'creator.name as creator_name')
            ->orderBy('a.activity_date');
        $announcements = DB::table('announcements')->orderByDesc('created_at');
        if ($user->role === 'student') {
            $activities->where('status', 'Published')->where('activity_date', '>=', now()->timezone('Asia/Manila')->toDateString());
            $this->forActivityAudience($activities, $profile);
            $this->forAudience($announcements->whereNull('archived_at'), $profile);
        }

        $data = [
            'section' => $section,
            'academicCatalog' => config('student_registration.catalog', []),
            'activityVenues' => config('cfo.venues', []),
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
            $data['registrations'] = DB::table('registrations as r')->join('users as u', 'u.id', '=', 'r.user_id')->join('activities as a', 'a.id', '=', 'r.activity_id')->leftJoin('profiles as p', 'p.user_id', '=', 'u.id')->select('r.*', 'u.name as student_name', 'u.university_id', 'a.title', 'a.type as activity_type', 'a.activity_date', DB::raw('COALESCE(p.course, p.department) as academic_course'), 'p.year', 'p.section')->orderByDesc('r.created_at')->get();
            $data['documents'] = DB::table('documents as d')->join('users as u', 'u.id', '=', 'd.user_id')->select('d.*', 'u.name as student_name')->orderByDesc('d.created_at')->get();
            $data['requirementSubmissions'] = DB::table('requirement_submissions as s')->join('activity_requirements as q', 'q.id', '=', 's.requirement_id')->join('users as u', 'u.id', '=', 's.user_id')->select('s.*', 'q.name as requirement_name', 'q.type as requirement_type', 'u.name as student_name')->orderByDesc('s.updated_at')->get();
            $data['staffAttendanceToday'] = DB::table('staff_attendance')->where('user_id', $user->id)->where('work_date', now()->timezone('Asia/Manila')->toDateString())->first();
            $data['staffAttendanceHistory'] = DB::table('staff_attendance')->where('user_id', $user->id)->orderByDesc('work_date')->limit(30)->get();
        }
        $data['activityFormFields'] = DB::table('activity_form_fields')->orderBy('sort_order')->get()->groupBy('activity_id');
        $data['activityRequirements'] = DB::table('activity_requirements')->orderBy('sort_order')->get()->groupBy('activity_id');
        $data['registrationAnswers'] = DB::table('registration_answers as a')->join('activity_form_fields as f', 'f.id', '=', 'a.field_id')->select('a.*', 'f.label', 'f.section_name')->orderBy('f.sort_order')->get()->groupBy('registration_id');
        $data['registrationCounts'] = DB::table('registrations')->where('status', '!=', 'Withdrawn')->select('activity_id', DB::raw('count(*) as total'))->groupBy('activity_id')->pluck('total', 'activity_id');
        if ($staff && $section === 'activities') {
            $activityFilter = in_array($request->query('activity_status'), ['drafts', 'pending', 'returned', 'published', 'closed', 'history', 'all'], true)
                ? $request->query('activity_status')
                : ($user->isSuperAdmin() ? 'pending' : 'all');
            $activityFilters = $request->validate([
                'activity_search' => ['nullable', 'string', 'max:150'],
                'activity_type' => ['nullable', 'in:Mass,Recollection,Retreat'],
                'activity_time' => ['nullable', 'in:all,today,upcoming,past'],
                'activity_from' => ['nullable', 'date'],
                'activity_to' => ['nullable', 'date', 'after_or_equal:activity_from'],
            ]);
            $today = now()->timezone('Asia/Manila')->toDateString();
            $data['activityFilter'] = $activityFilter;
            $data['activityFilters'] = $activityFilters + ['activity_time' => 'all'];
            $data['activityCounts'] = [
                'drafts' => $data['activities']->where('status', 'Draft')->count(),
                'pending' => $data['activities']->where('status', 'Pending approval')->count(),
                'returned' => $data['activities']->where('status', 'Changes requested')->count(),
                'published' => $data['activities']->where('status', 'Published')->count(),
                'closed' => $data['activities']->whereIn('status', ['Rejected', 'Cancelled', 'Archived'])->count(),
                'history' => $data['activities']->filter(fn ($activity) => $activity->activity_date < $today || in_array($activity->status, ['Rejected', 'Cancelled', 'Archived'], true))->count(),
                'all' => $data['activities']->count(),
            ];
            $visibleActivities = match ($activityFilter) {
                'drafts' => $data['activities']->where('status', 'Draft'),
                'pending' => $data['activities']->where('status', 'Pending approval'),
                'returned' => $data['activities']->where('status', 'Changes requested'),
                'published' => $data['activities']->where('status', 'Published'),
                'closed' => $data['activities']->whereIn('status', ['Rejected', 'Cancelled', 'Archived']),
                'history' => $data['activities']->filter(fn ($activity) => $activity->activity_date < $today || in_array($activity->status, ['Rejected', 'Cancelled', 'Archived'], true)),
                default => $data['activities'],
            };
            $search = Str::lower(trim((string) ($activityFilters['activity_search'] ?? '')));
            $visibleActivities = $visibleActivities
                ->when($search !== '', fn ($items) => $items->filter(fn ($activity) => Str::contains(Str::lower(implode(' ', [$activity->title, $activity->venue, $activity->department, $activity->creator_name])), $search)))
                ->when($activityFilters['activity_type'] ?? null, fn ($items, $type) => $items->where('type', $type))
                ->when(($activityFilters['activity_time'] ?? 'all') === 'today', fn ($items) => $items->where('activity_date', $today))
                ->when(($activityFilters['activity_time'] ?? 'all') === 'upcoming', fn ($items) => $items->filter(fn ($activity) => $activity->activity_date > $today))
                ->when(($activityFilters['activity_time'] ?? 'all') === 'past', fn ($items) => $items->filter(fn ($activity) => $activity->activity_date < $today))
                ->when($activityFilters['activity_from'] ?? null, fn ($items, $from) => $items->filter(fn ($activity) => $activity->activity_date >= $from))
                ->when($activityFilters['activity_to'] ?? null, fn ($items, $to) => $items->filter(fn ($activity) => $activity->activity_date <= $to))
                ->sortByDesc('activity_date')
                ->values();
            $data['visibleActivities'] = $this->paginateCollection($visibleActivities, 'activity_page');
        }
        if ($staff && $section === 'submissions') {
            $reviewFilter = in_array($request->query('review'), ['needs-review', 'awaiting-student', 'completed', 'all'], true)
                ? $request->query('review')
                : 'needs-review';
            $reviewArea = in_array($request->query('review_area'), ['activities', 'profiles'], true) ? $request->query('review_area') : 'activities';
            $reviewInput = $request->validate([
                'review_activity' => ['nullable', 'integer', 'exists:activities,id'],
                'review_search' => ['nullable', 'string', 'max:150'],
                'review_type' => ['nullable', 'in:Mass,Recollection,Retreat'],
                'review_item' => ['nullable', 'in:all,registration,documents,requirements'],
            ]);
            $pendingProfiles = $data['profiles']->where('status', 'Pending verification');
            $correctionProfiles = $data['profiles']->where('status', 'Requires correction');
            $pendingRegistration = fn (object $registration): bool => in_array($registration->status, ['Submitted', 'Under review'], true)
                || $data['documents']->where('registration_id', $registration->id)->where('status', 'Pending review')->isNotEmpty()
                || $data['requirementSubmissions']->where('registration_id', $registration->id)->where('status', 'Submitted')->isNotEmpty();
            $awaitingStudent = fn (object $registration): bool => $registration->status === 'Requires correction'
                || $data['documents']->where('registration_id', $registration->id)->where('status', 'Requires correction')->isNotEmpty()
                || $data['requirementSubmissions']->where('registration_id', $registration->id)->where('status', 'Requires correction')->isNotEmpty();
            $completedRegistration = fn (object $registration): bool => in_array($registration->status, ['Accepted', 'Withdrawn'], true)
                && ! $pendingRegistration($registration)
                && ! $awaitingStudent($registration);

            $data['reviewFilter'] = $reviewFilter;
            $data['reviewArea'] = $reviewArea;
            $data['reviewFilters'] = $reviewInput;
            $data['staffReviewCounts'] = [
                'needs-review' => $pendingProfiles->count()
                    + $data['registrations']->filter($pendingRegistration)->count(),
                'awaiting-student' => $correctionProfiles->count()
                    + $data['registrations']->filter($awaitingStudent)->count(),
                'completed' => $data['profiles']->where('status', 'Verified')->count()
                    + $data['registrations']->filter($completedRegistration)->count(),
                'all' => $data['profiles']->count() + $data['registrations']->count(),
            ];
            $reviewProfiles = match ($reviewFilter) {
                'needs-review' => $pendingProfiles,
                'awaiting-student' => $correctionProfiles,
                'completed' => $data['profiles']->where('status', 'Verified'),
                default => $data['profiles'],
            };
            $reviewRegistrations = match ($reviewFilter) {
                'needs-review' => $data['registrations']->filter($pendingRegistration),
                'awaiting-student' => $data['registrations']->filter($awaitingStudent),
                'completed' => $data['registrations']->filter($completedRegistration),
                default => $data['registrations'],
            };
            $reviewActivities = $data['activities']
                ->filter(fn ($activity) => $reviewRegistrations->contains('activity_id', $activity->id))
                ->sortBy(fn ($activity) => ($activity->activity_date >= now()->toDateString() ? '0' : '1').$activity->activity_date)
                ->values();
            $selectedActivity = $reviewInput['review_activity'] ?? $reviewRegistrations->first()?->activity_id ?? $reviewActivities->first()?->id;
            $search = Str::lower(trim((string) ($reviewInput['review_search'] ?? '')));
            $reviewProfiles = $reviewProfiles
                ->when($search !== '', fn ($items) => $items->filter(fn ($profile) => Str::contains(Str::lower($profile->student_name.' '.$profile->university_id), $search)))
                ->values();
            $reviewRegistrations = $reviewRegistrations
                ->when($selectedActivity, fn ($items, $id) => $items->where('activity_id', (int) $id))
                ->when($reviewInput['review_type'] ?? null, fn ($items, $type) => $items->where('activity_type', $type))
                ->when($search !== '', fn ($items) => $items->filter(fn ($registration) => Str::contains(Str::lower($registration->student_name.' '.$registration->university_id), $search)))
                ->when(($reviewInput['review_item'] ?? 'all') === 'registration', fn ($items) => $items->filter(fn ($registration) => in_array($registration->status, ['Submitted', 'Under review', 'Requires correction'], true)))
                ->when(($reviewInput['review_item'] ?? 'all') === 'documents', fn ($items) => $items->filter(fn ($registration) => $data['documents']->contains('registration_id', $registration->id)))
                ->when(($reviewInput['review_item'] ?? 'all') === 'requirements', fn ($items) => $items->filter(fn ($registration) => $data['requirementSubmissions']->contains('registration_id', $registration->id)))
                ->values();
            $data['reviewActivities'] = $reviewActivities;
            $data['selectedReviewActivity'] = $selectedActivity;
            $data['reviewProfiles'] = $this->paginateCollection($reviewProfiles, 'profile_page');
            $data['reviewRegistrations'] = $this->paginateCollection($reviewRegistrations, 'review_page');
        }

        if ($section === 'announcements') {
            if ($staff) {
                $announcementFilters = $request->validate([
                    'announcement_search' => ['nullable', 'string', 'max:150'],
                    'announcement_department' => ['nullable', 'string', 'max:150'],
                    'announcement_year' => ['nullable', 'string', 'max:20'],
                    'announcement_section' => ['nullable', 'string', 'max:50'],
                    'announcement_state' => ['nullable', 'in:active,archived,all'],
                    'announcement_from' => ['nullable', 'date'],
                    'announcement_to' => ['nullable', 'date', 'after_or_equal:announcement_from'],
                ]);
                $state = $announcementFilters['announcement_state'] ?? 'active';
                $search = Str::lower(trim((string) ($announcementFilters['announcement_search'] ?? '')));
                $visibleAnnouncements = $data['announcements']
                    ->when($state === 'active', fn ($items) => $items->whereNull('archived_at'))
                    ->when($state === 'archived', fn ($items) => $items->whereNotNull('archived_at'))
                    ->when($search !== '', fn ($items) => $items->filter(fn ($announcement) => Str::contains(Str::lower($announcement->title.' '.$announcement->body), $search)))
                    ->when($announcementFilters['announcement_department'] ?? null, fn ($items, $department) => $items->where('department', $department))
                    ->when($announcementFilters['announcement_year'] ?? null, fn ($items, $year) => $items->where('target_year', $year))
                    ->when($announcementFilters['announcement_section'] ?? null, fn ($items, $studentSection) => $items->where('target_section', $studentSection))
                    ->when($announcementFilters['announcement_from'] ?? null, fn ($items, $from) => $items->filter(fn ($announcement) => substr($announcement->created_at, 0, 10) >= $from))
                    ->when($announcementFilters['announcement_to'] ?? null, fn ($items, $to) => $items->filter(fn ($announcement) => substr($announcement->created_at, 0, 10) <= $to))
                    ->values();
                $data['announcementFilters'] = $announcementFilters + ['announcement_state' => 'active'];
                $data['announcementDepartments'] = $data['announcements']->pluck('department')->filter()->unique()->sort()->values();
                $data['visibleAnnouncements'] = $this->paginateCollection($visibleAnnouncements, 'announcement_page');
            } else {
                $data['visibleAnnouncements'] = $data['announcements'];
            }
        }

        if ($staff && $section === 'reports') {
            $reportFilters = RegistrationReport::filters($request);
            $reportQuery = RegistrationReport::query($reportFilters);
            $summaryRows = (clone $reportQuery)->get();
            $data['reportFilters'] = $reportFilters;
            $data['reportRows'] = $reportQuery->orderByDesc('a.activity_date')->paginate(25, ['*'], 'report_page')->withQueryString();
            $data['reportSummary'] = [
                'total' => $summaryRows->count(),
                'accepted' => $summaryRows->where('status', 'Accepted')->count(),
                'correction' => $summaryRows->where('status', 'Requires correction')->count(),
            ];
            $data['reportExportQuery'] = RegistrationReport::queryString($reportFilters);
        }

        if ($section === 'notifications') {
            $notificationFilters = $request->validate([
                'notification_state' => ['nullable', 'in:all,unread,read'],
                'notification_category' => ['nullable', 'string', 'max:80'],
                'notification_from' => ['nullable', 'date'],
                'notification_to' => ['nullable', 'date', 'after_or_equal:notification_from'],
                'notification_search' => ['nullable', 'string', 'max:150'],
            ]);
            $state = $notificationFilters['notification_state'] ?? 'unread';
            $search = Str::lower(trim((string) ($notificationFilters['notification_search'] ?? '')));
            $visibleNotifications = $data['notifications']
                ->when($state === 'unread', fn ($items) => $items->whereNull('read_at'))
                ->when($state === 'read', fn ($items) => $items->whereNotNull('read_at'))
                ->when($notificationFilters['notification_category'] ?? null, fn ($items, $category) => $items->where('category', $category))
                ->when($notificationFilters['notification_from'] ?? null, fn ($items, $from) => $items->filter(fn ($notification) => substr($notification->created_at, 0, 10) >= $from))
                ->when($notificationFilters['notification_to'] ?? null, fn ($items, $to) => $items->filter(fn ($notification) => substr($notification->created_at, 0, 10) <= $to))
                ->when($search !== '', fn ($items) => $items->filter(fn ($notification) => Str::contains(Str::lower($notification->message), $search)))
                ->values();
            $data['notificationFilters'] = $notificationFilters + ['notification_state' => 'unread'];
            $data['notificationCategories'] = $data['notifications']->pluck('category')->filter()->unique()->sort()->values();
            $data['visibleNotifications'] = $this->paginateCollection($visibleNotifications, 'notification_page');
        }
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

    private function paginateCollection(Collection $items, string $pageName, int $perPage = 20): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => $pageName]
        ))->withQueryString();
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
