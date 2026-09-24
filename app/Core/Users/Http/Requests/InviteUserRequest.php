<?php

namespace App\Core\Users\Http\Requests;

use App\Core\Users\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invite', User::class);
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $pending = Invitation::where('email', $value)
                        ->whereNull('accepted_at')
                        ->where('expires_at', '>', now())
                        ->exists();

                    if ($pending) {
                        $fail('È già stato inviato un invito a questo indirizzo email.');
                    }
                },
            ],
            'role' => [
                'required', 'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
        ];
    }
}
