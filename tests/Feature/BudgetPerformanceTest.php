<?php

use App\Actions\GenerateBudgetPdfAction;
use App\Actions\RecalculateBudgetTotalsAction;
use App\Filament\Staff\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Staff\Resources\Budgets\Pages\ViewBudget;
use App\Models\Budget;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * RNF-5, RNF-6 and RNF-7. Data is loaded in bulk and the time is the best of three runs, so a
 * busy machine does not fail a threshold the system meets. Run apart with
 * `php artisan test --group=performance`, or skip with `--exclude-group=performance`.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    $this->actingAs(User::factory()->staff()->create());
});

it('RNF-5: recalcular subtotal, descuento y total de un presupuesto de 100 ítems tarda menos de 1 segundo', function () {
    $budget = budgetWithManyItems(100);
    $budget->update(['discount_type' => 'percentage', 'discount_value' => '12.5']);

    expect($budget->items()->count())->toBe(100);

    $elapsed = fastestOf(3, fn () => app(RecalculateBudgetTotalsAction::class)->handle($budget));

    $budget->refresh();

    expect($elapsed)->toBeLessThan(1.0)
        ->and($budget->subtotal)->toBe(Money::fromCents($budget->items->sum(fn ($item) => $item->amountInCents())))
        ->and((float) $budget->total)->toBeGreaterThan(0.0)
        ->and((float) $budget->total)->toBeLessThan((float) $budget->subtotal);
})->group('performance');

it('RNF-5: mostrar un presupuesto de 100 ítems con sus importes tarda menos de 1 segundo', function () {
    $budget = budgetWithManyItems(100);
    app(RecalculateBudgetTotalsAction::class)->handle($budget);

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])->assertSuccessful();

    $elapsed = fastestOf(3, fn () => Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->assertSee($budget->refresh()->totalLabel()));

    expect($elapsed)->toBeLessThan(1.0);
})->group('performance');

it('RNF-5: recalcular los importes no hace una consulta por ítem', function () {
    $budget = budgetWithManyItems(100);

    DB::enableQueryLog();
    DB::flushQueryLog();

    app(RecalculateBudgetTotalsAction::class)->handle($budget);

    $queries = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($queries)->toBeLessThan(5);
})->group('performance');

it('RNF-6: generar el PDF de un presupuesto de 100 ítems tarda menos de 5 segundos', function () {
    $budget = budgetWithManyItems(100);
    app(RecalculateBudgetTotalsAction::class)->handle($budget);

    $bytes = '';

    $elapsed = fastestOf(3, function () use ($budget, &$bytes): void {
        $bytes = app(GenerateBudgetPdfAction::class)->handle($budget->fresh());
    });

    expect($bytes)->toStartWith('%PDF-')
        ->and($elapsed)->toBeLessThan(5.0);
})->group('performance');

it('RNF-7: el listado de 2.000 presupuestos se muestra en menos de 2 segundos', function () {
    seedBudgetsInBulk(2000);

    expect(Budget::withTrashed()->count())->toBe(2000);

    Livewire::test(ListBudgets::class)->assertSuccessful();

    $elapsed = fastestOf(3, fn () => Livewire::test(ListBudgets::class)->assertSuccessful());

    expect($elapsed)->toBeLessThan(2.0);
})->group('performance');

it('RNF-7: con filtros y orden sobre 2.000 presupuestos sigue por debajo de 2 segundos', function () {
    seedBudgetsInBulk(2000);

    $client = Budget::query()->value('client_id');

    Livewire::test(ListBudgets::class)->assertSuccessful();

    $elapsed = fastestOf(3, fn () => Livewire::test(ListBudgets::class)
        ->filterTable('status', 'sent')
        ->filterTable('client_id', $client)
        ->sortTable('issue_date', 'desc')
        ->assertSuccessful());

    expect($elapsed)->toBeLessThan(2.0);
})->group('performance');

it('RNF-7: el listado usa los totales guardados y no suma los ítems fila por fila', function () {
    seedBudgetsInBulk(2000);

    Livewire::test(ListBudgets::class)->assertSuccessful();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    Livewire::test(ListBudgets::class)->assertSuccessful();

    $touchingItems = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'budget_items'));

    expect($touchingItems)->toBe([])
        ->and(count($queries))->toBeLessThan(30);
})->group('performance');
