<?php

use App\Enums\BudgetHistoryField;
use App\Enums\BudgetModality;
use App\Enums\BudgetStatus;
use App\Models\Budget;
use App\Models\BudgetHistory;
use App\Models\BudgetItem;
use App\Models\Client;
use App\Models\Service;
use App\Models\ServicePriceHistory;
use App\Models\User;
use App\Models\WorkCategory;
use Database\Seeders\WorkCategorySeeder;

it('RF-2: cada servicio tiene exactamente una categoría de trabajo', function () {
    $service = Service::factory()->create();

    expect($service->category)->toBeInstanceOf(WorkCategory::class)
        ->and($service->work_category_id)->toBe($service->category->id);
});

it('RF-2: el seeder carga las categorías de trabajo y no las duplica', function () {
    $this->seed(WorkCategorySeeder::class);
    $this->seed(WorkCategorySeeder::class);

    expect(WorkCategory::query()->orderBy('name')->pluck('name')->all())->toBe(['Branding', 'Papelería', 'Redes']);
});

it('RF-13: el identificador del presupuesto es correlativo y no se reutiliza', function () {
    $first = Budget::factory()->create();
    $second = Budget::factory()->create();

    $second->delete();

    $third = Budget::factory()->create();

    expect($second->id)->toBe($first->id + 1)
        ->and($third->id)->toBe($second->id + 1);
});

it('RF-15, RF-42: la modalidad y el estado son enums y el presupuesto nace en borrador', function () {
    $budget = Budget::factory()->create()->refresh();

    expect($budget->modality)->toBe(BudgetModality::Single)
        ->and($budget->status)->toBe(BudgetStatus::Draft)
        ->and(Budget::factory()->monthly()->create()->modality)->toBe(BudgetModality::Monthly);
});

it('RF-42: solo borrador y enviado admiten cambios', function (BudgetStatus $status, bool $editable) {
    expect($status->isEditable())->toBe($editable);
})->with(fn () => [
    'borrador' => [BudgetStatus::Draft, true],
    'enviado' => [BudgetStatus::Sent, true],
    'aceptado' => [BudgetStatus::Accepted, false],
    'rechazado' => [BudgetStatus::Rejected, false],
]);

it('RF-24, RF-25: el importe del ítem es el precio copiado por la cantidad, sin errores de coma flotante', function (string $price, int $quantity, string $amount) {
    $item = BudgetItem::factory()->create(['unit_price' => $price, 'quantity' => $quantity]);

    expect($item->refresh()->amount)->toBe($amount);
})->with([
    'redondo' => ['12.50', 3, '37.50'],
    'trampa de flotantes' => ['0.10', 3, '0.30'],
    'con centavos' => ['19.99', 7, '139.93'],
    'una unidad' => ['5.00', 1, '5.00'],
]);

it('RNF-1: los importes se leen con exactamente 2 decimales', function () {
    $budget = Budget::factory()->create(['subtotal' => 10, 'discount_amount' => 1.5, 'total' => 8.5])->refresh();
    $service = Service::factory()->create(['list_price' => 7]);

    expect($budget->subtotal)->toBe('10.00')
        ->and($budget->discount_amount)->toBe('1.50')
        ->and($budget->total)->toBe('8.50')
        ->and($service->refresh()->list_price)->toBe('7.00');
});

it('RF-11, RF-14: el presupuesto resuelve su cliente (también archivado), su autor, sus ítems y su historial', function () {
    $client = Client::factory()->create();
    $author = User::factory()->staff()->create();
    $budget = Budget::factory()->for($client)->create(['created_by' => $author->id]);
    BudgetItem::factory()->for($budget)->count(2)->create();
    BudgetHistory::factory()->for($budget)->create();

    $client->delete();

    $budget->refresh();

    expect($budget->client->is($client))->toBeTrue()
        ->and($budget->creator->is($author))->toBeTrue()
        ->and($budget->items)->toHaveCount(2)
        ->and($budget->histories)->toHaveCount(1);
});

it('RF-34: el historial se lista en orden cronológico', function () {
    $budget = Budget::factory()->create();
    $second = BudgetHistory::factory()->for($budget)->create(['created_at' => now()->subDay()]);
    $first = BudgetHistory::factory()->for($budget)->create(['created_at' => now()->subDays(3)]);

    expect($budget->histories->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($budget->histories()->toSql())->toContain('order by');
});

it('RF-33: el asiento del historial guarda el campo como enum y los valores como arreglos', function () {
    $history = BudgetHistory::factory()->create([
        'field' => BudgetHistoryField::ItemAdded,
        'old_value' => null,
        'new_value' => ['name' => 'Logo', 'quantity' => 2],
    ])->refresh();

    expect($history->field)->toBe(BudgetHistoryField::ItemAdded)
        ->and($history->old_value)->toBeNull()
        ->and($history->new_value)->toBe(['name' => 'Logo', 'quantity' => 2])
        ->and($history->author)->toBeInstanceOf(User::class);
});

it('RF-5: el asiento del historial de precios resuelve servicio y autor', function () {
    $service = Service::factory()->create();
    $history = ServicePriceHistory::factory()->for($service)->create(['old_price' => '10.00', 'new_price' => '12.50'])->refresh();

    expect($history->service->is($service))->toBeTrue()
        ->and($history->old_price)->toBe('10.00')
        ->and($history->new_price)->toBe('12.50')
        ->and($history->author)->toBeInstanceOf(User::class)
        ->and($service->priceHistories)->toHaveCount(1);
});

it('RF-51, RF-53: descartar un presupuesto es un soft delete', function () {
    $budget = Budget::factory()->create();

    $budget->delete();

    expect(Budget::query()->count())->toBe(0)
        ->and(Budget::withTrashed()->count())->toBe(1);
});

it('RNF-8: los asientos de ambos historiales no se pueden modificar ni borrar', function () {
    $budgetHistory = BudgetHistory::factory()->create();
    $priceHistory = ServicePriceHistory::factory()->create();

    expect(fn () => $budgetHistory->update(['new_value' => ['x' => 1]]))->toThrow(LogicException::class)
        ->and(fn () => $budgetHistory->delete())->toThrow(LogicException::class)
        ->and(fn () => $priceHistory->update(['new_price' => '1.00']))->toThrow(LogicException::class)
        ->and(fn () => $priceHistory->delete())->toThrow(LogicException::class);
});
