<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** Shared hashed value for the default "password" stub. */
    private static ?string $hashedPassword = null;

    public function definition(): array
    {
        return [
            'tenant_id'          => Tenant::factory(),
            'branch_id'          => null,
            'name'               => $this->faker->name(),
            'email'              => $this->faker->unique()->safeEmail(),
            'phone'              => '+8801' . $this->faker->numerify('#########'),
            'password'           => static::$hashedPassword ??= Hash::make('password'),
            'status'             => 'active',
            'is_platform_admin'  => false,
            'email_verified_at'  => now(),
            'settings'           => null,
        ];
    }

    /** Pre-verified email. */
    public function verified(): static
    {
        return $this->state(['email_verified_at' => now()]);
    }

    /** Unverified email. */
    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    /** Suspended user. */
    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }

    /** Platform-level super-admin (no tenant). */
    public function platformAdmin(): static
    {
        return $this->state([
            'tenant_id'         => null,
            'is_platform_admin' => true,
        ]);
    }

    /** Convenience: set a custom plain-text password (hashed). */
    public function withPassword(string $plain): static
    {
        return $this->state(['password' => Hash::make($plain)]);
    }
}
