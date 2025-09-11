@extends('layouts.auth')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-login.css') }}">
@endsection

@section('title', '管理者ログイン')

@section('content')
<div class="admin-login">
    <h1 class="admin-login__title">管理者ログイン</h1>

    <form method="POST" action="{{ route('admin.login') }}" class="admin-login__form">
        @csrf

        <div class="admin-login__field">
            <label for="email">メールアドレス</label>
            <input type="email" name="email" id="email" required autofocus>
        </div>

        <div class="admin-login__field">
            <label for="password">パスワード</label>
            <input type="password" name="password" id="password" required>
        </div>

        <button type="submit" class="admin-login__btn">管理者ログインする</button>
    </form>
</div>
@endsection
