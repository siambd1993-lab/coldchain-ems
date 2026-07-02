<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    ['tenant' => $this->tenant, 'branch' => $this->branch] = createTenant();
});

// ── Happy path ────────────────────────────────────────────────────────────────

test('authenticated user receives JWT tokens on login', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $response = $this->postJson('/api/v1/auth/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'access_token',
                'token_type',
                'expires_in',
                'refresh_token',
                'user' => ['id', 'email', 'roles'],
            ],
        ]);

    expect($response->json('data.token_type'))->toBe('Bearer');
    expect($response->json('data.access_token'))->toBeString()->not->toBeEmpty();
    expect($response->json('data.refresh_token'))->toBeString()->not->toBeEmpty();
});

test('login returns user roles in response', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Accountant);

    $response = $this->postJson('/api/v1/auth/login', [
        'email'    => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk();
    expect($response->json('data.user.roles'))->toContain(RoleEnum::Accountant->value);
});

// ── Validation ────────────────────────────────────────────────────────────────

test('login fails with wrong password', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $this->postJson('/api/v1/auth/login', [
        'email'    => $user->email,
        'password' => 'wrong-password',
    ])->assertUnauthorized();
});

test('login fails with non-existent email', function (): void {
    $this->postJson('/api/v1/auth/login', [
        'email'    => 'nobody@example.test',
        'password' => 'password',
    ])->assertUnauthorized();
});

test('login rejects missing fields', function (): void {
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable();
});

test('suspended user cannot log in', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);
    $user->update(['status' => 'suspended']);

    $this->postJson('/api/v1/auth/login', [
        'email'    => $user->email,
        'password' => 'password',
    ])->assertUnauthorized();
});

// ── Auth/me ───────────────────────────────────────────────────────────────────

test('/me returns the authenticated user', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::TenantAdmin);

    $this->getJson('/api/v1/auth/me', apiHeaders($user))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

test('/me fails without a token', function (): void {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

// ── Refresh ───────────────────────────────────────────────────────────────────

test('refresh token rotates and returns new tokens', function (): void {
    $user = createUser($this->tenant, $this->branch, RoleEnum::Operator);

    $login = $this->postJson('/api/v1/auth/login', [
        'email'    => $user->email,
        'password' => 'password',
    ])->assertOk();

    $refreshToken = $login->json('data.refresh_token');

    $refresh = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $refreshToken,
    ])->assertOk();

    // New tokens must be issued.
    expect($refresh->json('data.access_token'))->toBeString()->not->toBeEmpty();
    expect($refresh->json('data.refresh_token'))->toBeString()->not->toBeEmpty();

    // Old refresh token must be revoked (reuse attempt → 401).
    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $refreshToken,
    ])->assertUnauthorized();
});

test('refresh with an invalid token returns 401', function (): void {
    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => str_repeat('x', 80),
    ])->assertUnauthorized();
});

// ── Logout ────────────────────────────────────────────────────────────────────

test('logout invalidates the access token', function (): void {
    $user    = createUser($this->tenant, $this->branch, RoleEnum::Operator);
    $headers = apiHeaders($user);

    $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();

    // Same token should now be rejected.
    $this->getJson('/api/v1/auth/me', $headers)->assertUnauthorized();
});
