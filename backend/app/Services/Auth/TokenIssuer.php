<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Mints the token bundle returned to clients: a short-lived JWT access token
 * (with tenant/role claims via {@see User::getJWTCustomClaims}) plus a long-lived,
 * opaque, rotating refresh token stored server-side by hash.
 *
 * Rotation + reuse detection: each refresh consumes the presented token and
 * issues a successor in the same `family_id`. Presenting an already-consumed
 * token (theft/replay) is caught by the caller, which then revokes the family.
 */
final class TokenIssuer
{
    /**
     * Issue a fresh bundle at login — starts a new refresh-token family.
     *
     * @return array<string, mixed>
     */
    public function issue(User $user, ?Request $request = null): array
    {
        $refresh = $this->createRefreshToken($user, (string) Str::uuid(), null, $request);

        return $this->bundle($user, $refresh);
    }

    /**
     * Rotate a valid refresh token: revoke it and issue a successor in the same
     * family, together with a new access token.
     *
     * @return array<string, mixed>
     */
    public function rotate(RefreshToken $current, User $user, Request $request): array
    {
        $current->revoke('rotated');

        $refresh = $this->createRefreshToken($user, $current->family_id, $current->getKey(), $request);

        return $this->bundle($user, $refresh);
    }

    /**
     * Revoke every live token in a family — used on reuse detection and on
     * "log out everywhere".
     */
    public function revokeFamily(string $familyId, string $reason = 'reuse_detected'): void
    {
        RefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
    }

    /** Revoke all of a user's refresh tokens (full sign-out). */
    public function revokeAllForUser(User $user, string $reason = 'logout_all'): void
    {
        RefreshToken::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
    }

    /**
     * @param  array{plaintext:string, model:RefreshToken}  $refresh
     * @return array<string, mixed>
     */
    private function bundle(User $user, array $refresh): array
    {
        return [
            'access_token' => JWTAuth::fromUser($user),
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl') * 60, // seconds
            'refresh_token' => $refresh['plaintext'],
            'refresh_expires_at' => $refresh['model']->expires_at->toIso8601String(),
        ];
    }

    /**
     * @return array{plaintext:string, model:RefreshToken}
     */
    private function createRefreshToken(
        User $user,
        string $familyId,
        ?int $previousId,
        ?Request $request,
    ): array {
        $plaintext = Str::random(80);

        $model = RefreshToken::create([
            'user_id' => $user->getKey(),
            'tenant_id' => $user->getAttribute('tenant_id'),
            'token_hash' => RefreshToken::hash($plaintext),
            'family_id' => $familyId,
            'previous_id' => $previousId,
            'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'ip' => $request?->ip(),
            'expires_at' => now()->addMinutes((int) config('jwt.refresh_ttl')),
        ]);

        return ['plaintext' => $plaintext, 'model' => $model];
    }
}
