<?php

use App\Actions\UpdateBudgetHeaderAction;
use App\Enums\BudgetHistoryField;
use App\Enums\BudgetModality;
use App\Exceptions\BudgetNotEditableException;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\Client;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
    $this->budget = Budget::factory()->create([
        'title' => 'Identidad visual',
        'issue_date' => '2026-10-01',
        'validity_date' => '2026-10-31',
    ]);
});

function headerData(Budget $budget, array $overrides = []): array
{
    return array_merge([
        'client_id' => $budget->client_id,
        'title' => $budget->title,
        'modality' => $budget->modality->value,
        'issue_date' => $budget->issue_date->toDateString(),
        'validity_date' => $budget->validity_date->toDateString(),
    ], $overrides);
}

function headerErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-16, RF-33: modifica los datos de cabecera de un borrador y registra valor anterior, nuevo, autor y fecha', function () {
    app(UpdateBudgetHeaderAction::class)->handle($this->budget, headerData($this->budget, [
        'title' => 'Identidad visual y papelería',
        'modality' => 'monthly',
        'validity_date' => '2026-11-15',
    ]), $this->actor);

    $this->budget->refresh();

    expect($this->budget->title)->toBe('Identidad visual y papelería')
        ->and($this->budget->modality)->toBe(BudgetModality::Monthly)
        ->and($this->budget->validity_date->toDateString())->toBe('2026-11-15');

    $history = BudgetHistory::query()->where('budget_id', $this->budget->id)->sole();

    expect($history->field)->toBe(BudgetHistoryField::HeaderChanged)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->created_at)->not->toBeNull()
        ->and($history->old_value)->toBe(['title' => 'Identidad visual', 'modality' => 'single', 'validity_date' => '2026-10-31'])
        ->and($history->new_value)->toBe(['title' => 'Identidad visual y papelería', 'modality' => 'monthly', 'validity_date' => '2026-11-15']);
});

it('RF-16: también modifica la cabecera de un presupuesto enviado, incluido el cliente y la fecha de emisión', function () {
    $sent = Budget::factory()->sent()->create(['issue_date' => '2026-10-01', 'validity_date' => '2026-10-31']);
    $otherClient = Client::factory()->create();

    app(UpdateBudgetHeaderAction::class)->handle($sent, headerData($sent, [
        'client_id' => $otherClient->id,
        'issue_date' => '2026-10-05',
    ]), $this->actor);

    expect($sent->refresh()->client_id)->toBe($otherClient->id)
        ->and($sent->issue_date->toDateString())->toBe('2026-10-05');
});

it('RF-48: no modifica la cabecera de un presupuesto aceptado o rechazado', function (string $state) {
    $closed = Budget::factory()->{$state}()->create(['title' => 'Cerrado']);

    expect(fn () => app(UpdateBudgetHeaderAction::class)->handle($closed, headerData($closed, ['title' => 'Cambiado']), $this->actor))
        ->toThrow(BudgetNotEditableException::class);

    expect($closed->refresh()->title)->toBe('Cerrado')
        ->and(BudgetHistory::query()->count())->toBe(0);
})->with(['accepted', 'rejected']);

it('RF-18: la fecha de validez sigue siendo obligatoria al editar', function () {
    $errors = headerErrors(fn () => app(UpdateBudgetHeaderAction::class)->handle(
        $this->budget,
        headerData($this->budget, ['validity_date' => '']),
        $this->actor,
    ));

    expect($errors)->toHaveKey('validity_date')
        ->and($this->budget->refresh()->validity_date->toDateString())->toBe('2026-10-31');
});

it('RF-19: la fecha de validez debe seguir siendo posterior a la de emisión', function (string $issue, string $validity, bool $valid) {
    $errors = headerErrors(fn () => app(UpdateBudgetHeaderAction::class)->handle(
        $this->budget,
        headerData($this->budget, ['issue_date' => $issue, 'validity_date' => $validity]),
        $this->actor,
    ));

    expect(array_key_exists('validity_date', $errors))->toBe(! $valid);
})->with([
    'posterior' => ['2026-10-01', '2026-10-02', true],
    'igual' => ['2026-10-10', '2026-10-10', false],
    'anterior' => ['2026-10-10', '2026-10-09', false],
    'mover la emisión después de la validez' => ['2026-12-01', '2026-10-31', false],
]);

it('RF-14: valida título, modalidad y cliente como en el alta', function () {
    expect(headerErrors(fn () => app(UpdateBudgetHeaderAction::class)->handle($this->budget, headerData($this->budget, ['title' => '']), $this->actor)))->toHaveKey('title')
        ->and(headerErrors(fn () => app(UpdateBudgetHeaderAction::class)->handle($this->budget, headerData($this->budget, ['modality' => 'weekly']), $this->actor)))->toHaveKey('modality')
        ->and(headerErrors(fn () => app(UpdateBudgetHeaderAction::class)->handle($this->budget, headerData($this->budget, ['client_id' => 9999]), $this->actor)))->toHaveKey('client_id')
        ->and(BudgetHistory::query()->count())->toBe(0);
});

it('RF-20: sigue editable aunque su cliente haya pasado a inactivo o archivado', function (string $state) {
    $client = Client::factory()->create();
    $budget = Budget::factory()->for($client)->create(['title' => 'Original', 'validity_date' => '2026-12-31']);

    match ($state) {
        'inactivo' => $client->update(['status' => 'inactive']),
        'archivado' => $client->delete(),
    };

    app(UpdateBudgetHeaderAction::class)->handle($budget, headerData($budget, ['title' => 'Editado igual']), $this->actor);

    expect($budget->refresh()->title)->toBe('Editado igual')
        ->and($budget->client_id)->toBe($client->id);
})->with(['inactivo', 'archivado']);

it('RF-33: si la cabecera no cambia no se registra ningún asiento', function () {
    app(UpdateBudgetHeaderAction::class)->handle($this->budget, headerData($this->budget), $this->actor);

    expect(BudgetHistory::query()->count())->toBe(0);
});
