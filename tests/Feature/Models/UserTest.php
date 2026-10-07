<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

it('RF-3: la cuenta conserva un único rol al sincronizar', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create();

    $user->syncRoles(['staff']);
    $user->syncRoles(['admin']);

    expect($user->roles)->toHaveCount(1)
        ->and($user->hasRole('admin'))->toBeTrue();
});

it('RF-34: la cuenta nace activa y is_active se expone como booleano', function () {
    $user = User::query()->create([
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
    ]);

    expect($user->refresh()->is_active)->toBeTrue();
});
