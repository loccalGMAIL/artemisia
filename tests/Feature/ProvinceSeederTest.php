<?php

use App\Models\Province;
use Database\Seeders\ProvinceSeeder;

it('RF-9: el seeder carga las 24 provincias argentinas de referencia', function () {
    $this->seed(ProvinceSeeder::class);

    expect(Province::query()->count())->toBe(24)
        ->and(Province::query()->where('name', 'Buenos Aires')->exists())->toBeTrue()
        ->and(Province::query()->where('name', 'Ciudad Autónoma de Buenos Aires')->exists())->toBeTrue()
        ->and(Province::query()->where('name', 'Tucumán')->exists())->toBeTrue();
});

it('RF-9: correr el seeder dos veces no duplica provincias', function () {
    $this->seed(ProvinceSeeder::class);
    $this->seed(ProvinceSeeder::class);

    expect(Province::query()->count())->toBe(24);
});
