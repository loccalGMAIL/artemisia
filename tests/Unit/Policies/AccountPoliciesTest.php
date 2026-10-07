<?php

use App\Models\AccessLog;
use App\Models\AccountHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
});

it('RF-9: solo un admin puede ejecutar acciones de gestión de cuentas y roles', function (string $ability) {
    $target = User::factory()->staff()->create();

    expect(Gate::forUser(User::factory()->admin()->create())->allows($ability, $target))->toBeTrue();

    foreach (['staff', 'client'] as $role) {
        $inspection = Gate::forUser(User::factory()->{$role}()->create())->inspect($ability, $target);

        expect($inspection->denied())->toBeTrue()
            ->and($inspection->message())->toContain('permiso');
    }
})->with(['view', 'update', 'changeRole', 'activate', 'deactivate']);

it('RF-9: solo un admin puede listar y crear cuentas', function (string $ability) {
    expect(Gate::forUser(User::factory()->admin()->create())->allows($ability, User::class))->toBeTrue();

    foreach (['staff', 'client'] as $role) {
        $inspection = Gate::forUser(User::factory()->{$role}()->create())->inspect($ability, User::class);

        expect($inspection->denied())->toBeTrue()
            ->and($inspection->message())->toContain('permiso');
    }
})->with(['viewAny', 'create']);

it('RF-34: nadie puede eliminar una cuenta, ni siquiera un admin', function (string $ability) {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->staff()->create();

    expect(Gate::forUser($admin)->denies($ability, $target))->toBeTrue();
})->with(['delete', 'forceDelete', 'restore']);

it('RF-39: solo un admin puede consultar registros de acceso e historial de cuentas', function (string $model, string $ability) {
    $record = $model::factory()->create();

    expect(Gate::forUser(User::factory()->admin()->create())->allows($ability, $ability === 'viewAny' ? $model : $record))->toBeTrue();

    foreach (['staff', 'client'] as $role) {
        $inspection = Gate::forUser(User::factory()->{$role}()->create())->inspect($ability, $ability === 'viewAny' ? $model : $record);

        expect($inspection->denied())->toBeTrue()
            ->and($inspection->message())->toContain('permiso');
    }
})->with([
    'registros de acceso: listar' => [AccessLog::class, 'viewAny'],
    'registros de acceso: ver' => [AccessLog::class, 'view'],
    'historial de cuentas: listar' => [AccountHistory::class, 'viewAny'],
    'historial de cuentas: ver' => [AccountHistory::class, 'view'],
]);

it('RF-35, RNF-6: nadie puede editar ni borrar asientos de registros e historial', function (string $model, string $ability) {
    $admin = User::factory()->admin()->create();
    $record = $model::factory()->create();

    expect(Gate::forUser($admin)->denies($ability, $record))->toBeTrue();
})->with([
    'registro de acceso: editar' => [AccessLog::class, 'update'],
    'registro de acceso: borrar' => [AccessLog::class, 'delete'],
    'historial: editar' => [AccountHistory::class, 'update'],
    'historial: borrar' => [AccountHistory::class, 'delete'],
]);
