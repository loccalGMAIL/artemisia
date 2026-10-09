<?php

use App\Actions\GenerateBudgetPdfAction;
use App\Actions\RecalculateBudgetTotalsAction;
use App\Actions\RemoveBudgetItemAction;
use App\Actions\SetBudgetDiscountAction;
use App\Exceptions\BudgetPdfGenerationException;
use App\Filament\Staff\Resources\Budgets\Pages\ViewBudget;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Province;
use App\Models\Service;
use App\Models\User;
use App\Support\Pdf\PdfRenderer;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
});

function budgetForPdf(array $attributes = []): Budget
{
    $province = Province::query()->firstOrCreate(['name' => 'Córdoba']);
    $client = Client::query()->where('document', '12345678')->first();

    if ($client === null) {
        $client = Client::factory()->create([
            'first_name' => 'Ana', 'last_name' => 'Pérez', 'document' => '12345678',
            'street' => 'Av. Colón', 'street_number' => '1234', 'city' => 'Córdoba', 'province_id' => $province->id,
        ]);
        ClientContact::factory()->for($client)->primary()->create(['phone' => '11-5555-0001']);
    }

    $budget = Budget::factory()->for($client)->create(array_merge([
        'title' => 'Identidad visual completa',
        'issue_date' => '2026-10-01',
        'validity_date' => '2026-10-31',
    ], $attributes));

    BudgetItem::factory()->for($budget)->create([
        'name' => 'Diseño de logotipo', 'description' => 'Isotipo y variantes', 'unit_price' => '1500.50', 'quantity' => 2,
    ]);
    BudgetItem::factory()->for($budget)->create([
        'name' => 'Papelería', 'description' => 'Tarjetas y hojas membretadas', 'unit_price' => '800.00', 'quantity' => 1,
    ]);

    app(RecalculateBudgetTotalsAction::class)->handle($budget);

    return $budget->refresh();
}

it('RF-61: staff y admin descargan el PDF de un presupuesto en cualquiera de sus estados', function (string $role, string $state) {
    $this->actingAs(User::factory()->{$role}()->create());
    $budget = budgetForPdf();
    $budget->update(['status' => $state]);

    $page = Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->callAction('downloadPdf')
        ->assertFileDownloaded("presupuesto-{$budget->id}.pdf");

    expect(base64_decode($page->effects['download']['content']))->toStartWith('%PDF-');
})->with(fn () => [
    'staff, borrador' => ['staff', 'draft'],
    'admin, borrador' => ['admin', 'draft'],
    'staff, enviado' => ['staff', 'sent'],
    'staff, aceptado' => ['staff', 'accepted'],
    'staff, rechazado' => ['staff', 'rejected'],
]);

it('RF-61: también se descarga el PDF de un presupuesto descartado que se consulta', function () {
    $this->actingAs(User::factory()->staff()->create());
    $budget = budgetForPdf();
    $budget->delete();

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->callAction('downloadPdf')
        ->assertFileDownloaded("presupuesto-{$budget->id}.pdf");
});

it('RF-62, RF-54: el PDF incluye el identificador, los datos del cliente, el título, las fechas y la modalidad', function () {
    $budget = budgetForPdf(['modality' => 'single']);

    $html = app(GenerateBudgetPdfAction::class)->renderHtml($budget);

    expect($html)->toContain("#{$budget->id}")
        ->toContain('Ana Pérez')
        ->toContain('12345678')
        ->toContain('Av. Colón')
        ->toContain('Córdoba')
        ->toContain('11-5555-0001')
        ->toContain('Identidad visual completa')
        ->toContain('01/10/2026')
        ->toContain('31/10/2026')
        ->toContain('Pago único');
});

it('RF-63: el PDF incluye por cada ítem su nombre, descripción, cantidad, precio copiado e importe', function () {
    $html = app(GenerateBudgetPdfAction::class)->renderHtml(budgetForPdf());

    expect($html)->toContain('Diseño de logotipo')
        ->toContain('Isotipo y variantes')
        ->toContain('$ 1.500,50')
        ->toContain('$ 3.001,00')
        ->toContain('Papelería')
        ->toContain('Tarjetas y hojas membretadas')
        ->toContain('$ 800,00');
});

