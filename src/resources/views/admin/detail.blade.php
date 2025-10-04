@extends('layouts.admin')

@section('title', '勤怠詳細（管理者）')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance_detail.css') }}">
@endsection

@section('content')
@php
    $d = $attendance->work_date instanceof \Carbon\Carbon
        ? $attendance->work_date
        : \Carbon\Carbon::parse($attendance->work_date);

    $breakRows = $attendance->breaks
        ->sortBy('break_start_at')
        ->values()
        ->map(fn($b) => [
            'start' => $b->break_start_at?->format('H:i'),
            'end'   => $b->break_end_at?->format('H:i'),
        ])
        ->toArray();

    while (count($breakRows) < 2) {
        $breakRows[] = ['start' => null, 'end' => null];
    }
@endphp

<div class="attendance-detail">
    <h1 class="attendance-detail__title">勤怠詳細</h1>

    <form method="POST" action="{{ route('admin.update', ['id' => $attendance->id]) }}">
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
                                        name="clock_in"
                                        class="attendance-detail__input-time"
                                        value="{{ old('clock_in', $attendance->clock_in_at?->format('H:i')) }}"
                                        placeholder="09:00"
                                    >
                                    <span class="attendance-detail__separator">〜</span>
                                    <input
                                        type="text"
                                        name="clock_out"
                                        class="attendance-detail__input-time"
                                        value="{{ old('clock_out', $attendance->clock_out_at?->format('H:i')) }}"
                                        placeholder="18:00"
                                    >
                                </div>
                                @error('clock_out')
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
                                            value="{{ old(
                                                "breaks.$i.start",
                                                isset($correctionRequest->breaks[$i]) && $correctionRequest->breaks[$i]?->requested_break_start_at
                                                    ? $correctionRequest->breaks[$i]->requested_break_start_at->format('H:i')
                                                    : ($breakRows[$i]['start'] ?? '')
                                            ) }}"
                                        >
                                        <span class="attendance-detail__separator">〜</span>
                                        <input
                                            type="text"
                                            name="breaks[{{ $i }}][end]"
                                            class="attendance-detail__input-time"
                                            value="{{ old(
                                                "breaks.$i.end",
                                                isset($correctionRequest->breaks[$i]) && $correctionRequest->breaks[$i]?->requested_break_end_at
                                                    ? $correctionRequest->breaks[$i]->requested_break_end_at->format('H:i')
                                                    : ($breakRows[$i]['end'] ?? '')
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

                        <tr class="attendance-detail__row attendance-detail__row--notes">
                            <th class="attendance-detail__label">備考</th>
                            <td class="attendance-detail__value">
                                <textarea
                                    name="notes"
                                    class="attendance-detail__input-notes"
                                    rows="3"
                                >{{ old('notes', $attendance->notes) }}</textarea>
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
            @if ($hasPending)
                <div class="attendance-detail__pending-msg">*承認待ちのため修正はできません。</div>
            @else
                <button type="submit" class="attendance-detail__button">修正</button>
            @endif
        </div>
    </form>
</div>

@if (session('success'))
    <div class="msg ok">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="msg ng">{{ session('error') }}</div>
@endif
@endsection