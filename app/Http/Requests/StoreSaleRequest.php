<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name'      => ['nullable', 'string', 'max:150'],
            'payment_method'     => ['required', 'in:cash,card,pix,transfer'],
            'discount'           => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'sold_at'            => ['nullable', 'date', 'before_or_equal:now'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity'   => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ];
    }
}
