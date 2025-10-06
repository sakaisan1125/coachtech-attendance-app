@extends('layouts.admin')

@section('title', '修正申請承認画面（管理者）')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-detail.css') }}">
@endsection

@section('content')
@if (session('success') || !empty($success))
    <div class="alert alert-success">
        {{ session('success') ?? $success }}
    </div>
@endif

@php
    $d = $attendance->work_date instanceof \Carbon\Carbon
        ? $attendance->work_date
        : \Carbon\Carbon::parse($attendance->work_date);

    $breakRows = $attendance->breaks
        ->sortBy('break_start_at')
        ->values()
        ->map(function ($b) {
            return [
                'start' => $b->break_start_at?->format('H:i'),
                'end'   => $b->break_end_at?->format('H:i'),
            ];
        })
        ->toArray();

    // ここを変更: 最低2行になるまで空行追加（元は1行だけ）
    while (count($breakRows) < 2) {
        $breakRows[] = ['start' => null, 'end' => null];
    }

    $dash = '';
@endphp

<div class="attendance-detail">
    <h1 class="attendance-detail__title">勤怠詳細</h1>

    <form method="POST" action="{{ route('admin.approve', ['attendance_correct_request' => $correctionRequest->id]) }}">
        @csrf

        <div class="attendance-detail__card">
            <div class="attendance-detail__body">
                <table class="attendance-detail__table">
                    <tbody class="attendance-detail__table-body">
                        <tr class="attendance-detail__row attendance-detail__row--name">
                            <th class="attendance-detail__label">名前</th>
                            <td class="attendance-detail__value">{{ $user->name }}</td>
                        </tr>

                        <tr class="attendance-detail__row attendance-detail__row--date">
                            <th class="attendance-detail__label">日付</th>
                            <td class="attendance-detail__value attendance-detail__value--date">
                                <div class="attendance-detail__date-flex">
                                    <span class="attendance-detail__date-year">{{ $d->format('Y年') }}</span>
                                    <span class="attendance-detail__date-day">{{ $d->format('n月j日') }}</span>
                                </div>
                            </td>
                        </tr>

                        <tr class="attendance-detail__row attendance-detail__row--inout">
                            <th class="attendance-detail__label">出勤・退勤</th>
                            <td class="attendance-detail__value">
                                <div class="attendance-detail__time-range">
                                    <input
                                        type="text"
                                        name="clock_in_at"
                                        class="attendance-detail__input-time"
                                        value="{{ old('clock_in_at', $correctionRequest && $correctionRequest->requested_clock_in_at ? (method_exists($correctionRequest->requested_clock_in_at, 'format') ? $correctionRequest->requested_clock_in_at->format('H:i') : substr($correctionRequest->requested_clock_in_at, 0, 5)) : $dash) }}"
                                    >
                                    <span class="attendance-detail__separator">〜</span>
                                    <input
                                        type="text"
                                        name="clock_out_at"
                                        class="attendance-detail__input-time"
                                        value="{{ old('clock_out_at', $correctionRequest && $correctionRequest->requested_clock_out_at ? (method_exists($correctionRequest->requested_clock_out_at, 'format') ? $correctionRequest->requested_clock_out_at->format('H:i') : substr($correctionRequest->requested_clock_out_at, 0, 5)) : $dash) }}"
                                    >
                                </div>
                                @error('clock_in_at')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                                @error('clock_out_at')
                                    <div class="form-error">{{ $message }}</div>
                                @enderror
                            </td>
                        </tr>

                        @foreach ($breakRows as $i => $br)
                            <tr class="attendance-detail__row attendance-detail__row--break">
                                <th class="attendance-detail__label">{{ $i === 0 ? '休憩' : '休憩' . ($i + 1) }}</th>
                                <td class="attendance-detail__value">
                                    <div class="attendance-detail__time-range">
                                        <input
                                            type="text"
                                            name="breaks[{{ $i }}][start]"
                                            class="attendance-detail__input-time"
                                            value="{{ old("breaks.$i.start", isset($correctionRequest->breaks[$i]) && $correctionRequest->breaks[$i]?->requested_break_start_at ? $correctionRequest->breaks[$i]->requested_break_start_at->format('H:i') : $dash) }}"
                                        >
                                        <span class="attendance-detail__separator">〜</span>
                                        <input
                                            type="text"
                                            name="breaks[{{ $i }}][end]"
                                            class="attendance-detail__input-time"
                                            value="{{ old("breaks.$i.end", isset($correctionRequest->breaks[$i]) && $correctionRequest->breaks[$i]?->requested_break_end_at ? $correctionRequest->breaks[$i]->requested_break_end_at->format('H:i') : $dash) }}"
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

                        <tr class="attendance-detail__row attendance-detail__row--notes">
                            <th class="attendance-detail__label">備考</th>
                            <td class="attendance-detail__value">
                                <textarea
                                    class="attendance-detail__input-notes"
                                    name="notes"
                                    rows="3"
                                >{{ old('notes', $correctionRequest && $correctionRequest->requested_notes ? $correctionRequest->requested_notes : '') }}</textarea>
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
            @if ($isApproved)
                <div class="approved-label">承認済み</div>
            @else
                <button type="submit" class="attendance-detail__button">承認</button>
            @endif
        </div>
    </form>
</div>
@endsection