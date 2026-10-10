<?php

use App\Actions\CreateContractAction;
use App\Exceptions\ContractTermElapsedException;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    Carbon::setTestNow(CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'));
    $this->author = User::factory()->staff()->create();
    $this->budget = Budget::factory()->accepted()->monthly()->create(['total' => '1500.00']);
});

afterEach(fn () => Carbon::setTestNow());

function contractInput(array $overrides = []): array
{
    return array_merge(['start_date' => '2026-10-01', 'due_day' => 10, 'term_months' => 6], $overrides);
}

function contractErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-7: exige la fecha de inicio, el día de vencimiento y el plazo', function () {
    $errors = contractErrors(fn () => app(CreateContractAction::class)->handle($this->budget, [], $this->author));

    expect($errors)->toHaveKeys(['start_date', 'due_day', 'term_months'])
        ->and(Contract::withTrashed()->count())->toBe(0);
});

it('RF-8: el plazo es una cantidad entera de meses mayor que cero o indefinido', function (mixed $term) {
    $errors = contractErrors(fn () => app(CreateContractAction::class)
        ->handle($this->budget, contractInput(['term_months' => $term]), $this->author));

    expect($errors)->toHaveKey('term_months')
        ->and(Contract::withTrashed()->count())->toBe(0);
})->with([
    'cero' => [0],
    'negativo' => [-3],
    'decimal' => [1.5],
    'texto' => ['seis'],
]);

it('RF-8: admite un plazo fijo de meses, como número o como texto', function (mixed $term) {
    $contract = app(CreateContractAction::class)
        ->handle($this->budget, contractInput(['term_months' => $term]), $this->author)->refresh();

    expect($contract->term_months)->toBe((int) $term)
        ->and($contract->isIndefinite())->toBeFalse();
})->with([1, 12, '6']);

it('RF-8: admite un contrato indefinido, sin plazo ni fin de plazo', function () {
    $contract = app(CreateContractAction::class)
        ->handle($this->budget, ['start_date' => '2026-10-01', 'due_day' => 10, 'indefinite' => true], $this->author)->refresh();

    expect($contract->term_months)->toBeNull()
        ->and($contract->term_end_month)->toBeNull()
        ->and($contract->isIndefinite())->toBeTrue();
});

it('RF-9: el día de vencimiento es un entero entre 1 y 28', function (mixed $day) {
    $errors = contractErrors(fn () => app(CreateContractAction::class)
        ->handle($this->budget, contractInput(['due_day' => $day]), $this->author));

    expect($errors)->toHaveKey('due_day')
        ->and($errors['due_day'][0])->toContain('28')
        ->and(Contract::withTrashed()->count())->toBe(0);
})->with([
    'cero' => [0],
    '29' => [29],
    '31' => [31],
    'negativo' => [-1],
    'decimal' => [5.5],
    'texto' => ['quince'],
]);

it('RF-9: admite los días 1 y 28, como número o como texto', function (mixed $day) {
    $contract = app(CreateContractAction::class)
        ->handle($this->budget, contractInput(['due_day' => $day]), $this->author);

    expect($contract->refresh()->due_day)->toBe((int) $day);
})->with([1, 28, '15']);

it('RF-11: admite una fecha de inicio pasada, actual o futura, y rechaza una que no es fecha', function () {
    foreach (['2026-08-20', '2026-10-15', '2027-05-01'] as $index => $date) {
        $budget = Budget::factory()->accepted()->monthly()->create(['total' => '800.00']);

        $contract = app(CreateContractAction::class)
            ->handle($budget, ['start_date' => $date, 'due_day' => 5, 'indefinite' => true], $this->author);

        expect($contract->refresh()->start_date->toDateString())->toBe($date);
    }

    $errors = contractErrors(fn () => app(CreateContractAction::class)
        ->handle($this->budget, contractInput(['start_date' => 'no es una fecha']), $this->author));

    expect($errors)->toHaveKey('start_date');
});

it('RF-12: calcula el último mes del plazo fijo a partir del mes de inicio', function (string $start, int $months, string $lastMonth) {
    $contract = app(CreateContractAction::class)
        ->handle($this->budget, ['start_date' => $start, 'due_day' => 10, 'term_months' => $months], $this->author);

    expect($contract->refresh()->term_end_month->toDateString())->toBe($lastMonth);
})->with([
    'seis meses desde octubre' => ['2026-10-15', 6, '2027-03-01'],
    'un solo mes, a fin de mes' => ['2026-10-31', 1, '2026-10-01'],
    'doce meses que cruzan el año' => ['2026-11-01', 12, '2027-10-01'],
]);

it('RF-12: rechaza con motivo un plazo fijo cuyo mes siguiente al último ya comenzó', function (string $start, int $months) {
    try {
        app(CreateContractAction::class)->handle($this->budget, ['start_date' => $start, 'due_day' => 10, 'term_months' => $months], $this->author);
        $this->fail('Debió rechazar el alta.');
    } catch (ContractTermElapsedException $exception) {
        expect($exception->getMessage())->toContain('plazo');
    }

    expect(Contract::withTrashed()->count())->toBe(0);
})->with([
    'terminó en marzo' => ['2026-01-10', 3],
    'terminó el mes pasado' => ['2026-08-05', 2],
]);

it('RF-12: admite un plazo fijo que termina este mes o más adelante, y un indefinido con inicio lejano', function () {
    $endsThisMonth = app(CreateContractAction::class)
        ->handle($this->budget, ['start_date' => '2026-09-05', 'due_day' => 10, 'term_months' => 2], $this->author);

    $old = Budget::factory()->accepted()->monthly()->create(['total' => '900.00']);
    $indefinite = app(CreateContractAction::class)
        ->handle($old, ['start_date' => '2019-01-10', 'due_day' => 10, 'indefinite' => true], $this->author);

    expect($endsThisMonth->refresh()->term_end_month->toDateString())->toBe('2026-10-01')
        ->and($indefinite->refresh()->isIndefinite())->toBeTrue();
});

it('RF-12: el mes se cuenta en hora argentina, no en UTC', function () {
    // 01:30 UTC del 1 de noviembre son las 22:30 del 31 de octubre en Argentina.
    Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 01:30:00', 'UTC'));

    $contract = app(CreateContractAction::class)
        ->handle($this->budget, ['start_date' => '2026-09-05', 'due_day' => 10, 'term_months' => 2], $this->author);

    expect($contract->refresh()->term_end_month->toDateString())->toBe('2026-10-01');
});
