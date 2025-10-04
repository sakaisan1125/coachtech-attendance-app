<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Http\Requests\CorrectionRequestStoreRequest;
use Carbon\Carbon;
use App\Models\CorrectionRequest;
use App\Models\CorrectionRequestBreak;
use Illuminate\Support\Facades\DB;

class AttendanceDetailController extends Controller
{
    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $attendance = Attendance::with('breaks')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $correctionRequest = CorrectionRequest::with('breaks')
            ->where('attendance_id', $attendance->id)
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->latest()
            ->first();

        $hasPending = $correctionRequest && $correctionRequest->status === 'pending';

        if ($hasPending) {
            $display = [
                'clock_in_at' => $correctionRequest->requested_clock_in_at,
                'clock_out_at' => $correctionRequest->requested_clock_out_at,
                'breaks' => $correctionRequest->breaks,
                'notes' => $correctionRequest->requested_notes,
            ];
        } else {
            $display = [
                'clock_in_at' => $attendance->clock_in_at,
                'clock_out_at' => $attendance->clock_out_at,
                'breaks' => $attendance->breaks,
                'notes' => $attendance->notes,
            ];
        }

        return view('attendance.detail', [
            'attendance' => $attendance,
            'user' => $user,
            'correctionRequest' => $correctionRequest,
            'hasPending' => $hasPending,
            'display' => $display,
        ]);
    }

    public function requestCorrection(CorrectionRequestStoreRequest $request, int $id)
    {
        $user = $request->user();
        $attendance = Attendance::with('breaks')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data = $request->validated();
        $ymd = Carbon::parse($attendance->work_date)->toDateString();
        $toDateTime = function (?string $hm) use ($ymd) {
            return $hm ? Carbon::parse("$ymd $hm:00") : null;
        };

        DB::transaction(function () use ($data, $attendance, $user, $toDateTime) {
            $correctionRequest = CorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'requested_by' => $user->id,
                'requested_clock_in_at' => $toDateTime($data['clock_in_at'] ?? null),
                'requested_clock_out_at' => $toDateTime($data['clock_out_at'] ?? null),
                'requested_notes' => $data['notes'] ?? null,
                'status' => 'pending',
            ]);

            if (isset($data['breaks']) && is_array($data['breaks'])) {
                foreach ($data['breaks'] as $br) {
                    if (isset($br['start']) || isset($br['end'])) {
                        $ymd = Carbon::parse($attendance->work_date)->toDateString();
                        $start = $br['start'] ? Carbon::parse("$ymd {$br['start']}:00") : null;
                        $end = $br['end'] ? Carbon::parse("$ymd {$br['end']}:00") : null;

                        CorrectionRequestBreak::create([
                            'correction_request_id' => $correctionRequest->id,
                            'requested_break_start_at' => $start,
                            'requested_break_end_at' => $end,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('attendance.list')->with('success', '勤怠修正を申請しました。');
    }

    public function showByDate(Request $request, string $date)
    {
        try {
            $d = Carbon::createFromFormat('Y-m-d', $date)->toDateString();
        } catch (\Throwable $e) {
            abort(404);
        }

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $request->user()->id, 'work_date' => $d],
            ['clock_in_at' => null, 'clock_out_at' => null, 'notes' => null]
        );

        return $this->show($request, $attendance->id);
    }
}
