<?php

use App\Actions\CreateBudgetAction;
use App\Enums\BudgetModality;
use App\Enums\BudgetStatus;
use App\Models\Budget;
use App\Models\Client;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->author = User::factory()->staff()->create();
    $this->client = Client::factory()->create();
});

function budgetData(array $overrides = []): array
{
    return array_merge([
        'client_id' => Client::query()->value('id'),
        'title' => 'Identidad visual completa',
        'modality' => 'single',
        'issue_date' => '2026-10-01',
        'validity_date' => '2026-10-31',
    ], $overrides);
}

function budgetErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-11, RF-12, RF-14: crea un presupuesto en borrador para un cliente existente, con todos sus datos y su autor', function () {
    $budget = app(CreateBudgetAction::class)->handle(budgetData(), $this->author)->refresh();

    expect($budget->client->is($this->client))->toBeTrue()
        ->and($budget->title)->toBe('Identidad visual completa')
        ->and($budget->status)->toBe(BudgetStatus::Draft)
        ->and($budget->modality)->toBe(BudgetModality::Single)
        ->and($budget->issue_date->toDateString())->toBe('2026-10-01')
        ->and($budget->validity_date->toDateString())->toBe('2026-10-31')
        ->and($budget->created_by)->toBe($this->author->id)
        ->and($budget->subtotal)->toBe('0.00')
        ->and($budget->discount_amount)->toBe('0.00')
        ->and($budget->total)->toBe('0.00');
});

it('RF-13: cada presupuesto recibe un identificador correlativo único que no se reutiliza', function () {
    $first = app(CreateBudgetAction::class)->handle(budgetData(), $this->author);
    $second = app(CreateBudgetAction::class)->handle(budgetData(['title' => 'Otro']), $this->author);

    $second->delete();

    $third = app(CreateBudgetAction::class)->handle(budgetData(['title' => 'Un tercero']), $this->author);

    expect($second->id)->toBe($first->id + 1)
        ->and($third->id)->toBe($second->id + 1)
        ->and(Budget::withTrashed()->pluck('id')->unique()->count())->toBe(3);
});

it('RF-15: la modalidad es exactamente una entre pago único y abono mensual', function (mixed $modality, bool $valid) {
    $errors = budgetErrors(fn () => app(CreateBudgetAction::class)->handle(budgetData(['modality' => $modality]), $this->author));

    expect(array_key_exists('modality', $errors))->toBe(! $valid);
})->with([
    'pago único' => ['single', true],
    'abono mensual' => ['monthly', true],
    'vacía' => ['', false],
    'desconocida' => ['quarterly', false],
]);

it('RF-14: exige un título de hasta 150 caracteres', function () {
    expect(budgetErrors(fn () => app(CreateBudgetAction::class)->handle(budgetData(['title' => '']), $this->author)))->toHaveKey('title')
        ->and(budgetErrors(fn () => app(CreateBudgetAction::class)->handle(budgetData(['title' => str_repeat('a', 151)]), $this->author)))->toHaveKey('title')
        ->and(budgetErrors(fn () => app(CreateBudgetAction::class)->handle(budgetData(['title' => str_repeat('a', 150)]), $this->author)))->toBe([]);
});

it('RF-17: sin cliente destinatario no se crea y se informa el motivo', function (mixed $client) {
    $errors = budgetErrors(fn () => app(CreateBudgetAction::class)->handle(budgetData(['client_id' => $client]), $this->author));

    expect($errors)->toHaveKey('client_id')
        ->and($errors['client_id'][0])->toContain('cliente')
        ->and(Budget::query()->count())->toBe(0);
})->with([
    'vacío' => '',
    'nulo' => null,
    'inexistente' => 9999,
]);

it('RF-18: la fecha de validez es obligatoria', function () {
    $errors = budgetErrors(fn () => app(CreateBudgetAction::class)->handle(budgetData(['validity_date' => '']), $this->author));

    expect($errors)->toHaveKey('validity_date')
        ->and(Budget::query()->count())->toBe(0);
});

it('RF-19: la fecha de validez debe ser posterior a la de emisión', function (string $validity, bool $valid) {
    $errors = budgetErrors(fn () => app(CreateBudgetAction::class)->handle(
        budgetData(['issue_date' => '2026-10-10', 'validity_date' => $validity]),
        $this->author,
    ));

    expect(array_key_exists('validity_date', $errors))->toBe(! $valid);

    if (! $valid) {
        expect($errors['validity_date'][0])->toContain('posterior');
    }
})->with([
    'el día siguiente' => ['2026-10-11', true],
    'mucho después' => ['2027-01-01', true],
    'el mismo día' => ['2026-10-10', false],
    'el día anterior' => ['2026-10-09', false],
    'mucho antes' => ['2026-01-01', false],
]);

it('RF-14: sin fecha de emisión se toma la de hoy', function () {
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(9, 0));

    $budget = app(CreateBudgetAction::class)->handle(
        budgetData(['issue_date' => null, 'validity_date' => '2026-11-07']),
        $this->author,
    );

    expect($budget->refresh()->issue_date->toDateString())->toBe('2026-10-07');
});

it('RF-20: se puede crear un presupuesto para un cliente inactivo o archivado', function (string $state) {
    $client = match ($state) {
        'inactivo' => Client::factory()->inactive()->create(),
        'archivado' => Client::factory()->archived()->create(),
    };

    $budget = app(CreateBudgetAction::class)->handle(budgetData(['client_id' => $client->id]), $this->author);

    expect($budget->refresh()->client_id)->toBe($client->id)
        ->and($budget->client->is($client))->toBeTrue();
})->with(['inactivo', 'archivado']);
