<?php

use App\Models\AccessLog;
use App\Models\AccountHistory;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(12, 0));
});

it('RNF-5: borra los registros de acceso de más de 24 meses y conserva los más nuevos', function () {
    $old = AccessLog::factory()->create(['created_at' => now()->subMonths(24)->subDay()]);
    $veryOld = AccessLog::factory()->create(['created_at' => now()->subYears(5)]);
    $edge = AccessLog::factory()->create(['created_at' => now()->subMonths(24)->addDay()]);
    $recent = AccessLog::factory()->create(['created_at' => now()->subMonths(3)]);
    $today = AccessLog::factory()->create(['created_at' => now()]);

    $this->artisan('access-logs:prune')->assertSuccessful();

    expect(AccessLog::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$edge, $recent, $today])->pluck('id')->sort()->values()->all())
        ->and(AccessLog::query()->whereKey([$old->id, $veryOld->id])->exists())->toBeFalse();
});

it('RNF-6: la purga no toca ninguna fila del historial de cuentas, por antigua que sea', function () {
    AccessLog::factory()->create(['created_at' => now()->subYears(3)]);
    $histories = AccountHistory::factory()->count(3)->sequence(
        ['created_at' => now()->subYears(5)],
        ['created_at' => now()->subYears(2)],
        ['created_at' => now()->subDay()],
    )->create();

    $this->artisan('access-logs:prune')->assertSuccessful();

    expect(AccessLog::query()->count())->toBe(0)
        ->and(AccountHistory::query()->pluck('id')->sort()->values()->all())
        ->toBe($histories->pluck('id')->sort()->values()->all());
});

it('RNF-5: informa cuántos registros eliminó', function () {
    AccessLog::factory()->count(2)->create(['created_at' => now()->subMonths(30)]);
    AccessLog::factory()->create(['created_at' => now()->subMonth()]);

    $this->artisan('access-logs:prune')->expectsOutputToContain('2')->assertSuccessful();
});

it('RNF-5: el comando queda programado mensualmente', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event) => str_contains($event->command ?? '', 'access-logs:prune'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 1 * *');
});
