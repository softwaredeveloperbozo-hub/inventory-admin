<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sku'         => ['required', 'string', 'max:80', 'unique:products,sku'],
            'barcode'     => ['nullable', 'string', 'max:120', 'unique:products,barcode'],
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'tax_rate'    => ['sometimes', 'numeric', 'between:0,100'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
