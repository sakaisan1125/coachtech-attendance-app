@extends('layouts.admin')

@section('css')
<link rel="stylesheet" href="{{ asset('css/request.css') }}">
@endsection

@section('title', '申請一覧（管理者）')

@section('content')
  <h1 class="request-list__title">申請一覧</h1>

  {{-- タブ切替（同一パスだがコントローラ振り分けで管理者ビューに遷移） --}}
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
          <th class="request-list__th" style="width:110px;">状態</th>
          <th class="request-list__th" style="width:180px;">名前</th>
          <th class="request-list__th" style="width:160px;">対象日付</th>
          <th class="request-list__th">申請理由</th>
          <th class="request-list__th" style="width:160px;">申請日時</th>
          <th class="request-list__th" style="width:90px;">詳細</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rows as $r)
          @php
            $user   = $r->attendance?->user;
            $name   = $user?->name ?? '－';
            $work   = optional($r->attendance?->work_date)->format('Y/m/d');
            $applied= optional($r->created_at)->format('Y/m/d');
            $reason = $r->requested_notes ?: '（なし）';
            $state  = $r->status === 'approved' ? '承認済み' : '承認待ち';
          @endphp
          <tr class="request-list__tr">
            <td class="request-list__td">{{ $state }}</td>
            <td class="request-list__td">{{ $name }}</td>
            <td class="request-list__td">{{ $work }}</td>
            <td class="request-list__td">{{ $reason }}</td>
            <td class="request-list__td">{{ $applied }}</td>
            <td class="request-list__td">
              <a class="request-list__link" href="{{ route('admin.approve', ['attendance_correct_request' => $r->id]) }}">詳細</a>
            </td>
          </tr>
        @empty
          <tr>
            <td class="request-list__td" colspan="6">表示する申請はありません。</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- ページネーションを使う場合は $rows を paginate にしてこのブロックを生かす --}}
  @if(method_exists($rows, 'links'))
    <div style="max-width:1000px;margin:12px auto 0;">
      {{ $rows->links() }}
    </div>
  @endif
@endsection
