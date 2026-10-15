<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isStaff(), 403);
        $rows = DB::table('registrations as r')->join('users as u', 'u.id', '=', 'r.user_id')->join('activities as a', 'a.id', '=', 'r.activity_id')->select('u.university_id','u.name','a.title','a.activity_date','r.status','r.group_name','r.facilitator')->orderBy('a.activity_date')->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['University ID','Student','Activity','Date','Status','Group','Facilitator']);
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'spreadsheetSafe'], (array) $row));
            }
            fclose($out);
        }, 'cfo-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function spreadsheetSafe(mixed $value): mixed
    {
        if (!is_string($value)) return $value;

        return preg_match('/^[=+\-@\t\r]/u', ltrim($value)) ? "'".$value : $value;
    }
}
