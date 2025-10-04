@extends('layouts.admin')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}">
@endsection

@section('title', 'スタッフ別勤怠一覧（管理者）')

@section('content')
<div class="container">
    <h1 class="page-title">{{ $staff->name }}さんの勤怠</h1>

    <div class="month-nav">
        <a class="btn" href="{{ route('admin.staff.attendance.list', ['id' => $staff->id, 'month' => $startDate->copy()->subMonth()->format('Y-m')]) }}">
            <span class="icon-arrow icon-arrow--left" aria-hidden="true"></span> 前月
        </a>
        <div class="month-label">
            <span class="icon-calendar" aria-hidden="true"></span>
            {{ $startDate->format('Y/m') }}
        </div>
        <a class="btn" href="{{ route('admin.staff.attendance.list', ['id' => $staff->id, 'month' => $startDate->copy()->addMonth()->format('Y-m')]) }}">
            翌月 <span class="icon-arrow icon-arrow--right" aria-hidden="true"></span>
        </a>
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
                        <td>{{ $attendance['clock_in'] ?: '' }}</td>
                        <td>{{ $attendance['clock_out'] ?: '' }}</td>
                        <td>{{ $attendance['break_hm'] ?: '' }}</td>
                        <td>{{ $attendance['total_hm'] ?: '' }}</td>
                        <td><a class="link" href="{{ $attendance['detail_url'] }}">詳細</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="export-btn-wrapper">
        <a class="btn btn--primary" href="{{ route('admin.staff.attendance.csv', ['id' => $staff->id, 'month' => $startDate->format('Y-m')]) }}">CSV出力</a>
    </div>
</div>
@endsection