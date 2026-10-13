<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request, string $section = 'overview')
    {
        $user = $request->user(); $staff = $user->isStaff();
        $allowed = $staff ? ['overview','activities','submissions','attendance','facilities','announcements','reports','notifications'] : ['overview','profile','activities','submissions','announcements','notifications'];
        if ($user->isSuperAdmin()) $allowed = array_merge($allowed, ['accounts','settings','audit']);
        abort_unless(in_array($section, $allowed, true), 404);
        $data = [
            'section' => $section,
            'activities' => DB::table('activities')->orderBy('activity_date')->get(),
            'announcements' => DB::table('announcements')->orderByDesc('created_at')->get(),
            'notifications' => DB::table('notifications')->where('user_id', $user->id)->orderByDesc('created_at')->get(),
        ];
        if ($user->role === 'student') {
            $data['profile'] = DB::table('profiles')->where('user_id', $user->id)->first();
            $data['registrations'] = DB::table('registrations as r')->join('activities as a', 'a.id', '=', 'r.activity_id')->where('r.user_id', $user->id)->select('r.*', 'a.title', 'a.activity_date')->orderByDesc('r.created_at')->get();
            $data['documents'] = DB::table('documents')->where('user_id', $user->id)->orderByDesc('created_at')->get();
        } else {
            $data['registrations'] = DB::table('registrations as r')->join('users as u', 'u.id', '=', 'r.user_id')->join('activities as a', 'a.id', '=', 'r.activity_id')->select('r.*', 'u.name as student_name', 'u.university_id', 'a.title', 'a.activity_date')->orderByDesc('r.created_at')->get();
            $data['facilities'] = DB::table('facility_requests as f')->join('users as u', 'u.id', '=', 'f.user_id')->select('f.*', 'u.name as requester')->orderByDesc('starts_at')->get();
            $data['documents'] = DB::table('documents as d')->join('users as u', 'u.id', '=', 'd.user_id')->select('d.*', 'u.name as student_name')->orderByDesc('d.created_at')->get();
            $data['attendance'] = DB::table('attendance')->get()->keyBy('registration_id');
        }
        if ($user->isSuperAdmin()) {
            $data['users'] = DB::table('users')->orderBy('name')->get();
            $data['audits'] = DB::table('audit_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.actor_id')->select('l.*', 'u.name as actor')->orderByDesc('l.created_at')->limit(200)->get();
        }
        return view('dashboard', $data);
    }
}
