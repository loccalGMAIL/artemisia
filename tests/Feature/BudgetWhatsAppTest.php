<?php

use App\Actions\BuildWhatsAppLinkAction;
use App\Filament\Staff\Resources\Budgets\Pages\ViewBudget;
use App\Models\Budget;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
});

function budgetWithPhone(?string $phone, array $attributes = []): Budget
{
    $client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);

    if ($phone !== null) {
        ClientContact::factory()->for($client)->primary()->create(['phone' => $phone]);
    }

    return Budget::factory()->for($client)->create(array_merge(['title' => 'Identidad visual completa'], $attributes));
}

/**
 * @return array{url: string, number: string, query: array<string, string>}
 */
function parseWhatsAppLink(string $link): array
{
    $parts = parse_url($link);
    parse_str($parts['query'] ?? '', $query);

    return ['url' => $parts['scheme'].'://'.$parts['host'], 'number' => ltrim($parts['path'], '/'), 'query' => $query];
}

it('RF-68: arma el enlace wa.me al teléfono de contacto del cliente con un mensaje que referencia identificador y título', function () {
    $budget = budgetWithPhone('11-5555-0001');

    $link = parseWhatsAppLink(app(BuildWhatsAppLinkAction::class)->handle($budget));

    expect($link['url'])->toBe('https://wa.me')
        ->and($link['number'])->toBe('5491155550001')
        ->and($link['query']['text'])->toContain("#{$budget->id}")
        ->and($link['query']['text'])->toContain('Identidad visual completa');
});

it('RF-68: normaliza el teléfono a dígitos con código de país', function (string $phone, string $expected) {
    $link = parseWhatsAppLink(app(BuildWhatsAppLinkAction::class)->handle(budgetWithPhone($phone)));

    expect($link['number'])->toBe($expected);
})->with([
    'con guiones' => ['11-5555-0001', '5491155550001'],
    'con espacios y paréntesis' => ['(351) 444 9999', '5493514449999'],
    'con cero inicial' => ['0351 444 9999', '5493514449999'],
    'con código de país y signo más' => ['+54 9 351 444-9999', '5493514449999'],
    'con código de país sin signo' => ['5493514449999', '5493514449999'],
]);

it('RF-68: el mensaje se codifica para ir en la dirección', function () {
    $budget = budgetWithPhone('11-5555-0001', ['title' => 'Logo & marca: 50% off ¿sí?']);

    $link = app(BuildWhatsAppLinkAction::class)->handle($budget);

    expect($link)->not->toContain(' ')
        ->and(parseWhatsAppLink($link)['query']['text'])->toContain('Logo & marca: 50% off ¿sí?');
});

it('RF-69: sin teléfono de contacto no hay enlace', function (string $case) {
    $budget = match ($case) {
        'sin contactos' => budgetWithPhone(null),
        'contacto solo con email' => (function () {
            $budget = budgetWithPhone(null);
            ClientContact::factory()->for($budget->client)->primary()->create(['phone' => null, 'email' => 'ana@example.com']);

            return $budget;
        })(),
        'teléfono sin dígitos suficientes' => budgetWithPhone('abc'),
    };

    expect(app(BuildWhatsAppLinkAction::class)->handle($budget))->toBeNull();
})->with(['sin contactos', 'contacto solo con email', 'teléfono sin dígitos suficientes']);

it('RF-21 de clientes, RF-68: usa el teléfono de otro contacto si el principal no tiene', function () {
    $budget = budgetWithPhone(null);
    ClientContact::factory()->for($budget->client)->primary()->create(['phone' => null, 'email' => 'ana@example.com']);
    ClientContact::factory()->for($budget->client)->create(['phone' => '351-444-9999']);

    $link = parseWhatsAppLink(app(BuildWhatsAppLinkAction::class)->handle($budget));

    expect($link['number'])->toBe('5493514449999');
});

it('RF-70: el enlace no adjunta el PDF: lleva solo el número y el texto', function () {
    $link = parseWhatsAppLink(app(BuildWhatsAppLinkAction::class)->handle(budgetWithPhone('11-5555-0001')));

    expect(array_keys($link['query']))->toBe(['text'])
        ->and(strtolower($link['query']['text']))->not->toContain('.pdf');
});

it('RF-68: sigue funcionando si el cliente fue archivado después', function () {
    $budget = budgetWithPhone('11-5555-0001');
    $budget->client->delete();

    expect(app(BuildWhatsAppLinkAction::class)->handle($budget->refresh()))->not->toBeNull();
});

it('RF-68: la ficha ofrece la acción de WhatsApp en una pestaña nueva, en cualquier estado', function (string $state) {
    $this->actingAs(User::factory()->staff()->create());
    $budget = budgetWithPhone('11-5555-0001');
    $budget->update(['status' => $state]);
    $expected = app(BuildWhatsAppLinkAction::class)->handle($budget);

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->assertActionVisible('whatsapp')
        ->assertActionHasUrl('whatsapp', $expected)
        ->assertActionShouldOpenUrlInNewTab('whatsapp')
        ->assertDontSee('falta el teléfono');
})->with(['draft', 'sent', 'accepted', 'rejected']);

it('RF-69: sin teléfono la ficha no ofrece la acción e indica que falta el teléfono del cliente', function () {
    $this->actingAs(User::factory()->staff()->create());
    $budget = budgetWithPhone(null);

    Livewire::test(ViewBudget::class, ['record' => $budget->getKey()])
        ->assertActionHidden('whatsapp')
        ->assertSee('falta el teléfono del cliente');
});

it('RF-70: ningún archivo se genera ni se guarda al armar el enlace', function () {
    app(BuildWhatsAppLinkAction::class)->handle(budgetWithPhone('11-5555-0001'));

    expect(glob(storage_path('app/*.pdf')) ?: [])->toBe([]);
});

it('RF-60: un usuario client no accede a la ficha donde está la acción', function () {
    $budget = budgetWithPhone('11-5555-0001');

    $this->actingAs(User::factory()->client()->create());

    $this->get("/staff/budgets/{$budget->id}")->assertForbidden();
});
