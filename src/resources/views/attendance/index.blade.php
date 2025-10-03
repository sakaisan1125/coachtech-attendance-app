@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/index.css') }}">
@endsection

@section('content') 
@php
  $statusLabels = [
    'off_duty' => '勤務外',
    'on_duty' => '出勤中',
    'on_break' => '休憩中',
    'clocked_out' => '退勤済',
  ];
@endphp
<div class="attendance-wrap">
  <header class="attendance-status">
    <span class="status-label">
      {{ $statusLabels[$attendance->status] }}
    </span>
  </header>
  <div class="attendance-date">
    {{ \Carbon\Carbon::parse($attendance->work_date)->isoFormat('YYYY年M月D日(ddd)')}}
  </div>
  <div class="attendance-time">
    {{ now()->format('H:i') }}
  </div>

  @if ($attendance->status === 'off_duty')
    <form method="POST" action="{{route('attendance.clock_in')}}">
      @csrf
      <button class="attendance-btn main-btn">出勤</button>
    </form>
  @elseif ($attendance->status === 'on_duty')
    <div class="attendance-btns">
      <form method="POST" action="{{route('attendance.clock_out')}}">
        @csrf
        <button class="attendance-btn main-btn">退勤</button>
      </form>
      <form method="POST" action="{{route('attendance.break_start')}}">
        @csrf
        <button class="attendance-btn sub-btn">休憩入</button>
      </form>
    </div>
  @elseif ($attendance->status === 'on_break')
    <form method="POST" action="{{route('attendance.break_end')}}">
      @csrf
      <button class="attendance-btn sub-btn">休憩戻</button>
    </form>
  @elseif ($attendance->status === 'clocked_out')
    <div class="attendance-message">お疲れ様でした。</div>
  @endif
</div>
@endsection