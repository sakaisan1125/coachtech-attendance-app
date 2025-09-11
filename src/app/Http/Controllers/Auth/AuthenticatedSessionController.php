<?php

namespace App\Http\Controllers\Auth;

use Laravel\Fortify\Http\Requests\LoginRequest;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifyAuthenticatedSessionController;

class AuthenticatedSessionController extends FortifyAuthenticatedSessionController
{
    public function store(LoginRequest $request)
    {
        $response = parent::store($request);

        // 認証後にroleでリダイレクト分岐
        if (auth()->check()) {
            if (auth()->user()->role === 'admin') {
                return redirect()->route('admin.attendance.list');
            }
            return redirect()->route('attendance');
        }

        return $response;
    }
}