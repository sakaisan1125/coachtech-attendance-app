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
                                        value="{{ old('clock_in_at', $attendance->clock_in_at?->format('H:i') ?? $dash) }}">
                                    <span class="attendance-detail__separator">〜</span>
                                    <input type="text"
                                        name="clock_out_at"
                                        class="attendance-detail__input-time"
                                        value="{{ old('clock_out_at', $attendance->clock_out_at?->format('H:i') ?? $dash) }}">
                                </div>
                                @error('clock_in_at') <div class="form-error">{{ $message }}</div> @enderror
                                @error('clock_out_at') <div class="form-error">{{ $message }}</div> @enderror
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
                                            value="{{ old("breaks.$i.start", $br['start'] ?? $dash) }}">
                                        <span class="attendance-detail__separator">〜</span>
                                        <input type="text"
                                            name="breaks[{{ $i }}][end]"
                                            class="attendance-detail__input-time"
                                            value="{{ old("breaks.$i.end", $br['end'] ?? $dash) }}">
                                    </div>
                                    @error("breaks.$i.start") <div class="form-error">{{ $message }}</div> @enderror
                                    @error("breaks.$i.end") <div class="form-error">{{ $message }}</div> @enderror
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
                                    rows="3">{{ old('notes', $attendance->notes) }}</textarea>
                                @error('notes') <div class="form-error">{{ $message }}</div> @enderror
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="attendance-detail__actions">
            <button type="submit" class="attendance-detail__button">修正</button>
        </div>
    </form>
</div>

{{-- フラッシュメッセージ --}}
@if (session('success'))
    <div class="msg ok">{{ session('success') }}</div>
@endif

@if (!empty($hasPending) && $hasPending)
  <p style="text-align:right;color:#d32f2f;margin-top:8px;">
    ※承認待ちのため修正はできません。
  </p>
@endif

@endsection