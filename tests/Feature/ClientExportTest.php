<?php

use App\Actions\ExportClientListAction;
use App\Filament\Staff\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
});

/**
 * Runs the streamed response and returns the CSV rows, header first, without the BOM.
 *
 * @return array<int, array<int, string|null>>
 */
function csvRows(StreamedResponse $response): array
{
    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    return array_map('str_getcsv', array_filter(explode("\n", str_replace("\r\n", "\n", $content)), fn ($line) => $line !== ''));
}

it('RF-56: exporta un CSV en streaming con las columnas del listado', function () {
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(12, 30));
    Client::factory()->create([
        'first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678', 'created_at' => '2026-09-01 10:15:00',
    ]);

    $response = app(ExportClientListAction::class)->handle(Client::query());

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('clientes-20261007-1230.csv');

    $rows = csvRows($response);

    expect($rows[0])->toBe(['Nombre', 'Tipo de persona', 'Documento', 'Estado', 'Fecha de alta'])
        ->and($rows[1])->toBe(['Ana Pérez', 'Persona física', '12345678', 'Activo', '01/09/2026 10:15']);
});

it('RF-56: el archivo empieza con BOM UTF-8 para que las tildes se lean bien en una planilla', function () {
    Client::factory()->create();

    ob_start();
    app(ExportClientListAction::class)->handle(Client::query())->sendContent();
    $content = (string) ob_get_clean();

    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();
});

it('RF-56: exporta solo los clientes de la consulta recibida, con su búsqueda, filtros y orden', function () {
    $zeta = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Zárate']);
    $alfa = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Alvarez']);
    Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez']);
    Client::factory()->inactive()->create(['first_name' => 'Ana', 'last_name' => 'Inactiva']);

    $query = Client::query()
        ->search('ana')
        ->where('status', 'active')
        ->orderByRaw(Client::displayNameSql().' desc');

    $rows = csvRows(app(ExportClientListAction::class)->handle($query));

    expect(array_column(array_slice($rows, 1), 0))->toBe(['Ana Zárate', 'Ana Alvarez']);
});

it('RF-50, RF-56: no exporta los archivados a menos que la consulta los incluya', function () {
    Client::factory()->create(['first_name' => 'Visible', 'last_name' => 'Uno']);
    Client::factory()->archived()->create(['first_name' => 'Archivado', 'last_name' => 'Dos']);

    $without = csvRows(app(ExportClientListAction::class)->handle(Client::query()));
    $with = csvRows(app(ExportClientListAction::class)->handle(Client::withTrashed()));

    expect(array_column(array_slice($without, 1), 0))->toBe(['Visible Uno'])
        ->and(array_column(array_slice($with, 1), 0))->toHaveCount(2);
});

it('RF-56: protege el archivo contra inyección de fórmulas en las celdas', function (string $name) {
    Client::factory()->create(['first_name' => $name, 'last_name' => 'Prueba']);

    $rows = csvRows(app(ExportClientListAction::class)->handle(Client::query()));

    expect($rows[1][0])->toBe("'{$name} Prueba");
})->with(['=1+1', '+54911', '-2+3', '@SUM(A1)']);

it('RF-56: exportar no altera los clientes', function () {
    $client = Client::factory()->create();

    app(ExportClientListAction::class)->handle(Client::query())->sendContent();

    expect(Client::query()->count())->toBe(1)
        ->and($client->refresh()->histories)->toHaveCount(0);
});

it('RF-56: staff y admin exportan desde el listado y el archivo respeta el listado visible', function (string $role) {
    Filament::setCurrentPanel('staff');
    $this->actingAs(User::factory()->{$role}()->create());
    Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    Client::factory()->create(['first_name' => 'Luis', 'last_name' => 'Gómez']);
    Client::factory()->archived()->create(['first_name' => 'Rosa', 'last_name' => 'Sosa']);

    $list = Livewire::test(ListClients::class)
        ->searchTable('pérez')
        ->callAction('export')
        ->assertHasNoActionErrors();

    $download = $list->effects['download'];
    $content = base64_decode($download['content']);

    expect($download['name'])->toStartWith('clientes-')->toEndWith('.csv')
        ->and($content)->toContain('Ana Pérez')
        ->and($content)->not->toContain('Luis Gómez')
        ->and($content)->not->toContain('Rosa Sosa');
})->with(['staff', 'admin']);
