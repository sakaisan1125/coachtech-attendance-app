@extends('layouts.auth')

@section('title', 'ログイン')

@section('content')
<div class="login-wrap">
    <div class="login-title">ログイン</div>
    <form class="login-form" method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div class="login-group">
            <label for="email">メールアドレス</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}">
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>
        <div class="login-group">
            <label for="password">パスワード</label>
            <input id="password" type="password" name="password">
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>
        @if ($errors->has('auth'))
            <div class="form-error">{{ $errors->first('auth') }}</div>
        @endif
        <button type="submit" class="login-btn">ログインする</button>
    </form>
    <div class="login-link">
        <a href="{{ route('register') }}">会員登録はこちら</a>
    </div>
</div>
@endsection