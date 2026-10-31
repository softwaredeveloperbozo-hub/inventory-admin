<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => [
                'nullable', 'integer',
                Rule::exists('suppliers', 'id')->where('is_active', true),
            ],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity'     => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'items.*.cost'         => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.sale_price'   => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }
}
