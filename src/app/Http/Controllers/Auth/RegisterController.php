<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // ログイン処理やリダイレクト
        auth()->login($user);

        return redirect()->route('attendance')->with('success', '登録が完了しました');
    }

    // public function login(LoginRequest $request)
    // {
    // if (!auth()->attempt($request->only('email', 'password'))) {
    //     return back()->withErrors(['auth' => 'ログイン情報が登録されていません'])->withInput();
    // }

    // // ログイン成功時の処理
    // return redirect()->route('attendance');
    // }
}