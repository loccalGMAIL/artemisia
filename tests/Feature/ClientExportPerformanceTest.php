<?php

use App\Actions\ExportClientListAction;
use App\Models\Client;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Builder;

/*
 * RNF-6. The streamed output is thrown away chunk by chunk, so the measurement shows the
 * memory the export itself needs. Run apart with `php artisan test --group=performance`.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');

    seedClientsInBulk(5000);
});

/**
 * Streams the export into a sink that keeps only counters.
 *
 * @return array{seconds: float, bytes: int, peakBefore: int, peakAfter: int}
 */
function streamExport(Builder $query): array
{
    $bytes = 0;
    $response = app(ExportClientListAction::class)->handle($query);

    gc_collect_cycles();
    memory_reset_peak_usage();
    $peakBefore = memory_get_peak_usage();

    ob_start(function (string $chunk) use (&$bytes): string {
        $bytes += strlen($chunk);

        return '';
    }, 8192);

    $seconds = secondsTaken(fn () => $response->sendContent());

    ob_end_clean();

    return ['seconds' => $seconds, 'bytes' => $bytes, 'peakBefore' => $peakBefore, 'peakAfter' => memory_get_peak_usage()];
}

it('RNF-6: exportar el listado filtrado de 5.000 clientes tarda menos de 5 segundos', function () {
    $query = Client::query()->search('nombre')->where('status', 'active')->orderByRaw(Client::displayNameSql().' asc');

    $result = streamExport($query);

    expect($result['bytes'])->toBeGreaterThan(50_000)
        ->and($result['seconds'])->toBeLessThan(5.0);
})->group('performance');

it('RNF-6: exportar todo, incluidos los archivados, también cumple el umbral', function () {
    $result = streamExport(Client::withTrashed());

    expect($result['bytes'])->toBeGreaterThan(100_000)
        ->and($result['seconds'])->toBeLessThan(5.0);
})->group('performance');

it('RNF-6: la exportación no carga todos los clientes en memoria', function () {
    $result = streamExport(Client::withTrashed());

    $extraMegabytes = ($result['peakAfter'] - $result['peakBefore']) / 1024 / 1024;

    // Holding 5000 hydrated models costs several times this; a row-by-row stream stays far below.
    expect($extraMegabytes)->toBeLessThan(4.0);
})->group('performance');

it('RNF-6: el archivo tiene una fila por cliente más el encabezado', function () {
    $query = Client::query();
    $expected = $query->count();

    ob_start();
    app(ExportClientListAction::class)->handle(Client::query())->sendContent();
    $lines = array_filter(explode("\n", (string) ob_get_clean()), fn ($line) => trim($line) !== '');

    expect(count($lines))->toBe($expected + 1);
})->group('performance');
