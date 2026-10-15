<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RegistrationReport
{
    public static function filters(Request $request): array
    {
        return $request->validate([
            'report_search' => ['nullable', 'string', 'max:150'],
            'report_activity' => ['nullable', 'integer', 'exists:activities,id'],
            'report_type' => ['nullable', Rule::in(['Mass', 'Recollection', 'Retreat'])],
            'report_from' => ['nullable', 'date'],
            'report_to' => ['nullable', 'date', 'after_or_equal:report_from'],
            'report_course' => ['nullable', 'string', 'max:150'],
            'report_year' => ['nullable', 'string', 'max:20'],
            'report_section' => ['nullable', 'string', 'max:50'],
            'report_status' => ['nullable', Rule::in(['Submitted', 'Under review', 'Requires correction', 'Accepted', 'Withdrawn'])],
        ]);
    }

    public static function query(array $filters): Builder
    {
        $query = DB::table('registrations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->join('activities as a', 'a.id', '=', 'r.activity_id')
            ->leftJoin('profiles as p', 'p.user_id', '=', 'u.id')
            ->select(
                'r.*',
                'u.name as student_name',
                'u.university_id',
                'a.title',
                'a.type as activity_type',
                'a.activity_date',
                'a.activity_time',
                'a.venue',
                DB::raw('COALESCE(p.course, p.department) as academic_course'),
                'p.year',
                'p.section',
            );

        $search = trim((string) ($filters['report_search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->where('u.name', 'like', "%{$search}%")
                    ->orWhere('u.university_id', 'like', "%{$search}%")
                    ->orWhere('a.title', 'like', "%{$search}%");
            });
        }

        return $query
            ->when($filters['report_activity'] ?? null, fn (Builder $query, $activity) => $query->where('a.id', $activity))
            ->when($filters['report_type'] ?? null, fn (Builder $query, $type) => $query->where('a.type', $type))
            ->when($filters['report_from'] ?? null, fn (Builder $query, $date) => $query->whereDate('a.activity_date', '>=', $date))
            ->when($filters['report_to'] ?? null, fn (Builder $query, $date) => $query->whereDate('a.activity_date', '<=', $date))
            ->when($filters['report_course'] ?? null, fn (Builder $query, $course) => $query->whereRaw('COALESCE(p.course, p.department) = ?', [$course]))
            ->when($filters['report_year'] ?? null, fn (Builder $query, $year) => $query->where('p.year', $year))
            ->when($filters['report_section'] ?? null, fn (Builder $query, $section) => $query->where('p.section', $section))
            ->when($filters['report_status'] ?? null, fn (Builder $query, $status) => $query->where('r.status', $status));
    }

    public static function queryString(array $filters): array
    {
        return array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }
}
