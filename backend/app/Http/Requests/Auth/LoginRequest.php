<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a password-grant login. `otp` is only required when the account has
 * TOTP enabled; that conditional check happens in the controller after the
 * password is verified (so we don't leak whether 2FA is on before auth).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            // A 6–8 digit TOTP code, or a one-time recovery code (longer). The
            // controller decides which it is after the password is verified.
            'otp' => ['nullable', 'string', 'min:6', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
