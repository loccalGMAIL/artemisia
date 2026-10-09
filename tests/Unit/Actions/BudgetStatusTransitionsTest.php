<?php

use App\Actions\AcceptBudgetAction;
use App\Actions\AddBudgetItemAction;
use App\Actions\RejectBudgetAction;
use App\Actions\RevertBudgetToSentAction;
use App\Actions\SendBudgetAction;
use App\Actions\SetBudgetDiscountAction;
use App\Actions\UpdateBudgetHeaderAction;
use App\Enums\BudgetHistoryField;
use App\Enums\BudgetStatus;
use App\Exceptions\InvalidBudgetTransitionException;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\BudgetItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(10, 0));
    $this->actor = User::factory()->staff()->create();
});

function budgetWithItem(string $state = 'draft', array $attributes = []): Budget
{
    $budget = Budget::factory()->{$state}()->create($attributes);

    BudgetItem::factory()->for($budget)->create(['unit_price' => '100.00', 'quantity' => 1]);

    return $budget;
}

it('RF-43, RF-50: marca un borrador como enviado y registra estado anterior, nuevo, autor y fecha', function () {
    $budget = budgetWithItem('draft');

    app(SendBudgetAction::class)->handle($budget, $this->actor);

    expect($budget->refresh()->status)->toBe(BudgetStatus::Sent);

    $history = BudgetHistory::query()->where('budget_id', $budget->id)->sole();

    expect($history->field)->toBe(BudgetHistoryField::StatusChanged)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->created_at)->not->toBeNull()
        ->and($history->old_value)->toMatchArray(['status' => 'draft'])
        ->and($history->new_value)->toMatchArray(['status' => 'sent']);
});

