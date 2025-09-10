@extends('layouts.app')

@section('title', '勤怠一覧')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection
@section('content')
    <div class="container">
    <h1 class="page-title">勤怠一覧</h1>

    <div class="month-nav">
        <a class="btn" href="{{ route('attendance.list', ['month' => $prevMonth]) }}"><span class="arrow">←</span> 前月</a>
        <div class="month-label">{{ $month->format('Y/m')}}</div>
        <a class="btn" href="{{ route('attendance.list', ['month' => $nextMonth]) }}"> 翌月<span class="arrow">→</span></a>        
    </div>

    <div class="card">
        <table class="table">
            <thead>
        <tr>
          <th>日付</th>
          <th>出勤</th>
          <th>退勤</th>
          <th>休憩</th>
          <th>合計</th>
          <th>詳細</th>
        </tr>
      </thead>
        <tbody>
            @foreach ($days as $day)
                <tr>
                    <!-- <td>{{$day['date']->format('m/d(ddd)')}}</td> -->
                    <td>{{ $day['date']->isoFormat('MM/DD(ddd)') }}</td>
                    <td>{{ $day['clock_in'] ?: '-' }}</td>
                    <td>{{ $day['clock_out'] ?: '-' }}</td>
                    <td>{{ $day['break_hm'] ?: '-' }}</td>
                    <td>{{ $day['total_hm'] ?: '-' }}</td>
                    <td><a class="link" href="{{ $day['detail_url'] }}">詳細</a></td>
                </tr>
            @endforeach
        </tbody>
        </table>
    </div>
    </div>
@endsection