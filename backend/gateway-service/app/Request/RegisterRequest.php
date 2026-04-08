<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|min:3|max:50',
            'password' => 'required|string|min:6|max:50',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => '用户名不能为空',
            'username.min' => '用户名至少3个字符',
            'username.max' => '用户名最多50个字符',
            'password.required' => '密码不能为空',
            'password.min' => '密码至少6个字符',
            'email.email' => '邮箱格式不正确',
        ];
    }
}
