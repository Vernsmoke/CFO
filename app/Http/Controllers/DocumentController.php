<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function download(Request $request, int $document)
    {
        $record = DB::table('documents')->find($document); abort_unless($record, 404);
        abort_unless($request->user()->isStaff() || $record->user_id === $request->user()->id, 403);
        abort_unless(Storage::disk('local')->exists($record->path), 404);
        return Storage::disk('local')->download($record->path, $record->original_name, ['Content-Type' => $record->mime]);
    }

    public function downloadRequirement(Request $request, int $submission)
    {
        $record = DB::table('requirement_submissions')->find($submission); abort_unless($record, 404);
        abort_unless($request->user()->isStaff() || $record->user_id === $request->user()->id, 403);
        abort_unless($record->path && Storage::disk('local')->exists($record->path), 404);
        return Storage::disk('local')->download($record->path, $record->original_name, ['Content-Type' => $record->mime]);
    }
}
