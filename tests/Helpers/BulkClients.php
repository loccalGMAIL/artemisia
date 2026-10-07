<?php

use App\Models\Client;
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
