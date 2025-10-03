@extends('layouts.admin')

@section('title', '勤怠一覧（管理者）')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin_list.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="attendance-list">
  <h1 class="attendance-list__title">{{ $date->format('Y年n月j日') }}の勤怠</h1>

  <div class="attendance-list__date-nav">
    {{-- 前日 --}}
    <form method="GET" action="{{ route('admin.attendance.list') }}" class="attendance-list__nav-side">
      <input type="hidden" name="date" value="{{ $date->copy()->subDay()->toDateString() }}">
      <button type="submit" class="attendance-list__nav-btn">
        &larr; 前日
      </button>
    </form>

    {{-- 日付選択 --}}
    <form method="GET" action="{{ route('admin.attendance.list') }}" class="attendance-list__date-form">
      <label class="attendance-list__date-pill" style="cursor:pointer;">
        <i class="fa-regular fa-calendar-days"></i>
        <span class="attendance-list__date-text">{{ $date->format('Y/m/d') }}</span>
        <input
          type="date"
          name="date"
          value="{{ $date->toDateString() }}"
          class="attendance-list__date-input"
          aria-label="日付を選択"
          onchange="this.form.submit()"
        >
      </label>
    </form>

    {{-- 翌日 --}}
    <form method="GET" action="{{ route('admin.attendance.list') }}" class="attendance-list__nav-side">
      <input type="hidden" name="date" value="{{ $date->copy()->addDay()->toDateString() }}">
      <button type="submit" class="attendance-list__nav-btn">
        翌日 &rarr;
      </button>
    </form>
  </div>

  <div class="attendance-list__card">
    <table class="attendance-list__table">
      <thead>
        <tr>
          <th>名前</th>
          <th>出勤</th>
          <th>退勤</th>
          <th>休憩</th>
          <th>合計</th>
          <th>詳細</th>
        </tr>
      </thead>
      <tbody>
        @foreach($attendanceData as $row)
           @continue(($row->user->role ?? null) === 'admin') {{-- 念のため管理者は表示しない --}}
           <tr>
             <td>{{ $row->user->name }}</td>
             <td>{{ $row->clock_in_at ? \Carbon\Carbon::parse($row->clock_in_at)->format('H:i') : '-' }}</td>
          <!-- <tr>
            <td>{{ $row->user->name }}</td>
            <td>{{ $row->clock_in_at ? \Carbon\Carbon::parse($row->clock_in_at)->format('H:i') : '-' }}</td> -->
            <td>{{ $row->clock_out_at ? \Carbon\Carbon::parse($row->clock_out_at)->format('H:i') : '-' }}</td>
            <td>{{ $row->breaks_sum ?? '-' }}</td>
            <td>{{ $row->work_sum ?? '-' }}</td>
            <td>
              <a href="{{ route('admin.detail', ['id' => $row->id]) }}" class="attendance-list__detail-link">詳細</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection