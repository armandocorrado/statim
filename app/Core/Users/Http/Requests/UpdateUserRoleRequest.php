<?php

namespace App\Core\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required', 'string',
                Rule::exists('roles', 'name')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('guard_name', 'web'),
            ],
        ];
    }
}
