<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|string|max:255',
            'category_id' => 'required|integer|min:1',
            'stock' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '商品名称不能为空',
            'price.required' => '商品价格不能为空',
            'price.numeric' => '商品价格必须是数字',
            'price.min' => '商品价格不能小于0',
            'category_id.required' => '商品分类不能为空',
        ];
    }
}
