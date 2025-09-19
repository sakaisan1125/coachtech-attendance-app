@extends('layouts.admin')

@section('css')
<link rel="stylesheet" href="{{ asset('css/staff.css') }}">
@endsection

@section('title', 'スタッフ一覧（管理者）')

@section('content')
    <h1 class="staff-list__title">スタッフ一覧</h1>

    <div class="staff-list__card">
        <table class="staff-list__table">
        <thead>
            <tr>
            <th class="staff-list__th" style="width:120px;">名前</th>
            <th class="staff-list__th" style="width:160px;">メールアドレス</th>
            <th class="staff-list__th" style="width:120px;">月次勤怠</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($staffs as $staff)
            <tr class="staff-list__tr">
                <td class="staff-list__td">{{ $staff->name }}</td>
                <td class="staff-list__td">{{ $staff->email }}</td>
                <td class="staff-list__td">
                <a class="staff-list__link" href="{{route('admin.staff.attendance.list', ['id' => $staff->id])}}">詳細</a>
                </td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>
@endsection