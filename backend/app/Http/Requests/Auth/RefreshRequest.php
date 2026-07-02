<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Exchanges an opaque refresh token for a new access/refresh pair. The token is
 * the long-lived secret returned at login; it is looked up server-side by hash
 * (never stored in plaintext) and rotated on every successful use.
 */
class RefreshRequest extends FormRequest
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
            'refresh_token' => ['required', 'string', 'min:40', 'max:120'],
        ];
    }
}
