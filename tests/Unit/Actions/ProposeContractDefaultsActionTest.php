<?php

use App\Actions\ProposeContractDefaultsAction;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Contract;

beforeEach(function () {
    app()->setLocale('es');
    $this->client = Client::factory()->create();
});

function previousContract(Client $client, array $attributes = []): Contract
{
    $budget = Budget::factory()->accepted()->monthly()->create(['client_id' => $client->id]);

    return Contract::factory()->create(['budget_id' => $budget->id, ...$attributes]);
}

it('RF-13: un cliente sin contratos anteriores no recibe ninguna propuesta', function () {
    $proposal = app(ProposeContractDefaultsAction::class)->handle($this->client);

    expect($proposal)->toBe(['due_day' => null, 'term_months' => null, 'indefinite' => false, 'start_date' => null]);
});

it('RF-13: propone el día de vencimiento y el plazo del contrato de fecha de inicio más reciente, de cualquier estado', function () {
    previousContract($this->client, ['start_date' => '2026-01-01', 'due_day' => 5])->forceFill(['term_months' => 6])->save();
    previousContract($this->client, ['start_date' => '2026-08-01', 'due_day' => 20, 'term_months' => 12, 'term_end_month' => '2027-07-01'])
        ->forceFill(['status' => 'cancelled'])->save();

    $proposal = app(ProposeContractDefaultsAction::class)->handle($this->client);

    expect($proposal['due_day'])->toBe(20)
        ->and($proposal['term_months'])->toBe(12)
        ->and($proposal['indefinite'])->toBeFalse();
});

it('RF-13: ante igual fecha de inicio propone el contrato dado de alta más recientemente', function () {
    previousContract($this->client, ['start_date' => '2026-03-01', 'due_day' => 5, 'term_months' => 3, 'term_end_month' => '2026-05-01']);
    previousContract($this->client, ['start_date' => '2026-03-01', 'due_day' => 15, 'term_months' => 9, 'term_end_month' => '2026-11-01']);

    $proposal = app(ProposeContractDefaultsAction::class)->handle($this->client);

    expect($proposal['due_day'])->toBe(15)
        ->and($proposal['term_months'])->toBe(9);
});

it('RF-13: ignora los contratos de otros clientes', function () {
    previousContract(Client::factory()->create(), ['start_date' => '2026-09-01', 'due_day' => 27, 'term_months' => 3, 'term_end_month' => '2026-11-01']);
    previousContract($this->client, ['start_date' => '2026-02-01', 'due_day' => 8, 'term_months' => 4, 'term_end_month' => '2026-05-01']);

    expect(app(ProposeContractDefaultsAction::class)->handle($this->client)['due_day'])->toBe(8);
});

it('RF-14: si el contrato anterior es de plazo fijo propone como inicio el mes siguiente a su último mes', function (string $lastMonth, string $expected) {
    previousContract($this->client, ['start_date' => '2026-01-10', 'term_months' => 12, 'term_end_month' => $lastMonth]);

    expect(app(ProposeContractDefaultsAction::class)->handle($this->client)['start_date'])->toBe($expected);
})->with([
    'dentro del año' => ['2026-07-01', '2026-08-01'],
    'cruzando el año' => ['2026-12-01', '2027-01-01'],
]);

it('RF-15: si el contrato anterior es indefinido no propone fecha de inicio, pero sí el vencimiento', function () {
    previousContract($this->client, ['start_date' => '2026-02-01', 'due_day' => 12, 'term_months' => null, 'term_end_month' => null]);

    $proposal = app(ProposeContractDefaultsAction::class)->handle($this->client);

    expect($proposal['start_date'])->toBeNull()
        ->and($proposal['indefinite'])->toBeTrue()
        ->and($proposal['term_months'])->toBeNull()
        ->and($proposal['due_day'])->toBe(12);
});

it('RF-17: nunca propone el importe mensual, que sale del presupuesto de origen', function () {
    previousContract($this->client, ['monthly_amount' => '7777.00', 'term_months' => 6, 'term_end_month' => '2026-12-01']);

    $proposal = app(ProposeContractDefaultsAction::class)->handle($this->client);

    expect(array_keys($proposal))->toBe(['due_day', 'term_months', 'indefinite', 'start_date'])
        ->and(json_encode($proposal))->not->toContain('7777');
});

it('RF-13: la propuesta no persiste nada', function () {
    previousContract($this->client, ['term_months' => 6, 'term_end_month' => '2026-12-01']);
    $before = Contract::withTrashed()->count();

    app(ProposeContractDefaultsAction::class)->handle($this->client);

    expect(Contract::withTrashed()->count())->toBe($before);
});
