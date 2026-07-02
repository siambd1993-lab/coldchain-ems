<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Resources\UserResource;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\TokenIssuer;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;

/**
 * Password-grant authentication with an optional TOTP second factor and rotating
 * refresh tokens.
 *
 * These endpoints (except {@see me} and {@see logout}) run *before* the tenant is
 * resolved — they are the front door, so the {@see \App\Http\Middleware\ResolveTenant}
 * middleware is deliberately not in front of them. The JWT the caller receives
 * carries the tenant/role claims that every subsequent request is scoped by.
 */
final class AuthController extends Controller
{
    /** TOTP validation window (± this many 30s steps) to absorb minor clock drift. */
    private const OTP_WINDOW = 1;

    public function __construct(
        private readonly TokenIssuer $tokens,
        private readonly AuditLogger $audit,
        private readonly Google2FA $google2fa,
    ) {
    }

    /**
     * Exchange email + password (+ TOTP when enabled) for a token bundle.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Unscoped lookup: no tenant context exists yet, and email is globally
        // unique. A generic failure message avoids account enumeration.
        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], (string) $user->password)) {
            return ApiError::make(
                'invalid_credentials',
                'The provided credentials are incorrect.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // 401 (not 403): the caller holds no valid session, and the login
        // endpoint reports every rejection the same way.
        if (! $user->isActive()) {
            return ApiError::make(
                'account_inactive',
                'This account is not active. Please contact your administrator.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // Second factor is only challenged *after* the password checks out, so an
        // unauthenticated caller can never learn whether 2FA is on for an account.
        if ($user->hasTwoFactorEnabled()) {
            $otp = isset($data['otp']) ? trim((string) $data['otp']) : '';

            if ($otp === '') {
                return ApiError::make(
                    'otp_required',
                    'A two-factor authentication code is required.',
                    Response::HTTP_UNAUTHORIZED,
                    ['two_factor' => true],
                );
            }

            if (! $this->verifyTwoFactor($user, $otp)) {
                return ApiError::make(
                    'otp_invalid',
                    'The two-factor authentication code is invalid.',
                    Response::HTTP_UNAUTHORIZED,
                    ['two_factor' => true],
                );
            }
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        // Relations must be present before minting the JWT (custom claims read
        // roles/branches) and before the resource serialises them.
        $user->loadMissing(['roles', 'branches']);

        $bundle = $this->tokens->issue($user, $request);

        // The login route runs before ResolveTenant, so the context has no actor
        // yet — supply it explicitly rather than letting the logger read a blank.
        $this->audit->log('auth.login', $user, [
            'description' => 'User signed in.',
            'tenant_id' => $user->tenant_id,
            'actor_id' => $user->getKey(),
            'actor_label' => $user->email,
            'new' => ['device_name' => $data['device_name'] ?? null],
        ]);

        return $this->tokenResponse($bundle, $user);
    }

    /**
     * Rotate a refresh token for a fresh access/refresh pair. Detects replay of a
     * consumed token and burns the whole family when it happens.
     */
    public function refresh(RefreshRequest $request): JsonResponse
    {
        $presented = $request->validated()['refresh_token'];

        /** @var RefreshToken|null $token */
        $token = RefreshToken::query()
            ->where('token_hash', RefreshToken::hash($presented))
            ->first();

        if ($token === null) {
            return ApiError::make(
                'invalid_refresh_token',
                'The refresh token is invalid.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // A token that exists but is already revoked means someone replayed a
        // rotated secret → treat as theft and revoke the entire family.
        if ($token->isRevoked()) {
            $this->tokens->revokeFamily($token->family_id, 'reuse_detected');

            return ApiError::make(
                'refresh_token_reused',
                'Refresh token reuse detected. Please sign in again.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if ($token->isExpired()) {
            return ApiError::make(
                'refresh_token_expired',
                'The refresh token has expired. Please sign in again.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        /** @var User|null $user */
        $user = $token->user()->first();

        if ($user === null || ! $user->isActive()) {
            $this->tokens->revokeFamily($token->family_id, 'user_inactive');

            return ApiError::make(
                'account_inactive',
                'This account is no longer active.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $user->loadMissing(['roles', 'branches']);

        $bundle = $this->tokens->rotate($token, $user, $request);

        return $this->tokenResponse($bundle, $user);
    }

    /**
     * The authenticated principal, with resolved roles, permissions and branches.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'branches']);

        return response()->json(['data' => new UserResource($user)]);
    }

    /**
     * Sign out. Blacklists the presented access token and revokes refresh tokens —
     * just the current family by default, or every session with `all=true`.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Blacklist the access token so it cannot be replayed before it expires.
        // Idempotent: a token that is already invalid is fine to "log out" again.
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Throwable) {
            // no-op
        }

        $all = $request->boolean('all');

        if ($all) {
            $this->tokens->revokeAllForUser($user, 'logout_all');
        } else {
            $presented = $request->input('refresh_token');

            if (is_string($presented) && $presented !== '') {
                $token = RefreshToken::query()
                    ->where('token_hash', RefreshToken::hash($presented))
                    ->first();

                if ($token !== null) {
                    $this->tokens->revokeFamily($token->family_id, 'logout');
                }
            }
        }

        $this->audit->log('auth.logout', $user, [
            'description' => $all ? 'User signed out of all sessions.' : 'User signed out.',
        ]);

        return response()->json(['data' => ['message' => 'Signed out.']]);
    }

    /**
     * Verify a submitted second factor: first as a TOTP code, then — failing that
     * — as a single-use recovery code (which is consumed on success).
     */
    private function verifyTwoFactor(User $user, string $otp): bool
    {
        $secret = $user->two_factor_secret;

        if (is_string($secret) && $secret !== '' && $this->google2fa->verifyKey($secret, $otp, self::OTP_WINDOW)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $otp);
    }

    /**
     * Burn a matching recovery code. Returns true and removes it on match, false
     * otherwise. Comparison is constant-time to avoid leaking code contents.
     */
    private function consumeRecoveryCode(User $user, string $candidate): bool
    {
        $codes = $user->two_factor_recovery_codes;

        if (! is_array($codes) || $codes === []) {
            return false;
        }

        $matched = false;
        $remaining = [];

        foreach ($codes as $code) {
            if (! $matched && hash_equals((string) $code, $candidate)) {
                $matched = true; // drop it (single-use)

                continue;
            }

            $remaining[] = $code;
        }

        if (! $matched) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => array_values($remaining)])->save();

        return true;
    }

    /**
     * Shape the standard token payload with the embedded user.
     *
     * @param  array<string, mixed>  $bundle
     */
    private function tokenResponse(array $bundle, User $user): JsonResponse
    {
        return response()->json([
            'data' => $bundle + ['user' => new UserResource($user)],
        ]);
    }
}
