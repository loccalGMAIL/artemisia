<?php

namespace Database\Factories;

use App\Enums\TipoUsuario;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'tipo' => TipoUsuario::Staff,
            'cliente_id' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Usuario de tipo Cliente, ligado a un Cliente existente (o uno nuevo si no se pasa).
     */
    public function cliente(?Cliente $cliente = null): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoUsuario::Cliente,
            'cliente_id' => $cliente?->id ?? Cliente::factory(),
        ]);
    }
}
