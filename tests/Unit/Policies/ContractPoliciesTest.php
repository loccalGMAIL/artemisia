<?php

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractPeriod;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    $this->contract = Contract::factory()->create();
    $this->period = ContractPeriod::factory()->for($this->contract)->create();
    $this->payment = Payment::factory()->for($this->period)->create();
});

/**
 * @return array<string, array{0: class-string, 1: string, 2: string}>
 */
function staffAbilities(): array
{
    return [
        'contrato: ver' => [Contract::class, 'view', 'contract'],
        'contrato: modificar' => [Contract::class, 'update', 'contract'],
        'contrato: cancelar' => [Contract::class, 'cancel', 'contract'],
        'contrato: reactivar' => [Contract::class, 'reactivate', 'contract'],
        'período: ver' => [ContractPeriod::class, 'view', 'period'],
        'período: modificar conceptos y factura' => [ContractPeriod::class, 'update', 'period'],
        'pago: anular' => [Payment::class, 'void', 'payment'],
    ];
}

it('RF-98: admin y staff tienen exactamente los mismos permisos sobre contratos, períodos y pagos', function (string $role) {
    $user = User::factory()->{$role}()->create();

    expect(Gate::getPolicyFor(Contract::class))->not->toBeNull()
        ->and(Gate::getPolicyFor(ContractPeriod::class))->not->toBeNull()
        ->and(Gate::getPolicyFor(Payment::class))->not->toBeNull();

    foreach (staffAbilities() as $name => [$class, $ability, $key]) {
        expect(Gate::forUser($user)->allows($ability, $this->{$key}))->toBeTrue("{$role} debería poder: {$name}");
    }

    foreach ([Contract::class, ContractPeriod::class, Payment::class] as $class) {
        expect(Gate::forUser($user)->allows('viewAny', $class))->toBeTrue("{$role} debería poder listar {$class}");
    }

    foreach ([Contract::class, Payment::class] as $class) {
        expect(Gate::forUser($user)->allows('create', $class))->toBeTrue("{$role} debería poder crear {$class}");
    }
})->with(['admin', 'staff']);

it('RF-98: una cuenta client es rechazada con permiso insuficiente, ni siquiera sobre los contratos de su cliente', function () {
    $account = User::factory()->client()->create(['client_id' => $this->contract->client_id]);

    foreach (staffAbilities() as $name => [$class, $ability, $key]) {
        $inspection = Gate::forUser($account)->inspect($ability, $this->{$key});

        expect($inspection->denied())->toBeTrue("client no debería poder: {$name}")
            ->and($inspection->message())->toContain('permiso');
    }

    foreach ([Contract::class, ContractPeriod::class, Payment::class] as $class) {
        expect(Gate::forUser($account)->denies('viewAny', $class))->toBeTrue("client no debería listar {$class}");
    }

    foreach ([Contract::class, Payment::class] as $class) {
        expect(Gate::forUser($account)->denies('create', $class))->toBeTrue("client no debería crear {$class}");
    }
});

it('RF-98: una cuenta sin rol no accede', function () {
    $user = User::factory()->create();

    expect(Gate::getPolicyFor(Contract::class))->not->toBeNull()
        ->and(Gate::forUser($user)->denies('view', $this->contract))->toBeTrue()
        ->and(Gate::forUser($user)->denies('create', Contract::class))->toBeTrue();
});

it('RF-34, RF-80: nadie elimina ni restaura un contrato o un pago, ni siquiera un admin', function (string $ability) {
    $admin = User::factory()->admin()->create();

    expect(Gate::getPolicyFor(Contract::class))->not->toBeNull()
        ->and(Gate::getPolicyFor(Payment::class))->not->toBeNull()
        ->and(Gate::forUser($admin)->denies($ability, $this->contract))->toBeTrue()
        ->and(Gate::forUser($admin)->denies($ability, $this->payment))->toBeTrue();
})->with(['delete', 'restore', 'forceDelete']);

it('RF-97: la cuenta vinculada puede ver los contratos de su propio cliente en el portal', function () {
    $account = User::factory()->client()->create(['client_id' => $this->contract->client_id]);

    expect(Gate::has('portal.contracts.view'))->toBeTrue()
        ->and(Gate::forUser($account)->allows('portal.contracts.view', $this->contract))->toBeTrue();
});

it('RF-97: un contrato de otro cliente se rechaza con permiso insuficiente', function () {
    $stranger = User::factory()->client()->create(['client_id' => Client::factory()->create()->id]);

    $inspection = Gate::forUser($stranger)->inspect('portal.contracts.view', $this->contract);

    expect(Gate::has('portal.contracts.view'))->toBeTrue()
        ->and($inspection->denied())->toBeTrue()
        ->and($inspection->message())->toContain('permiso');
});

it('RF-97: una cuenta sin vínculo a un cliente recibe el aviso de que su acceso no fue habilitado', function () {
    $account = User::factory()->client()->create();

    $inspection = Gate::forUser($account)->inspect('portal.contracts.view', $this->contract);

    expect(Gate::has('portal.contracts.view'))->toBeTrue()
        ->and($inspection->denied())->toBeTrue()
        ->and($inspection->message())->toContain('todavía no fue habilitado');
});

it('RF-97: las cuentas de staff no usan la habilidad del portal', function (string $role) {
    $user = User::factory()->{$role}()->create();

    expect(Gate::has('portal.contracts.view'))->toBeTrue()
        ->and(Gate::forUser($user)->denies('portal.contracts.view', $this->contract))->toBeTrue();
})->with(['admin', 'staff']);
