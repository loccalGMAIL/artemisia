<?php

use App\Models\Budget;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Loads clients (and one primary contact each) with plain bulk inserts, so performance tests
 * measure the feature and not faker or Eloquent. The data is deterministic: every fourth
 * client is a company, every fifth is inactive and every fiftieth is archived.
 */
function seedClientsInBulk(int $count): void
{
    $author = User::factory()->admin()->create()->id;
    $now = now()->toDateTimeString();

    foreach (array_chunk(range(1, $count), 500) as $chunk) {
        Client::query()->insert(array_map(fn (int $n): array => [
            'person_type' => $n % 4 === 0 ? 'company' : 'individual',
            'first_name' => $n % 4 === 0 ? null : "Nombre{$n}",
            'last_name' => $n % 4 === 0 ? null : "Apellido{$n}",
            'company_name' => $n % 4 === 0 ? "Empresa {$n} S.A." : null,
            'document' => (string) (10_000_000 + $n),
            'status' => $n % 5 === 0 ? 'inactive' : 'active',
            'created_by' => $author,
            'created_at' => now()->subMinutes($n)->toDateTimeString(),
            'updated_at' => $now,
            'deleted_at' => $n % 50 === 0 ? $now : null,
        ], $chunk));
    }

    foreach (array_chunk(Client::withTrashed()->pluck('id')->all(), 500) as $chunk) {
        DB::table('client_contacts')->insert(array_map(fn (int $id): array => [
            'client_id' => $id,
            'name' => "Contacto {$id}",
            'phone' => '11-'.str_pad((string) $id, 8, '0', STR_PAD_LEFT),
            'email' => "contacto{$id}@example.com",
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $chunk));
    }
}

/** Seconds taken by the callback. */
function secondsTaken(Closure $callback): float
{
    $start = microtime(true);

    $callback();

    return microtime(true) - $start;
}

/**
 * The fastest of several runs. A threshold test should show what the system can do, not fail
 * because the machine was busy for a moment; the callback must be safe to repeat.
 */
function fastestOf(int $runs, Closure $callback): float
{
    return min(array_map(fn () => secondsTaken($callback), range(1, $runs)));
}

/**
 * Loads budgets with plain bulk inserts spread over a few clients, for performance tests. Every
 * fourth budget is in each of the four states and every fortieth is discarded.
 */
function seedBudgetsInBulk(int $count, int $clients = 50): void
{
    $author = User::factory()->admin()->create()->id;
    $clientIds = Client::factory()->count($clients)->create()->pluck('id')->all();
    $states = ['draft', 'sent', 'accepted', 'rejected'];
    $now = now()->toDateTimeString();

    foreach (array_chunk(range(1, $count), 500) as $chunk) {
        DB::table('budgets')->insert(array_map(fn (int $n): array => [
            'client_id' => $clientIds[$n % $clients],
            'title' => "Presupuesto de prueba {$n}",
            'modality' => $n % 3 === 0 ? 'monthly' : 'single',
            'issue_date' => '2026-01-01',
            'validity_date' => '2026-12-31',
            'status' => $states[$n % 4],
            'subtotal' => '1000.00',
            'discount_amount' => '100.00',
            'total' => '900.00',
            'created_by' => $author,
            'created_at' => now()->subMinutes($n)->toDateTimeString(),
            'updated_at' => $now,
            'deleted_at' => $n % 40 === 0 ? $now : null,
        ], $chunk));
    }
}

/** A budget with the given number of items, loaded in bulk, and its totals recalculated. */
function budgetWithManyItems(int $items): Budget
{
    $budget = Budget::factory()->create();
    $service = Service::factory()->create();
    $now = now()->toDateTimeString();

    DB::table('budget_items')->insert(array_map(fn (int $n): array => [
        'budget_id' => $budget->id,
        'service_id' => $service->id,
        'name' => "Servicio de prueba {$n}",
        'description' => "Descripción del servicio de prueba número {$n}, con algo de texto para ocupar espacio en el documento.",
        'unit_price' => number_format(100 + $n / 4, 2, '.', ''),
        'quantity' => ($n % 5) + 1,
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $items)));

    return $budget->refresh();
}
