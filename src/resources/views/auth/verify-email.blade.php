@extends('layouts.auth')

@section('content')
<div class="register-wrap">
    <div style="text-align: center; margin-top: 80px;">
        <div style="margin-bottom: 60px;">
            <p style="font-size: clamp(16px, 2.2vw, 24px); color: #000; font-weight: bold; white-space: nowrap;">
            登録していただいたメールアドレスに認証メールを送付しました。<br>メール認証を完了してください。
            </p>
            <!-- <p style="font-size: 24px; margin-bottom: 0; color: #000; font-weight: bold;">
                メール認証を完了してください。
            </p> -->
        </div>
        
        <!-- @if (session('message'))
            <div style="color: #28a745; font-weight: bold; margin-bottom: 30px; font-size: 16px;">
                {{ session('message') }}
            </div>
        @endif
        
        @if (session('success'))
            <div style="color: #28a745; font-weight: bold; margin-bottom: 30px; font-size: 16px;">
                {{ session('success') }}
            </div>
        @endif -->
        
        <div style="margin-bottom: 40px;">
            <a href="{{ route('email.verified') }}" style="
                display: inline-block;
                background-color: #D9D9D9;
                color: #000000;
                padding: 15px 30px;
                border-radius: 10px;
                border: 1px solid #000000;
                box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
                text-decoration: none;
                font-size: 24px;
                font-weight: bold;
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
                    color: #0073CC;
                    text-decoration: none;
                    cursor: pointer;
                    font-size: 20px;
                    padding: 0;
                ">
                    認証メールを再送する
                </button>
            </form>
        </div>
    </div>
</div>
@endsection