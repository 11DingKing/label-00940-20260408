<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class CreateOrderRequest extends FormRequest
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
            'address' => 'required|string|max:255',
            'receiver' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
            'remark' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => '订单商品不能为空',
            'items.min' => '至少选择一件商品',
            'items.*.product_id.required' => '商品ID不能为空',
            'items.*.quantity.required' => '商品数量不能为空',
            'items.*.quantity.min' => '商品数量至少为1',
            'address.required' => '收货地址不能为空',
            'receiver.required' => '收货人不能为空',
            'phone.required' => '联系电话不能为空',
        ];
    }
}
