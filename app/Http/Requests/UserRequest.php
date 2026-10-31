<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // "Maria@Teste.com" e "maria@teste.com" são a mesma pessoa
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $presence = $creating ? ['required'] : ['sometimes', 'required'];

        return [
            'name'      => [...$presence, 'string', 'max:100'],
            'email'     => [...$presence, 'email', 'max:120', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role'      => [...$presence, 'in:admin,operator'],
            'is_active' => ['sometimes', 'boolean'],
            'password'  => [$creating ? 'required' : 'sometimes', 'string', 'min:8', 'max:72'],
        ];
    }
}
