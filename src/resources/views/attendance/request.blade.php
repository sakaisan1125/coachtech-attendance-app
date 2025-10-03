@extends('layouts.app')

@section('css')
  <link rel="stylesheet" href="{{ asset('css/request.css') }}">
@endsection

@section('title', '申請一覧')

@section('content')
  <h1 class="request-list__title">申請一覧</h1>

  {{-- タブ --}}
  <div class="request-list__tabs">
    <a class="request-list__tab {{ $activeTab==='pending' ? 'is-active' : '' }}"
       href="{{ route('requests.pending') }}">承認待ち</a>
    <a class="request-list__tab {{ $activeTab==='approved' ? 'is-active' : '' }}"
       href="{{ route('requests.approved') }}">承認済み</a>
  </div>

  <div class="request-list__card">
    <table class="request-list__table">
      <thead>
        <tr>
          <th class="request-list__th u-center" style="width:110px;">状態</th>
          <th class="request-list__th" style="width:140px;">名前</th>
          <th class="request-list__th u-center" style="width:160px;">対象日付</th>
          <th class="request-list__th">申請理由</th>
          <th class="request-list__th u-center" style="width:160px;">申請日時</th>
          <th class="request-list__th u-center" style="width:90px;">詳細</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rows as $r)
          @php
            $att       = $r->attendance;
            $dateYmd   = \Carbon\Carbon::parse($att->work_date)->format('Y/m/d');
            $appliedAt = $r->created_at?->format('Y/m/d');
            $isPending = ($activeTab==='pending');
          @endphp
          <tr class="request-list__tr">
            <td class="request-list__td u-center">
              <span class="status-badge {{ $isPending ? 'is-pending' : 'is-approved' }}">
                {{ $isPending ? '承認待ち' : '承認済み' }}
              </span>
            </td>
            <td class="request-list__td">{{ auth()->user()->name }}</td>
            <td class="request-list__td u-center">{{ $dateYmd }}</td>
            <td class="request-list__td u-ellipsis" title="{{ $r->requested_notes }}">
              {{ $r->requested_notes ?: '（なし）' }}
            </td>
            <td class="request-list__td u-center">{{ $appliedAt }}</td>
            <td class="request-list__td u-center">
              <a class="request-list__link" href="{{ route('attendance.detail', ['id' => $att->id]) }}">詳細</a>
            </td>
          </tr>
        @empty
          <tr>
            <td class="request-list__td u-center" colspan="6">表示する申請はありません。</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
