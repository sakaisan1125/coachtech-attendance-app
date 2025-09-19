@extends('layouts.admin')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection

@section('title', 'スタッフ別勤怠一覧（管理者）')

@section('content')
    <div class="container">
        <h1 class="page-title">{{ $staff->name }}の勤怠一覧</h1>
        <div class="month-nav">
            <a class="btn" href="{{ route('admin.staff.attendance.list', ['id' => $staff->id, 'month' => $startDate->copy()->subMonth()->format('Y-m')]) }}"><span class="arrow">←</span> 前月</a>
            <div class="month-label">{{ $startDate->format('Y年n月') }}</div>
            <a class="btn" href="{{ route('admin.staff.attendance.list', ['id' => $staff->id, 'month' => $startDate->copy()->addMonth()->format('Y-m')]) }}"> 翌月<span class="arrow">→</span></a>
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
                @foreach ($dailyAttendanceList as $attendance)
                    <tr>
                        <td>{{ $attendance['date']->isoFormat('MM/DD(ddd)') }}</td>
                        <td>{{ $attendance['clock_in'] ?: '-' }}</td>
                        <td>{{ $attendance['clock_out'] ?: '-' }}</td>
                        <td>{{ $attendance['break_hm'] ?: '-' }}</td>
                        <td>{{ $attendance['total_hm'] ?: '-' }}</td>
                        <td><a class="link" href="{{ $attendance['detail_url'] }}">詳細</a></td>
                    </tr>
                @endforeach
            </tbody>
            </table>
        </div>
    </div>
@endsection