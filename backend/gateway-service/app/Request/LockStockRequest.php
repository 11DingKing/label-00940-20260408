<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class LockStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'order_no' => 'required|string|max:32',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => '商品列表不能为空',
            'items.array' => '商品列表格式错误',
            'items.*.product_id.required' => '商品ID不能为空',
            'items.*.quantity.required' => '数量不能为空',
            'order_no.required' => '订单号不能为空',
        ];
    }
}