it('RF-64: el PDF incluye el subtotal, el descuento y el total', function () {
    $budget = budgetForPdf();
    $budget->update(['status' => 'sent']);
    app(SetBudgetDiscountAction::class)->handle($budget, 'fixed', '301.00', User::factory()->staff()->create());

    $html = app(GenerateBudgetPdfAction::class)->renderHtml($budget->refresh());

    expect($html)->toContain('Subtotal')
        ->toContain('$ 3.801,00')
        ->toContain('Descuento')
        ->toContain('$ 301,00')
        ->toContain('Total')
        ->toContain('$ 3.500,00');
});

it('RF-65, RF-41: en un abono mensual el PDF indica que el total es un importe mensual', function () {
    $monthly = app(GenerateBudgetPdfAction::class)->renderHtml(budgetForPdf(['modality' => 'monthly']));
    $single = app(GenerateBudgetPdfAction::class)->renderHtml(budgetForPdf(['modality' => 'single']));

    expect($monthly)->toContain('Importe mensual')
        ->and($single)->not->toContain('Importe mensual');
});

it('RF-66: el PDF no incluye los ítems quitados ni los asientos del historial', function () {
    $actor = User::factory()->staff()->create();
    $budget = budgetForPdf();
    $removed = BudgetItem::factory()->for($budget)->create(['name' => 'Ítem que se quitó', 'unit_price' => '10.00', 'quantity' => 1]);

    app(RemoveBudgetItemAction::class)->handle($removed, $actor);

    $html = app(GenerateBudgetPdfAction::class)->renderHtml($budget->refresh());

    expect($html)->not->toContain('Ítem que se quitó')
        ->not->toContain('Ítem quitado')
        ->not->toContain('Historial')
        ->and($budget->histories()->count())->toBeGreaterThan(0);
});

it('RF-62: el PDF escapa el texto que cargó el usuario', function () {
    $html = app(GenerateBudgetPdfAction::class)->renderHtml(budgetForPdf(['title' => '<script>alert(1)</script> Oferta']));

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

it('RF-61: el PDF real se genera con la librería y empieza con la firma de un PDF', function () {
    $bytes = app(GenerateBudgetPdfAction::class)->handle(budgetForPdf());

    expect($bytes)->toStartWith('%PDF-')
        ->and(strlen($bytes))->toBeGreaterThan(1000);
});

it('RF-67: si falla la generación se muestra un mensaje de error y no se descarga ningún archivo', function () {
    $this->actingAs(User::factory()->staff()->create());
    $budget = budgetForPdf();

    $this->mock(PdfRenderer::class, fn ($mock) => $mock->shouldReceive('render')->andThrow(new RuntimeException('render roto')));

    expect(fn () => app(GenerateBudgetPdfAction::class)->handle($budget))
        ->toThrow(BudgetPdfGenerationException::class);

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->callAction('downloadPdf')
        ->assertNotified('No se pudo generar el PDF del presupuesto. Intente nuevamente.')
        ->assertNoFileDownloaded();
});

it('RF-71: ningún presupuesto ni su PDF está en una dirección accesible sin sesión', function () {
    $budget = budgetForPdf();

    $publicPdfRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => preg_match('/pdf/i', $route->uri().' '.($route->getName() ?? '')))
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    expect($publicPdfRoutes)->toBe([]);

    foreach (["/staff/budgets/{$budget->id}", '/staff/budgets', "/budgets/{$budget->id}/pdf", "/budgets/{$budget->id}", "/storage/presupuesto-{$budget->id}.pdf"] as $url) {
        $response = $this->get($url);

        expect($response->status())->not->toBe(200, "{$url} no debe responder 200 sin sesión");
    }
});

it('RF-60, RF-71: un usuario client no puede descargar el PDF', function () {
    $budget = budgetForPdf();

    $this->actingAs(User::factory()->client()->create());

    $this->get("/staff/budgets/{$budget->id}")->assertForbidden();
});

it('RF-66: el PDF de un presupuesto sin servicios consultables no depende del catálogo', function () {
    $budget = budgetForPdf();
    Service::query()->update(['is_active' => false]);

    expect(app(GenerateBudgetPdfAction::class)->renderHtml($budget))->toContain('Diseño de logotipo');
});
