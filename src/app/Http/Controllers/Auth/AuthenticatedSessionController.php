<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\AdminLoginRequest;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifyAuthenticatedSessionController;

class AuthenticatedSessionController extends FortifyAuthenticatedSessionController
{
    public function store(\Laravel\Fortify\Http\Requests\LoginRequest $request)
    {
        if (!auth()->attempt($request->only('email', 'password'))) {
            return back()->withErrors(['auth' => 'ログイン情報が登録されていません'])->withInput();
        }
        return redirect()->route('attendance');
    }

    public function adminLogin(AdminLoginRequest $request)
    {
        if (!auth()->attempt([
            'email'    => $request->email,
            'password' => $request->password,
            'role'     => 'admin'
        ])) {
            return back()->withErrors(['auth' => 'ログイン情報が登録されていません'])->withInput();
        }

        if (auth()->user()->email_verified_at === null) {
            auth()->user()->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect()->route('admin.attendance.list');
    }
}