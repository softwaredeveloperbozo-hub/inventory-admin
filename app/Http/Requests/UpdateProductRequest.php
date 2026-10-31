<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'sku'         => ['sometimes', 'required', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product)],
            'barcode'     => ['sometimes', 'nullable', 'string', 'max:120', Rule::unique('products', 'barcode')->ignore($product)],
            'name'        => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'tax_rate'    => ['sometimes', 'numeric', 'between:0,100'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
