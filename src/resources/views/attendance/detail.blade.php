@extends('layouts.app')

@section('title', '勤怠詳細')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance-detail.css') }}">
@endsection

@section('content')
@php
    // 日付を一度だけ生成して使い回し
    $d = $attendance->work_date instanceof \Carbon\Carbon
        ? $attendance->work_date
        : \Carbon\Carbon::parse($attendance->work_date);

    // 休憩を開始時刻でソートし配列化
    $breakRows = $attendance->breaks
        ->sortBy('break_start_at')
        ->values()
        ->map(function($b){
            return [
                'start' => $b->break_start_at?->format('H:i'),
                'end'   => $b->break_end_at?->format('H:i'),
            ];
        })
        ->toArray();

    // 空行を1行追加（入力が無くてもUIを揃える）
    $breakRows[] = ['start' => null, 'end' => null];
    $dash = '';
@endphp

<div class="attendance-detail">
    <h1 class="attendance-detail__title">勤怠詳細</h1>

    <form method="POST" action="{{ route('attendance.request', ['id' => $attendance->id]) }}">
        @csrf
        <div class="attendance-detail__card">
            <div class="attendance-detail__body">
                <table class="attendance-detail__table">
                    <tbody class="attendance-detail__table-body">
                        {{-- 名前 --}}
                        <tr class="attendance-detail__row attendance-detail__row--name">
                            <th class="attendance-detail__label">名前</th>
                            <td class="attendance-detail__value">{{ $user->name }}</td>
                        </tr>

                        {{-- 日付 --}}
                        <tr class="attendance-detail__row attendance-detail__row--date">
                            <th class="attendance-detail__label">日付</th>
                            <td class="attendance-detail__value attendance-detail__value--date">
                                <div class="attendance-detail__date-flex">
                                    <span class="attendance-detail__date-year">{{ $d->format('Y年') }}</span>
                                    <span class="attendance-detail__date-day">{{ $d->format('n月j日') }}</span>
                                </div>
                            </td>
                        </tr>

                        {{-- 出勤・退勤 --}}
                        <tr class="attendance-detail__row attendance-detail__row--inout">
                            <th class="attendance-detail__label">出勤・退勤</th>
                            <td class="attendance-detail__value">
                                <div class="attendance-detail__time-range">
                                    <input type="text"
                                        name="clock_in_at"
                                        class="attendance-detail__input-time"
                                        value="{{ old(
                                            'clock_in_at',
                                            $correctionRequest && $correctionRequest->requested_clock_in_at
                                                ? (method_exists($correctionRequest->requested_clock_in_at, 'format')
                                                    ? $correctionRequest->requested_clock_in_at->format('H:i')
                                                    : substr($correctionRequest->requested_clock_in_at, 0, 5))
                                                : ($attendance->clock_in_at
                                                    ? (method_exists($attendance->clock_in_at, 'format')
                                                        ? $attendance->clock_in_at->format('H:i')
                                                        : substr($attendance->clock_in_at, 0, 5))
                                                    : $dash)
                                        ) }}"
                                    >
                                    <span class="attendance-detail__separator">〜</span>
                                    <input type="text"
                                        name="clock_out_at"
                                        class="attendance-detail__input-time"
                                        value="{{ old(
                                            'clock_out_at',
                                            $correctionRequest && $correctionRequest->requested_clock_out_at
                                                ? (method_exists($correctionRequest->requested_clock_out_at, 'format')
                                                    ? $correctionRequest->requested_clock_out_at->format('H:i')
                                                    : substr($correctionRequest->requested_clock_out_at, 0, 5))
                                                : ($attendance->clock_out_at
                                                    ? (method_exists($attendance->clock_out_at, 'format')
                                                        ? $attendance->clock_out_at->format('H:i')
                                                        : substr($attendance->clock_out_at, 0, 5))
                                                    : $dash)
                                        ) }}"
                                    >
                                    @error('clock_in_at')
                                        <div class="form-error">{{ $message }}</div>
                                    @enderror
                                    @error('clock_out_at')
                                        <div class="form-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </td>
                        </tr>

                        {{-- 休憩（1行目は「休憩」、以降は「休憩2,3…」） --}}
                        @foreach($breakRows as $i => $br)
                            <tr class="attendance-detail__row attendance-detail__row--break">
                                <th class="attendance-detail__label">{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</th>
                                <td class="attendance-detail__value">
                                    <div class="attendance-detail__time-range">
                                        <input type="text"
                                            name="breaks[{{ $i }}][start]"
                                            class="attendance-detail__input-time"
                                            value="{{ old(
                                                "breaks.$i.start",
                                                isset($correctionRequest->breaks[$i]) && $correctionRequest->breaks[$i]?->requested_break_start_at
                                                    ? $correctionRequest->breaks[$i]->requested_break_start_at->format('H:i')
                                                    : ($breakRows[$i]['start'] ?? $dash)
                                            ) }}"
                                        >
                                        <span class="attendance-detail__separator">〜</span>
                                        <input type="text"
                                            name="breaks[{{ $i }}][end]"
                                            class="attendance-detail__input-time"
                                            value="{{ old(
                                                "breaks.$i.end",
                                                isset($correctionRequest->breaks[$i]) && $correctionRequest->breaks[$i]?->requested_break_end_at
                                                    ? $correctionRequest->breaks[$i]->requested_break_end_at->format('H:i')
                                                    : ($breakRows[$i]['end'] ?? $dash)
                                            ) }}"
                                        >
                                    </div>
                                    @error("breaks.$i.start")
                                        <div class="form-error">{{ $message }}</div>
                                    @enderror
                                    @error("breaks.$i.end")
                                        <div class="form-error">{{ $message }}</div>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach

                        {{-- 備考 --}}
                        <tr class="attendance-detail__row attendance-detail__row--notes">
                            <th class="attendance-detail__label">備考</th>
                            <td class="attendance-detail__value">
                                <textarea
                                    class="attendance-detail__input-notes"
                                    name="notes"
                                    rows="3">{{ old('notes', $correctionRequest && $correctionRequest->requested_notes ? $correctionRequest->requested_notes : '') }}</textarea>
                                @error('notes')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="attendance-detail__actions">
            @if($hasPending)
                <div class="attendance-detail__pending-msg">
                    *承認待ちのため修正はできません。
                </div>
            @else
                <button type="submit" class="attendance-detail__button">修正</button>
            @endif
        </div>
    </form>
</div>
@endsection