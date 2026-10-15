<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function download(Request $request, int $document)
    {
        $record = DB::table('documents')->find($document);
        abort_unless($record, 404);
        abort_unless($request->user()->isStaff() || $record->user_id === $request->user()->id, 403);
        abort_unless(Storage::disk('local')->exists($record->path), 404);

        return Storage::disk('local')->download($record->path, $record->original_name, ['Content-Type' => $record->mime]);
    }

    public function downloadRequirement(Request $request, int $submission)
    {
        $record = DB::table('requirement_submissions')->find($submission);
        abort_unless($record, 404);
        abort_unless($request->user()->isStaff() || $record->user_id === $request->user()->id, 403);
        abort_unless($record->path && Storage::disk('local')->exists($record->path), 404);

        return Storage::disk('local')->download($record->path, $record->original_name, ['Content-Type' => $record->mime]);
    }

    public function registrationForm(Request $request, int $registration)
    {
        $record = DB::table('registrations as r')
            ->join('activities as a', 'a.id', '=', 'r.activity_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('profiles as p', 'p.user_id', '=', 'u.id')
            ->where('r.id', $registration)
            ->select('r.*', 'a.title as activity_title', 'a.type as activity_type', 'a.activity_date', 'u.name as student_name', 'u.university_id', 'u.email', 'p.department', 'p.course', 'p.major', 'p.year', 'p.section', 'p.contact')
            ->first();
        abort_unless($record, 404);
        abort_unless($request->user()->isStaff() || $record->user_id === $request->user()->id, 403);
        abort_unless(in_array($record->activity_type, ['Recollection', 'Retreat'], true), 404);

        $answers = DB::table('registration_answers as a')
            ->join('activity_form_fields as f', 'f.id', '=', 'a.field_id')
            ->where('a.registration_id', $registration)
            ->select('a.answer', 'f.label', 'f.section_name', 'f.sort_order')
            ->orderBy('f.sort_order')
            ->get()
            ->groupBy('section_name');

        return view('registration-form', compact('record', 'answers'));
    }
}
