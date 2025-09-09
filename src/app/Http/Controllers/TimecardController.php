<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimecardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            ['status' => 'off_duty']
        );

        $breaks = $attendance->breaks()->orderBy('id')->get();

        return view('attendance.index', compact('attendance', 'breaks'));
    }

    public function clockIn(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $attendance = Attendance::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            ['status' => 'off_duty']
        );

        // if ($attendance->clock_in_at) {
        //     return back()->with('error', '本日はすでに出勤済みです。');
        // }
        // if ($attendance->status !== 'off_duty') {
        //     return back()->with('error', '現在の状態では出勤できません。');
        // }

        $attendance->update([
            'clock_in_at' => now(),
            'status'      => 'on_duty',
        ]);

        return back()->with('success', '出勤しました。');
    }

    public function breakStart(Request $request)
    {
        $attendance = $this->todayAttendance($request);

        // if ($attendance->status !== 'on_duty') {
        //     return back()->with('error', '出勤中のみ休憩に入れます。');
        // }

        // // 未終了の休憩があるなら弾く
        // $openBreak = $attendance->breaks()->whereNull('break_end_at')->first();
        // if ($openBreak) {
        //     return back()->with('error', 'すでに休憩中です。');
        // }

        BreakModel::create([
            'attendance_id'  => $attendance->id,
            'break_start_at' => now(),
        ]);

        $attendance->update(['status' => 'on_break']);

        return back()->with('success', '休憩に入りました。');
    }

    public function breakEnd(Request $request)
    {
        $attendance = $this->todayAttendance($request);

        // if ($attendance->status !== 'on_break') {
        //     return back()->with('error', '休憩中のみ休憩から戻れます。');
        // }

        $openBreak = $attendance->breaks()->whereNull('break_end_at')->latest('id')->first();
        // if (!$openBreak) {
        //     return back()->with('error', '未終了の休憩が見つかりません。');
        // }

        $openBreak->update(['break_end_at' => now()]);
        $attendance->update(['status' => 'on_duty']);

        return back()->with('success', '休憩から戻りました。');
    }

    public function clockOut(Request $request)
    {
        $attendance = $this->todayAttendance($request);

        // if (! $attendance->clock_in_at) {
        //     return back()->with('error', '出勤していません。');
        // }
        // if ($attendance->clock_out_at) {
        //     return back()->with('error', '本日はすでに退勤済みです。');
        // }
        // if ($attendance->status === 'on_break') {
        //     return back()->with('error', '休憩中は退勤できません。先に休憩から戻ってください。');
        // }

        DB::transaction(function () use ($attendance) {
            // 開きっぱなしの休憩があればクローズしておく（安全策）
            $attendance->breaks()->whereNull('break_end_at')->update(['break_end_at' => now()]);

            $attendance->update([
                'clock_out_at' => now(),
                'status'       => 'clocked_out',
            ]);
        });

        return back()->with('success', 'お疲れ様でした。');
    }

    private function todayAttendance(Request $request): Attendance
    {
        $user = $request->user();
        $today = now()->toDateString();

        return Attendance::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            // user_id と work_date が一致する勤怠データを検索
            ['status' => 'off_duty']
            // なければ status を 'off_duty' で新規作成
        );
    }
}
