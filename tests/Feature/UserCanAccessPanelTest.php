<?php

use App\Enums\TipoUsuario;
use App\Models\User;
use Filament\Facades\Filament;

it('lets staff into the admin panel and blocks the cliente panel', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($user->canAccessPanel(Filament::getPanel('cliente')))->toBeFalse();
});

it('lets a cliente user with cliente_id into the cliente panel and blocks admin', function () {
    $user = User::factory()->cliente()->create();

    expect($user->canAccessPanel(Filament::getPanel('cliente')))->toBeTrue()
        ->and($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('blocks a cliente user without cliente_id from every panel', function () {
    $user = User::factory()->create([
        'tipo' => TipoUsuario::Cliente,
        'cliente_id' => null,
    ]);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($user->canAccessPanel(Filament::getPanel('cliente')))->toBeFalse();
});
