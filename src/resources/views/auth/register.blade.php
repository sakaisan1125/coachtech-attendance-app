@extends('layouts.auth')

@section('title', '会員登録')

@section('content')
<div class="register-wrap">
    <div class="register-title">会員登録</div>
    <form class="register-form" method="POST" action="{{ route('register') }}" novalidate>
        @csrf

        <div class="register-group">
            <label for="name">名前</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" autofocus>
            @error('name')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="register-group">
            <label for="email">メールアドレス</label>
            <input id="email" type="text" name="email" value="{{ old('email') }}">
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="register-group">
            <label for="password">パスワード</label>
            <input id="password" type="password" name="password" autocomplete="new-password">
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="register-group">
            <label for="password_confirmation">パスワード確認</label>
            <input id="password_confirmation" type="password" name="password_confirmation">
            @error('password_confirmation')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="register-btn">登録する</button>
    </form>
    <div class="register-link">
        <a href="{{ route('login') }}">ログインはこちら</a>
    </div>
</div>
@endsection