@extends('layouts.app')

@section('title', '勤怠詳細')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/attendance_detail.css') }}">
@endsection

@section('content')
@php
    // 日付
    $d = $attendance->work_date instanceof \Carbon\Carbon
        ? $attendance->work_date
        : \Carbon\Carbon::parse($attendance->work_date);

    // 入力可否（申請中は編集不可）
    $inputDisabled = $hasPending ? 'disabled' : '';

    // 休憩行を作成：申請中は申請内容、そうでなければ実績
    if ($hasPending && isset($correctionRequest) && optional($correctionRequest->breaks)->count()) {
        $breakRows = $correctionRequest->breaks
            ->sortBy(fn($b) => $b->index ?? $b->requested_break_start_at)
            ->values()
            ->map(function($b){
                return [
                    'start' => $b->requested_break_start_at
                        ? \Carbon\Carbon::parse($b->requested_break_start_at)->format('H:i') : null,
                    'end'   => $b->requested_break_end_at
                        ? \Carbon\Carbon::parse($b->requested_break_end_at)->format('H:i') : null,
                ];
            })
            ->toArray();
    } else {
        // 実績の休憩
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
    }

    // 空行を1行追加
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

                        {{-- 出勤・退勤（申請中は入力不可） --}}
                        <tr class="attendance-detail__row attendance-detail__row--inout">
                            <th class="attendance-detail__label">出勤・退勤</th>
                            <td class="attendance-detail__value">
                                <div class="attendance-detail__time-range">
                                    <input type="text"
                                        name="clock_in_at"
                                        class="attendance-detail__input-time"
                                        value="{{ old('clock_in_at', $display['clock_in_at'] ? $display['clock_in_at']->format('H:i') : $dash) }}"
                                        {{ $inputDisabled }}>
                                    <span class="attendance-detail__separator">〜</span>
                                    <input type="text"
                                        name="clock_out_at"
                                        class="attendance-detail__input-time"
                                        value="{{ old('clock_out_at', $display['clock_out_at'] ? $display['clock_out_at']->format('H:i') : $dash) }}"
                                        {{ $inputDisabled }}>
                                    @error('clock_in_at')
                                        <div class="form-error">{{ $message }}</div>
                                    @enderror
                                    @error('clock_out_at')
                                        <div class="form-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </td>
                        </tr>

                        {{-- 休憩（申請中は申請内容を表示し入力不可） --}}
                        @foreach($breakRows as $i => $br)
                            <tr class="attendance-detail__row attendance-detail__row--break">
                                <th class="attendance-detail__label">{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</th>
                                <td class="attendance-detail__value">
                                    <div class="attendance-detail__time-range">
                                        <input type="text"
                                            name="breaks[{{ $i }}][start]"
                                            class="attendance-detail__input-time"
                                            value="{{ old("breaks.$i.start", $br['start'] ?? $dash) }}"
                                            {{ $inputDisabled }}>
                                        <span class="attendance-detail__separator">〜</span>
                                        <input type="text"
                                            name="breaks[{{ $i }}][end]"
                                            class="attendance-detail__input-time"
                                            value="{{ old("breaks.$i.end", $br['end'] ?? $dash) }}"
                                            {{ $inputDisabled }}>
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
                                  rows="3"
                                  {{ $inputDisabled }}>{{ old('notes', $display['notes'] ?? '') }}</textarea>
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