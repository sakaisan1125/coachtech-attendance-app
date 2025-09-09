<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>@yield('title', 'COACHTECH 勤怠管理')</title>
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
  @yield('css')
</head>
<body>
  <header class="header">
    <div class="header__left">
      <img src="{{ asset('images/coachtech-logo.svg') }}" alt="COACHTECH ロゴ" class="header__logo">
    </div>
  </header>
  <main>
    @yield('content')
  </main>
</body>
</html>