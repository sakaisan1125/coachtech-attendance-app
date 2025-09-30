<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class TimecardController extends Controller
{
    public function index(Request $request)
    {
        $attendance = $this->todayAttendance($request);
        $breaks = $attendance->breaks()->orderBy('id')->get();

        return view('attendance.index', compact('attendance', 'breaks'));
    }

    public function clockIn(Request $request)
    {
        $attendance = $this->todayAttendance($request);

        if ($attendance->clock_in_at) {
            return redirect()->route('attendance')->with('info', 'すでに出勤済みです。');
        }

        $attendance->update([
            'clock_in_at' => now(),
            'status'      => 'on_duty',
        ]);

        return redirect()->route('attendance')->with('success', '出勤しました。');
    }

    public function breakStart(Request $request)
    {
        $attendance = $this->todayAttendance($request);

        $attendance->breaks()->create(['break_start_at' => now()]);
        $attendance->update(['status' => 'on_break']);

        return redirect()->route('attendance')->with('success', '休憩に入りました。');
    }

    public function breakEnd(Request $request)
    {
        $attendance = $this->todayAttendance($request);

        $openBreak = $attendance->breaks()->whereNull('break_end_at')->latest('id')->first();
        if (!$openBreak) {
            return redirect()->route('attendance')->with('error', '未終了の休憩が見つかりません。');
        }

        $openBreak->update(['break_end_at' => now()]);
        $attendance->update(['status' => 'on_duty']);

        return redirect()->route('attendance')->with('success', '休憩から戻りました。');
    }

    public function clockOut(Request $request)
    {
        $attendance = $this->todayAttendance($request);

        DB::transaction(function () use ($attendance) {
            $attendance->breaks()->whereNull('break_end_at')->update(['break_end_at' => now()]);

            $attendance->update([
                'clock_out_at' => now(),
                'status'       => 'clocked_out',
            ]);
        });

        return redirect()->route('attendance')->with('success', 'お疲れ様でした。');
    }

    /**
     * 今日の勤怠を取得 or 作成
     */
    private function todayAttendance(Request $request): Attendance
    {
        if (!$request->user()) {
            abort(401);
        }

        $attendance = Attendance::where('user_id', $request->user()->id)
            ->whereDate('work_date', now())
            ->first();

        if ($attendance) {
            return $attendance;
        }

        try {
            return Attendance::create([
                'user_id'   => $request->user()->id,
                'work_date' => now(),       // モデルで Y-m-d に正規化される
                'status'    => 'off_duty',
            ]);
        } catch (QueryException $e) {
            return Attendance::where('user_id', $request->user()->id)
                ->whereDate('work_date', now())
                ->firstOrFail();
        }
    }
}