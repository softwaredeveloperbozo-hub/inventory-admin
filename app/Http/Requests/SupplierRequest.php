<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('POST')
            ? ['required']
            : ['sometimes', 'required'];

        return [
            'name'      => [...$presence, 'string', 'max:150'],
            'tax_id'    => [
                'sometimes', 'nullable', 'string', 'max:30',
                Rule::unique('suppliers', 'tax_id')->ignore($this->route('supplier')),
            ],
            'phone'     => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'     => ['sometimes', 'nullable', 'email', 'max:120'],
            'address'   => ['sometimes', 'nullable', 'string', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
