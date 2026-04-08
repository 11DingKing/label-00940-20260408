<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => '邮箱格式不正确',
        ];
    }
}
