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
        $attendance = Attendance::with('breaks')->where('id', $id)->where('user_id', $user->id)->firstOrFail();

        $correctionRequest = CorrectionRequest::where('attendance_id', $attendance->id)
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->first();

        $hasPending = $correctionRequest && $correctionRequest->status === 'pending';

        return view('attendance.detail', [
            'attendance' => $attendance,
            'user'      => $user,
            'correctionRequest' => $correctionRequest,
            'hasPending' => $hasPending,
        ]);

    }

    public function requestCorrection(CorrectionRequestStoreRequest $request, int $id)
    {
        $user = $request->user();
        $attendance = Attendance::with('breaks')->where('id', $id)->where('user_id', $user->id)->firstOrFail();
        // firstOerFail()なので見つからないなら404

        $data = $request->validated();
        $ymd    = Carbon::parse($attendance->work_date)->toDateString();
        // toDatestring()はYYYY年MM月DD日を取り出すメソッド
        $toDateTime = function (?string $hm) use ($ymd) {
            return $hm ? Carbon::parse("$ymd $hm:00") : null;
        };
        //「'HH:MM' → 'YYYY-MM-DD HH:MM:00'」に変換する小さな関数（無名関数）。

        // 引数が空なら null を返す（未入力対応）。

        // use ($ymd) は、外側の $ymd をこの関数内でも使えるように“捕まえて”いる。

        DB::transaction(function () use ($data, $attendance, $user, $toDateTime) {
            // 勤怠修正申請の登録
            $correctionRequest = CorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'user_id'       => $user->id,
                'requested_by'  => $user->id,
                'requested_clock_in_at'  => $toDateTime($data['clock_in_at'] ?? null),
                'requested_clock_out_at' => $toDateTime($data['clock_out_at'] ?? null),
                'requested_notes'        => $data['notes'] ?? null, // ← 追加
                // 'notes'         => $data['notes'] ?? null,
                'status'        => 'pending',
            ]);
            // 中のDB処理が全部成功したらコミット、どれか失敗なら全部ロールバック。
            // use (...) は外側の変数をこの無名関数で使うためのもの。

            // 休憩修正申請の登録
            if (isset($data['breaks']) && is_array($data['breaks'])) {
                foreach ($data['breaks'] as $br) {
                    if (isset($br['start']) || isset($br['end'])) {

                         // ここで日付＋時刻に変換
                        $ymd = Carbon::parse($attendance->work_date)->toDateString();
                        $start = $br['start'] ? Carbon::parse("$ymd {$br['start']}:00") : null;
                        $end   = $br['end']   ? Carbon::parse("$ymd {$br['end']}:00")   : null;

                        CorrectionRequestBreak::create([
                            'correction_request_id' => $correctionRequest->id,
                            'requested_break_start_at' => $start,
                            'requested_break_end_at'   => $end,
                        ]);
                    }
                }
            }
        });
        // $br['start'] が「空じゃない」なら → Carbon::parse(...) の結果
        return redirect()->route('attendance.list')->with('success', '勤怠修正を申請しました。');
    }
}
