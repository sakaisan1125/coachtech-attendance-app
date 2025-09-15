<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\LoginRequest;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifyAuthenticatedSessionController;

class AuthenticatedSessionController extends FortifyAuthenticatedSessionController
{
    public function store(\Laravel\Fortify\Http\Requests\LoginRequest $request)
    {
        // 一般ユーザー認証
        if (!auth()->attempt($request->only('email', 'password'))) {
            return back()->withErrors(['auth' => 'ログイン情報が登録されていません'])->withInput();
        }
        return redirect()->route('attendance');
    }

    public function adminLogin(AdminLoginRequest $request)
    {
        // 管理者認証
        if (!auth()->attempt([
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'admin'
        ])) {
            return back()->withErrors(['auth' => 'ログイン情報が登録されていません'])->withInput();
        }
        return redirect()->route('admin.attendance.list');
    }
}