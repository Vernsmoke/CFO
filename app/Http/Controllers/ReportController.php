<?php

namespace App\Http\Controllers;

use App\Support\RegistrationReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isStaff(), 403);
        $filters = RegistrationReport::filters($request);
        $rows = RegistrationReport::query($filters)->orderBy('a.activity_date')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['University ID', 'Student', 'Course', 'Year', 'Section', 'Activity', 'Type', 'Date', 'Registration Status', 'Group', 'Facilitator']);
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'spreadsheetSafe'], [
                    $row->university_id,
                    $row->student_name,
                    $row->academic_course,
                    $row->year,
                    $row->section,
                    $row->title,
                    $row->activity_type,
                    $row->activity_date,
                    $row->status,
                    $row->group_name,
                    $row->facilitator,
                ]));
            }
            fclose($out);
        }, 'cfo-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function spreadsheetSafe(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/u', ltrim($value)) ? "'".$value : $value;
    }
}
