<?php

use App\Actions\CreateLoosePieceAction;
use App\Enums\PieceHistoryField;
use App\Enums\PieceStatus;
use App\Exceptions\BudgetNotAcceptedException;
use App\Models\Budget;
use App\Models\Piece;
use App\Models\PieceHistory;
use App\Models\User;
use App\Models\WorkCategory;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    $this->author = User::factory()->staff()->create();
    $this->budget = Budget::factory()->accepted()->create();
    $this->category = WorkCategory::factory()->create();
});

function loosePieceData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Banner para el local',
        'work_category_id' => WorkCategory::query()->value('id'),
    ], $overrides);
}

function loosePieceErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-5, RF-9, RF-10, RF-18, RF-19: crea una pieza suelta pendiente, sin ítem de origen, con nombre, descripción y categoría', function () {
    $piece = app(CreateLoosePieceAction::class)
        ->handle($this->budget, loosePieceData(['description' => 'Medidas 2x1']), $this->author)
        ->refresh();

    expect($piece->budget_id)->toBe($this->budget->id)
        ->and($piece->budget_item_id)->toBeNull()
        ->and($piece->name)->toBe('Banner para el local')
        ->and($piece->description)->toBe('Medidas 2x1')
        ->and($piece->work_category_id)->toBe($this->category->id)
        ->and($piece->status)->toBe(PieceStatus::Pending)
        ->and($piece->created_by)->toBe($this->author->id)
        ->and($piece->assignee_id)->toBeNull();
});

it('RF-9: la descripción es opcional', function () {
    $piece = app(CreateLoosePieceAction::class)->handle($this->budget, loosePieceData(), $this->author);

    expect($piece->refresh()->description)->toBeNull();
});

it('RF-10: la pieza suelta exige una categoría de trabajo existente', function (?int $categoryId) {
    $errors = loosePieceErrors(fn () => app(CreateLoosePieceAction::class)
        ->handle($this->budget, loosePieceData(['work_category_id' => $categoryId]), $this->author));

    expect($errors)->toHaveKey('work_category_id')
        ->and($errors['work_category_id'][0])->toContain('categoría')
        ->and(Piece::query()->count())->toBe(0);
})->with([
    'sin categoría' => [null],
    'categoría inexistente' => [999999],
]);

it('RF-9: exige el nombre de la pieza', function () {
    $errors = loosePieceErrors(fn () => app(CreateLoosePieceAction::class)
        ->handle($this->budget, loosePieceData(['name' => '  ']), $this->author));

    expect($errors)->toHaveKey('name')
        ->and(Piece::query()->count())->toBe(0);
});

it('RF-16: la fecha de entrega comprometida es opcional al crear', function () {
    $without = app(CreateLoosePieceAction::class)->handle($this->budget, loosePieceData(), $this->author);
    $with = app(CreateLoosePieceAction::class)
        ->handle($this->budget, loosePieceData(['due_date' => '2026-11-15']), $this->author);

    expect($without->refresh()->due_date)->toBeNull()
        ->and($with->refresh()->due_date->toDateString())->toBe('2026-11-15');
});

it('RF-38: registra en el historial el alta de la pieza suelta con su autor', function () {
    $piece = app(CreateLoosePieceAction::class)->handle($this->budget, loosePieceData(), $this->author);

    $entry = PieceHistory::query()->where('piece_id', $piece->id)->sole();

    expect($entry->field)->toBe(PieceHistoryField::Created)
        ->and($entry->old_value)->toBeNull()
        ->and($entry->new_value)->toMatchArray(['name' => 'Banner para el local', 'status' => 'pending', 'budget_item_id' => null])
        ->and($entry->author_id)->toBe($this->author->id);
});

it('RF-6, RF-7: rechaza con motivo crear una pieza suelta en un presupuesto que no está aceptado', function (string $state) {
    $budget = Budget::factory()->{$state}()->create();

    try {
        app(CreateLoosePieceAction::class)->handle($budget, loosePieceData(), $this->author);
        $this->fail('Debió rechazar el presupuesto.');
    } catch (BudgetNotAcceptedException $exception) {
        expect($exception->getMessage())->toContain('aceptado');
    }

    expect(Piece::withTrashed()->count())->toBe(0)
        ->and(PieceHistory::query()->count())->toBe(0);
})->with(['draft', 'sent', 'rejected']);
