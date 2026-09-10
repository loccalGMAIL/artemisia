<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('RF-3: asigna a la cuenta exactamente un rol', function () {
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->syncRoles(['staff']);

    expect($user->hasRole('staff'))->toBeTrue()
        ->and($user->roles)->toHaveCount(1);
});
