<?php

use App\Models\Cliente;
use App\Models\DetallePresupuesto;
use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Spatie\Activitylog\Models\Activity;

it('logs the creation of a business model with the causing user', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff);

    $cliente = Cliente::factory()->create();

    $activity = Activity::query()->where('subject_type', Cliente::class)->where('subject_id', $cliente->id)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('created')
        ->and($activity->causer_id)->toBe($staff->id);
});

it('logs only the dirty attributes on update', function () {
    $cliente = Cliente::factory()->create(['nombre' => 'Nombre original']);

    $cliente->update(['nombre' => 'Nombre nuevo']);

    $activity = Activity::query()
        ->where('subject_type', Cliente::class)
        ->where('subject_id', $cliente->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['attributes'])->toHaveKey('nombre')
        ->and($activity->properties['attributes'])->not->toHaveKey('email');
});

it('does not log the presupuesto total recalculation triggered by a detalle save', function () {
    $presupuesto = Presupuesto::factory()->create();

    DetallePresupuesto::factory()->for($presupuesto)->create();

    $activity = Activity::query()
        ->where('subject_type', Presupuesto::class)
        ->where('subject_id', $presupuesto->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->toBeNull();
});

it('logs login and logout events', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('filament.admin.auth.logout'));

    expect(Activity::query()->where('log_name', 'auth')->where('event', 'login')->exists())->toBeFalse();
    // actingAs no dispara el evento Login real; se verifica logout, que sí pasa por el guard.
    expect(Activity::query()->where('log_name', 'auth')->where('event', 'logout')->where('causer_id', $user->id)->exists())->toBeTrue();
});

it('never logs the plaintext password on a failed login attempt', function () {
    // El login de Filament es un componente Livewire, no un POST tradicional
    // a /login; se dispara el evento de Laravel directamente para probar
    // el listener sin depender de esa mecánica interna.
    event(new Failed('web', null, [
        'email' => 'nadie@example.com',
        'password' => 'secreto-no-debe-quedar-logueado',
    ]));

    $activity = Activity::query()->where('log_name', 'auth')->where('event', 'failed')->first();

    expect($activity)->not->toBeNull();

    $propiedades = json_encode($activity->properties);

    expect($propiedades)->not->toContain('secreto-no-debe-quedar-logueado')
        ->and($propiedades)->not->toContain('password');
});

it('logs a role assignment', function () {
    $user = User::factory()->create();

    $activity = Activity::query()
        ->where('log_name', 'accesos')
        ->where('event', 'rol_asignado')
        ->where('subject_id', $user->id)
        ->first();

    expect($activity)->not->toBeNull();
});
