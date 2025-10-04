<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>@yield('title', 'COACHTECH 勤怠管理')</title>
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
  <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
  @yield('css')
</head>
<body>
  <header class="header">
    <div class="header__left">
      <img src="{{ asset('images/coachtech-logo.svg') }}" alt="COACHTECH ロゴ" class="header__logo">
    </div>
    <nav class="header__nav">
      <a href="/admin/attendance/list" class="header__link">勤怠一覧</a>
      <a href="/admin/staff/list" class="header__link">スタッフ一覧</a>
      <a href="{{ route('requests.pending') }}" class="header__link">申請一覧</a>
      <a href="/logout" class="header__link">ログアウト</a>
    </nav>
  </header>
  <main>
    @yield('content')
  </main>
</body>
</html>