it('RF-44: no envía un presupuesto sin ítems y muestra el motivo', function () {
    $budget = Budget::factory()->create();

    try {
        app(SendBudgetAction::class)->handle($budget, $this->actor);
        $this->fail('Debió rechazar el envío sin ítems.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('items')
            ->and($exception->errors()['items'][0])->toContain('ítems');
    }

    expect($budget->refresh()->status)->toBe(BudgetStatus::Draft)
        ->and(BudgetHistory::query()->count())->toBe(0);
});

it('RF-45, RF-46, RF-50: marca un enviado como aceptado o rechazado y registra la fecha de respuesta', function (string $action, string $expected) {
    $budget = budgetWithItem('sent');

    app($action)->handle($budget, ...($action === RejectBudgetAction::class ? [null] : []), ...[$this->actor]);

    expect($budget->refresh()->status->value)->toBe($expected)
        ->and($budget->response_date->toDateString())->toBe('2026-10-07');

    $history = BudgetHistory::query()->where('budget_id', $budget->id)->sole();

    expect($history->field)->toBe(BudgetHistoryField::StatusChanged)
        ->and($history->old_value)->toMatchArray(['status' => 'sent'])
        ->and($history->new_value)->toMatchArray(['status' => $expected, 'response_date' => '2026-10-07']);
})->with([
    'aceptado' => [AcceptBudgetAction::class, 'accepted'],
    'rechazado' => [RejectBudgetAction::class, 'rejected'],
]);

it('RF-47: al rechazar se puede registrar un motivo y se acepta vacío', function (?string $reason, ?string $stored) {
    $budget = budgetWithItem('sent');

    app(RejectBudgetAction::class)->handle($budget, $reason, $this->actor);

    expect($budget->refresh()->rejection_reason)->toBe($stored);
})->with([
    'con motivo' => ['Precio fuera de presupuesto', 'Precio fuera de presupuesto'],
    'motivo con espacios' => ['  Lo pospone  ', 'Lo pospone'],
    'vacío' => ['', null],
    'solo espacios' => ['   ', null],
    'nulo' => [null, null],
]);

it('RF-47: el motivo de rechazo no pasa de 255 caracteres', function () {
    $budget = budgetWithItem('sent');

    expect(fn () => app(RejectBudgetAction::class)->handle($budget, str_repeat('a', 256), $this->actor))
        ->toThrow(ValidationException::class);

    expect($budget->refresh()->status)->toBe(BudgetStatus::Sent);

    app(RejectBudgetAction::class)->handle($budget, str_repeat('a', 255), $this->actor);

    expect($budget->refresh()->status)->toBe(BudgetStatus::Rejected);
});

it('RF-42: solo se admiten las transiciones válidas; cualquier otra se rechaza sin cambiar nada', function (string $from, string $action) {
    $budget = Budget::factory()->{$from}()->create();
    BudgetItem::factory()->for($budget)->create();

    $arguments = $action === RejectBudgetAction::class ? [null] : [];

    expect(fn () => app($action)->handle($budget, ...$arguments, ...[$this->actor]))
        ->toThrow(InvalidBudgetTransitionException::class);

    expect($budget->refresh()->status->value)->toBe($from)
        ->and(BudgetHistory::query()->count())->toBe(0);
})->with([
    'aceptar un borrador' => ['draft', AcceptBudgetAction::class],
    'rechazar un borrador' => ['draft', RejectBudgetAction::class],
    'revertir un borrador' => ['draft', RevertBudgetToSentAction::class],
    'enviar un enviado' => ['sent', SendBudgetAction::class],
    'revertir un enviado' => ['sent', RevertBudgetToSentAction::class],
    'enviar un aceptado' => ['accepted', SendBudgetAction::class],
    'aceptar un aceptado' => ['accepted', AcceptBudgetAction::class],
    'rechazar un aceptado' => ['accepted', RejectBudgetAction::class],
    'enviar un rechazado' => ['rejected', SendBudgetAction::class],
    'aceptar un rechazado' => ['rejected', AcceptBudgetAction::class],
    'rechazar un rechazado' => ['rejected', RejectBudgetAction::class],
]);

it('RF-49, RF-50: devuelve un aceptado o rechazado a enviado, limpia la respuesta y lo registra', function (string $state) {
    $budget = Budget::factory()->{$state}()->create([
        'response_date' => '2026-10-05',
        'rejection_reason' => $state === 'rejected' ? 'Muy caro' : null,
    ]);
    BudgetItem::factory()->for($budget)->create();

    app(RevertBudgetToSentAction::class)->handle($budget, $this->actor);

    $budget->refresh();

    expect($budget->status)->toBe(BudgetStatus::Sent)
        ->and($budget->response_date)->toBeNull()
        ->and($budget->rejection_reason)->toBeNull();

    $history = BudgetHistory::query()->where('budget_id', $budget->id)->sole();

    expect($history->field)->toBe(BudgetHistoryField::StatusChanged)
        ->and($history->author_id)->toBe($this->actor->id)
        ->and($history->old_value)->toMatchArray(['status' => $state, 'response_date' => '2026-10-05'])
        ->and($history->new_value)->toMatchArray(['status' => 'sent', 'response_date' => null]);
})->with(['accepted', 'rejected']);

it('RF-48, RF-49: aceptado o rechazado no admite cambios hasta volver a enviado, y entonces sí', function (string $state) {
    $service = Service::factory()->create(['list_price' => '10.00']);
    $budget = Budget::factory()->{$state}()->create(['issue_date' => '2026-10-01', 'validity_date' => '2026-12-31']);
    BudgetItem::factory()->for($budget)->create(['unit_price' => '100.00', 'quantity' => 1]);

    $edits = [
        fn () => app(UpdateBudgetHeaderAction::class)->handle($budget, ['client_id' => $budget->client_id, 'title' => 'Nuevo', 'modality' => 'single', 'validity_date' => '2026-12-31'], $this->actor),
        fn () => app(AddBudgetItemAction::class)->handle($budget, $service, 1, null, $this->actor),
        fn () => app(SetBudgetDiscountAction::class)->handle($budget, 'fixed', '5', $this->actor),
    ];

    foreach ($edits as $edit) {
        expect($edit)->toThrow(Exception::class);
    }

    app(RevertBudgetToSentAction::class)->handle($budget, $this->actor);

    foreach ($edits as $edit) {
        $edit();
    }

    expect($budget->refresh()->title)->toBe('Nuevo')
        ->and($budget->items()->count())->toBe(2)
        ->and($budget->discount_amount)->toBe('5.00');
})->with(['accepted', 'rejected']);

it('RF-55: un enviado con fecha de validez anterior a hoy se señala como vencido sin cambiar de estado', function (string $state, string $validity, bool $expired) {
    $budget = Budget::factory()->{$state}()->create(['issue_date' => '2026-01-01', 'validity_date' => $validity]);

    expect($budget->isExpired())->toBe($expired)
        ->and($budget->status->value)->toBe($state);

    expect(Budget::expired()->whereKey($budget->id)->exists())->toBe($expired);
})->with([
    'enviado, ayer' => ['sent', '2026-10-06', true],
    'enviado, hace meses' => ['sent', '2026-03-01', true],
    'enviado, hoy' => ['sent', '2026-10-07', false],
    'enviado, mañana' => ['sent', '2026-10-08', false],
    'borrador vencido' => ['draft', '2026-03-01', false],
    'aceptado vencido' => ['accepted', '2026-03-01', false],
    'rechazado vencido' => ['rejected', '2026-03-01', false],
]);

it('RF-56: un presupuesto enviado y vencido se puede igual aceptar o rechazar', function (string $action) {
    $budget = budgetWithItem('sent', ['issue_date' => '2026-01-01', 'validity_date' => '2026-03-01']);

    expect($budget->isExpired())->toBeTrue();

    app($action)->handle($budget, ...($action === RejectBudgetAction::class ? ['Se venció'] : []), ...[$this->actor]);

    expect($budget->refresh()->status->isEditable())->toBeFalse()
        ->and($budget->response_date->toDateString())->toBe('2026-10-07');
})->with([AcceptBudgetAction::class, RejectBudgetAction::class]);
