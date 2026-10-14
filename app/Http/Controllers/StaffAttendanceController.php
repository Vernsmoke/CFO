<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StaffAttendanceController extends Controller
{
    public function record(Request $request)
    {
        abort_unless($request->user()->isStaff(), 403);
        $data = $request->validate(['checkpoint' => ['required', Rule::in(['in', 'out'])], 'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120']]);
        $date = now()->timezone('Asia/Manila')->toDateString();
        $checkpoint = $data['checkpoint'];
        $result = DB::transaction(function () use ($request, $date, $checkpoint, $data) {
            $entry = DB::table('staff_attendance')->where('user_id', $request->user()->id)->where('work_date', $date)->lockForUpdate()->first();
            if ($checkpoint === 'out' && !$entry?->time_in) return ['error' => 'Record your time in before recording time out.'];
            if ($entry?->{"time_{$checkpoint}"}) return ['error' => 'Your time '.($checkpoint === 'in' ? 'in' : 'out').' is already recorded for today.'];

            $path = $data['photo']->store('staff-attendance/'.$date, 'local');
            $values = [
                "time_{$checkpoint}" => now(),
                "time_{$checkpoint}_photo_path" => $path,
                "time_{$checkpoint}_ip" => $request->ip(),
                "time_{$checkpoint}_user_agent" => mb_substr((string) $request->userAgent(), 0, 1000),
                'updated_at' => now(),
            ];
            if ($entry) DB::table('staff_attendance')->where('id', $entry->id)->update($values);
            else DB::table('staff_attendance')->insert(['user_id' => $request->user()->id, 'work_date' => $date, 'status' => 'Present', ...$values, 'created_at' => now()]);
            return ['saved' => true];
        });
        if (isset($result['error'])) return back()->withErrors(['staff_attendance' => $result['error']]);
        $this->audit($request, 'Recorded staff attendance', $request->user()->university_id, $checkpoint);
        return back()->with('success', 'Time '.($checkpoint === 'in' ? 'in' : 'out').' recorded with your attendance photo.');
    }

    public function photo(Request $request, int $attendance, string $checkpoint)
    {
        abort_unless(in_array($checkpoint, ['in', 'out'], true), 404);
        $entry = DB::table('staff_attendance')->find($attendance); abort_unless($entry, 404);
        abort_unless($request->user()->isStaff() && ($request->user()->isSuperAdmin() || $entry->user_id === $request->user()->id), 403);
        $path = $entry->{"time_{$checkpoint}_photo_path"};
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path);
    }

    public function update(Request $request, int $attendance)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $entry = DB::table('staff_attendance')->find($attendance); abort_unless($entry, 404);
        $data = $request->validate([
            'time_in' => ['nullable', 'date'],
            'time_out' => ['nullable', 'date', 'after_or_equal:time_in'],
            'status' => ['required', Rule::in(['Present', 'Incomplete', 'Excused'])],
            'admin_note' => ['required', 'string', 'max:2000'],
        ]);
        DB::table('staff_attendance')->where('id', $attendance)->update([...$data, 'corrected_by' => $request->user()->id, 'updated_at' => now()]);
        $detail = json_encode(['before' => ['time_in' => $entry->time_in, 'time_out' => $entry->time_out, 'status' => $entry->status], 'after' => $data], JSON_UNESCAPED_SLASHES);
        $this->audit($request, 'Corrected staff attendance', (string) $attendance, $detail);
        return back()->with('success', 'Staff attendance correction saved.');
    }

    private function audit(Request $request, string $action, string $target, ?string $detail = null): void
    {
        DB::table('audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'target' => $target, 'detail' => $detail, 'created_at' => now(), 'updated_at' => now()]);
    }
}
