<?php

use App\Actions\ArchiveClientAction;
use App\Actions\CreateClientAction;
use App\Actions\DeactivateClientAction;
use App\Actions\UpdateClientAddressAction;
use App\Enums\ClientHistoryField;
use App\Models\Client;
use App\Models\ClientHistory;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
});

it('RF-37: el historial se lista en orden cronológico, aunque los asientos se hayan insertado desordenados', function () {
    $client = Client::factory()->create();

    $second = ClientHistory::factory()->for($client)->create(['created_at' => now()->subDays(2)]);
    $third = ClientHistory::factory()->for($client)->create(['created_at' => now()->subDay()]);
    $first = ClientHistory::factory()->for($client)->create(['created_at' => now()->subDays(5)]);

    expect($client->histories->pluck('id')->all())->toBe([$first->id, $second->id, $third->id])
        // The engine happens to read rows through the (client_id, created_at) index; the order
        // must not depend on that, so it has to be requested explicitly.
        ->and($client->histories()->toSql())->toContain('order by');
});

it('RF-37: los asientos con la misma fecha se ordenan por el orden en que se registraron', function () {
    $client = Client::factory()->create();
    $moment = now()->startOfSecond();

    $one = ClientHistory::factory()->for($client)->create(['created_at' => $moment]);
    $two = ClientHistory::factory()->for($client)->create(['created_at' => $moment]);

    expect($client->histories->pluck('id')->all())->toBe([$one->id, $two->id]);
});

it('RF-35, RF-36, RF-37: cada cambio deja autor, fecha, campo afectado y valores anterior y nuevo', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(9, 0));
    $client = app(CreateClientAction::class)->handle([
        'person_type' => 'individual',
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'document' => '12345678',
    ], $this->actor);

    $this->travelTo(now()->setTime(10, 0));
    app(UpdateClientAddressAction::class)->handle($client, ['street' => 'Av. Colón'], $this->actor);

    $this->travelTo(now()->setTime(11, 0));
    app(DeactivateClientAction::class)->handle($client, $this->actor);

    $this->travelTo(now()->setTime(12, 0));
    app(ArchiveClientAction::class)->handle($client->refresh(), $this->actor);

    $entries = $client->histories()->get();

    expect($entries->pluck('field')->all())->toBe([
        ClientHistoryField::Identification,
        ClientHistoryField::Address,
        ClientHistoryField::Status,
        ClientHistoryField::Archived,
    ])
        ->and($entries->pluck('author_id')->unique()->all())->toBe([$this->actor->id])
        ->and($entries->map(fn ($entry) => $entry->created_at->format('H:i'))->all())->toBe(['09:00', '10:00', '11:00', '12:00'])
        ->and($entries[0]->old_value)->toBeNull()
        ->and($entries[1]->old_value)->toMatchArray(['street' => null])
        ->and($entries[1]->new_value)->toMatchArray(['street' => 'Av. Colón'])
        ->and($entries[2]->old_value)->toBe(['status' => 'active'])
        ->and($entries[2]->new_value)->toBe(['status' => 'inactive']);
});

it('RF-34: un asiento ya escrito no se puede modificar', function () {
    $history = ClientHistory::factory()->create(['new_value' => ['status' => 'inactive']]);

    expect(fn () => $history->update(['new_value' => ['status' => 'active']]))
        ->toThrow(LogicException::class);

    expect($history->refresh()->new_value)->toBe(['status' => 'inactive']);
});

it('RF-34, RNF-7: un asiento no se puede borrar', function () {
    $history = ClientHistory::factory()->create();

    expect(fn () => $history->delete())->toThrow(LogicException::class);

    expect(ClientHistory::query()->whereKey($history->id)->exists())->toBeTrue();
});

it('RNF-7: ninguna tarea programada purga el historial de clientes', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn (Event $event) => (string) $event->command)
        ->implode(' ');

    expect($commands)->not->toContain('client');
});

it('RNF-7: el historial se conserva aunque pase el tiempo', function () {
    $history = ClientHistory::factory()->create(['created_at' => now()->subYears(10)]);

    $this->travel(5)->years();

    expect(ClientHistory::query()->whereKey($history->id)->exists())->toBeTrue();
});
