<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name'                  => ['required', 'string', 'max:20'],
            'email'                 => ['required', 'string', 'email', 'max:255'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
        ];
    }

    public function messages()
    {
        return [
            'name.required'                  => 'お名前を入力してください',
            'name.string'                    => 'お名前は文字列で入力してください',
            'name.max'                       => 'お名前は20文字以内で入力してください',
            'email.required'                 => 'メールアドレスを入力してください',
            'email.email'                    => '有効なメールアドレスを入力してください',
            'password.required'              => 'パスワードを入力してください',
            'password.min'                   => 'パスワードは8文字以上で入力してください',
            'password.confirmed'             => 'パスワードと一致しません',
            'password_confirmation.required' => 'パスワードを入力してください',
            'password_confirmation.min'      => 'パスワードは8文字以上で入力してください',
        ];
    }
}