<?php

use App\Enums\PieceStatus;
use App\Filament\Staff\Resources\Budgets\Pages\ViewBudget;
use App\Filament\Staff\Resources\Pieces\Pages\ListPieces;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Piece;
use App\Models\PieceHistory;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkCategory;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->actor = User::factory()->staff()->create();
    $this->actingAs($this->actor);
    $this->budget = Budget::factory()->accepted()->create();
    $this->category = WorkCategory::factory()->create();
});

function budgetPage(Budget $budget)
{
    return Livewire::test(ViewBudget::class, ['record' => $budget->getKey()]);
}

it('RF-1, RF-4, RF-10: el staff abre la propuesta de un presupuesto aceptado, con una línea por ítem, y confirma las piezas', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());
    $logo = BudgetItem::factory()->for($this->budget)->for(Service::factory()->for($this->category, 'category'))->create(['name' => 'Logo', 'quantity' => 1]);
    $flyer = BudgetItem::factory()->for($this->budget)->create(['name' => 'Flyer', 'quantity' => 2]);

    budgetPage($this->budget)
        ->mountAction('generatePieces')
        ->assertActionDataSet(function (array $state) use ($logo, $flyer): bool {
            $lines = array_values($state['lines']);

            return count($lines) === 2
                && $lines[0]['name'] === 'Logo' && $lines[0]['budget_item_id'] === $logo->id
                && $lines[0]['work_category_id'] === $this->category->id
                && $lines[1]['name'] === 'Flyer' && $lines[1]['budget_item_id'] === $flyer->id;
        });

    $lines = [
        ['budget_item_id' => $logo->id, 'name' => 'Logo', 'quantity' => 1, 'work_category_id' => $this->category->id, 'description' => null],
        ['budget_item_id' => $flyer->id, 'name' => 'Flyer', 'quantity' => 2, 'work_category_id' => $flyer->service->work_category_id, 'description' => null],
    ];

    budgetPage($this->budget)
        ->callAction('generatePieces', ['lines' => $lines])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(Piece::query()->orderBy('id')->pluck('budget_item_id')->all())->toBe([$logo->id, $flyer->id])
        ->and(Piece::query()->where('status', PieceStatus::Pending)->count())->toBe(2)
        ->and(PieceHistory::query()->count())->toBe(2);
})->with(['admin', 'staff']);

it('RF-2, RF-3: divide la cantidad de un ítem en varias piezas y quita las líneas que no hacen falta', function () {
    $flyer = BudgetItem::factory()->for($this->budget)->create(['name' => 'Flyer', 'quantity' => 3]);
    BudgetItem::factory()->for($this->budget)->create(['name' => 'Tarjeta']);
    $categoryId = $flyer->service->work_category_id;

    budgetPage($this->budget)
        ->callAction('generatePieces', ['lines' => [
            ['budget_item_id' => $flyer->id, 'name' => 'Flyer A', 'quantity' => 2, 'work_category_id' => $categoryId, 'description' => null],
            ['budget_item_id' => $flyer->id, 'name' => 'Flyer B', 'quantity' => 1, 'work_category_id' => $categoryId, 'description' => 'Versión chica'],
        ]])
        ->assertHasNoActionErrors();

    expect(Piece::query()->orderBy('id')->pluck('name')->all())->toBe(['Flyer A', 'Flyer B'])
        ->and(Piece::query()->pluck('budget_item_id')->unique()->all())->toBe([$flyer->id]);
});

it('RF-4: la propuesta exige el nombre y la categoría de cada pieza', function () {
    $item = BudgetItem::factory()->for($this->budget)->create();

    budgetPage($this->budget)
        ->callAction('generatePieces', ['lines' => [
            ['budget_item_id' => $item->id, 'name' => '', 'quantity' => 1, 'work_category_id' => null, 'description' => null],
        ]])
        ->assertNotified('Cada pieza necesita un nombre y una categoría de trabajo válidos.');

    expect(Piece::query()->count())->toBe(0);
});

it('RF-6, RF-7: sobre un presupuesto que no está aceptado se muestra el motivo y no se abre la propuesta', function (string $state) {
    $budget = Budget::factory()->{$state}()->create();
    BudgetItem::factory()->for($budget)->create();

    budgetPage($budget)
        ->mountAction('generatePieces')
        ->assertNotified('Solo se pueden generar piezas a partir de un presupuesto aceptado.')
        ->assertActionNotMounted();

    expect(Piece::query()->count())->toBe(0);
})->with(['draft', 'sent', 'rejected']);

it('RF-5, RF-9, RF-10: crea una pieza suelta asociada a un presupuesto aceptado', function () {
    Livewire::test(ListPieces::class)
        ->callAction('createLoosePiece', [
            'budget_id' => $this->budget->id,
            'name' => 'Banner para el local',
            'description' => 'Medidas 2x1',
            'work_category_id' => $this->category->id,
            'due_date' => '2026-11-15',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $piece = Piece::query()->sole();

    expect($piece->budget_id)->toBe($this->budget->id)
        ->and($piece->budget_item_id)->toBeNull()
        ->and($piece->work_category_id)->toBe($this->category->id)
        ->and($piece->status)->toBe(PieceStatus::Pending)
        ->and($piece->due_date->toDateString())->toBe('2026-11-15')
        ->and($piece->created_by)->toBe($this->actor->id);
});

it('RF-10: la pieza suelta exige nombre y categoría de trabajo', function () {
    Livewire::test(ListPieces::class)
        ->callAction('createLoosePiece', [
            'budget_id' => $this->budget->id,
            'name' => '',
            'work_category_id' => null,
        ])
        ->assertHasActionErrors(['name', 'work_category_id']);

    expect(Piece::query()->count())->toBe(0);
});

it('RF-6: al crear una pieza suelta solo se ofrecen presupuestos aceptados', function () {
    Budget::factory()->draft()->create();
    Budget::factory()->sent()->create();
    Budget::factory()->rejected()->create();
    Budget::factory()->accepted()->discarded()->create();

    Livewire::test(ListPieces::class)
        ->mountAction('createLoosePiece')
        ->assertFormFieldExists('budget_id', checkFieldUsing: fn (Select $field): bool => array_keys($field->getSearchResults('')) === [$this->budget->id]);
});

it('RF-6, RF-7: un presupuesto que dejó de estar aceptado no admite la pieza suelta y se muestra el motivo', function () {
    $draft = Budget::factory()->draft()->create();

    Livewire::test(ListPieces::class)
        ->callAction('createLoosePiece', [
            'budget_id' => $draft->id,
            'name' => 'Banner',
            'work_category_id' => $this->category->id,
        ])
        ->assertNotified('Solo se pueden generar piezas a partir de un presupuesto aceptado.');

    expect(Piece::query()->count())->toBe(0);
});
