@extends('layouts.auth')

@section('content')
<div class="register-wrap">
    <div style="text-align: center; margin-top: 80px;">
        <div style="margin-bottom: 60px;">
            <p style="font-size: 18px; margin-bottom: 5px; color: #000; font-weight: 500;">
                登録していただいたメールアドレスに認証メールを送付しました。
            </p>
            <p style="font-size: 18px; margin-bottom: 0; color: #000; font-weight: 500;">
                メール認証を完了してください。
            </p>
        </div>
        
        @if (session('message'))
            <div style="color: #28a745; font-weight: bold; margin-bottom: 30px; font-size: 16px;">
                {{ session('message') }}
            </div>
        @endif
        
        @if (session('success'))
            <div style="color: #28a745; font-weight: bold; margin-bottom: 30px; font-size: 16px;">
                {{ session('success') }}
            </div>
        @endif
        
        <div style="margin-bottom: 40px;">
            <a href="{{ route('email.verified') }}" style="
                display: inline-block;
                background-color: #d6d6d6;
                color: #000;
                padding: 15px 30px;
                border-radius: 6px;
                text-decoration: none;
                font-size: 16px;
                font-weight: 500;
                border: none;
                cursor: pointer;
            ">
                認証はこちらから
            </a>
        </div>
        
        <div style="margin-bottom: 40px;">
            <form method="POST" action="{{ route('verification.send') }}" style="display: inline;">
                @csrf
                <button type="submit" style="
                    background: none;
                    border: none;
                    color: #35a5ff;
                    text-decoration: none;
                    cursor: pointer;
                    font-size: 16px;
                    padding: 0;
                ">
                    認証メールを再送する
                </button>
            </form>
        </div>
    </div>
</div>
@endsection