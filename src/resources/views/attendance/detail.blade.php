@extends('layouts.app')

@section('title', '勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance-detail.css') }}">
@endsection

@section('content')
@php
    $d = $attendance->work_date instanceof \Carbon\Carbon
        ? $attendance->work_date
        : \Carbon\Carbon::parse($attendance->work_date);
    $inputDisabled = $hasPending ? 'disabled' : '';
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
                        <tr>
                            <th class="attendance-detail__label">名前</th>
                            <td>{{ $user->name }}</td>
                        </tr>
                        <tr>
                            <th class="attendance-detail__label">日付</th>
                            <td class="attendance-detail__value--date">
                                <div class="attendance-detail__date-flex">
                                    <span class="attendance-detail__date-year">{{ $d->format('Y年') }}</span>
                                    <span class="attendance-detail__date-day">{{ $d->format('n月j日') }}</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th class="attendance-detail__label">出勤・退勤</th>
                            <td>
                                <div class="attendance-detail__time-range">
                                    <input type="text"
                                           name="clock_in_at"
                                           class="attendance-detail__input-time"
                                           value="{{ old('clock_in_at', $display['clock_in_at']?->format('H:i') ?? $dash) }}"
                                           {{ $inputDisabled }}>
                                    <span class="attendance-detail__separator">〜</span>
                                    <input type="text"
                                           name="clock_out_at"
                                           class="attendance-detail__input-time"
                                           value="{{ old('clock_out_at', $display['clock_out_at']?->format('H:i') ?? $dash) }}"
                                           {{ $inputDisabled }}>
                                </div>
                                @error('clock_in_at') <div class="form-error">{{ $message }}</div> @enderror
                                @error('clock_out_at') <div class="form-error">{{ $message }}</div> @enderror
                            </td>
                        </tr>
                        @foreach($displayBreaks as $i => $br)
                            <tr>
                                <th class="attendance-detail__label">{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</th>
                                <td>
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
                                    @error("breaks.$i.start") <div class="form-error">{{ $message }}</div> @enderror
                                    @error("breaks.$i.end")   <div class="form-error">{{ $message }}</div> @enderror
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <th class="attendance-detail__label">備考</th>
                            <td>
                                <textarea class="attendance-detail__input-notes"
                                          name="notes"
                                          rows="3"
                                          {{ $inputDisabled }}>{{ old('notes', $display['notes'] ?? '') }}</textarea>
                                @error('notes') <div class="form-error">{{ $message }}</div> @enderror
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="attendance-detail__actions">
            @if($hasPending)
                <div class="attendance-detail__pending-msg">*承認待ちのため修正はできません。</div>
            @else
                <button type="submit" class="attendance-detail__button">修正</button>
            @endif
        </div>
    </form>
</div>
@endsection