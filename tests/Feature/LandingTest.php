<?php

use Illuminate\Support\Facades\Route;

it('RF-25: la landing es accesible de forma anónima', function () {
    $this->get('/')
        ->assertOk()
        ->assertViewIs('landing.index')
        ->assertSee(__('landing.staff_access'))
        ->assertSee(route('filament.staff.auth.login'))
        ->assertSee(route('filament.client.auth.login'));

    $this->assertGuest();
});

it('RF-1: la aplicación no expone ninguna ruta de registro público', function () {
    $registrationRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => preg_match('/regist|sign-?up/i', $route->uri().' '.($route->getName() ?? '')))
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($registrationRoutes)->toBe([]);
});
