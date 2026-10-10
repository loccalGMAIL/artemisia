<?php

use App\Actions\AssignPieceOwnerAction;
use App\Actions\SetPieceDueDateAction;
use App\Enums\PieceHistoryField;
use App\Enums\PieceStatus;
use App\Exceptions\InvalidPieceAssigneeException;
use App\Exceptions\PieceDeliveredException;
use App\Models\Piece;
use App\Models\PieceHistory;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app()->setLocale('es');
    Carbon::setTestNow('2026-10-15 10:00:00');
    $this->actor = User::factory()->staff()->create();
    $this->piece = Piece::factory()->create();
});

afterEach(fn () => Carbon::setTestNow());

it('RF-11, RF-12, RF-38: delega la pieza a una cuenta staff o admin y lo registra con su autor', function (string $role) {
    $assignee = User::factory()->{$role}()->create();

    app(AssignPieceOwnerAction::class)->handle($this->piece, $assignee, $this->actor);

    $entry = PieceHistory::query()->where('piece_id', $this->piece->id)->sole();

    expect($this->piece->refresh()->assignee_id)->toBe($assignee->id)
        ->and($entry->field)->toBe(PieceHistoryField::AssigneeChanged)
        ->and($entry->old_value)->toBe(['assignee_id' => null])
        ->and($entry->new_value)->toBe(['assignee_id' => $assignee->id])
        ->and($entry->author_id)->toBe($this->actor->id);
})->with(['staff', 'admin']);

it('RF-12, RF-14, RF-38: reasignar reemplaza al responsable y registra el anterior', function () {
    $first = User::factory()->staff()->create();
    $second = User::factory()->admin()->create();
    $action = app(AssignPieceOwnerAction::class);

    $action->handle($this->piece, $first, $this->actor);
    $action->handle($this->piece, $second, $this->actor);

    $last = PieceHistory::query()->where('piece_id', $this->piece->id)->latest('id')->first();

    expect($this->piece->refresh()->assignee_id)->toBe($second->id)
        ->and(PieceHistory::query()->count())->toBe(2)
        ->and($last->old_value)->toBe(['assignee_id' => $first->id])
        ->and($last->new_value)->toBe(['assignee_id' => $second->id]);
});

it('RF-11: solo se delega a una cuenta con rol admin o staff', function () {
    $client = User::factory()->client()->create();

    try {
        app(AssignPieceOwnerAction::class)->handle($this->piece, $client, $this->actor);
        $this->fail('Debió rechazar al responsable.');
    } catch (InvalidPieceAssigneeException $exception) {
        expect($exception->getMessage())->toContain('staff');
    }

    expect($this->piece->refresh()->assignee_id)->toBeNull()
        ->and(PieceHistory::query()->count())->toBe(0);
});

it('RF-11, RF-14, RF-25: no se delega ni se reasigna una pieza entregada', function () {
    $owner = User::factory()->staff()->create();
    $piece = Piece::factory()->status(PieceStatus::Delivered)->assignedTo($owner)->create();

    try {
        app(AssignPieceOwnerAction::class)->handle($piece, User::factory()->staff()->create(), $this->actor);
        $this->fail('Debió rechazar la reasignación.');
    } catch (PieceDeliveredException $exception) {
        expect($exception->getMessage())->toContain('entregada');
    }

    expect($piece->refresh()->assignee_id)->toBe($owner->id)
        ->and(PieceHistory::query()->count())->toBe(0);
});

it('RF-13: la pieza delegada aparece entre las piezas de esa cuenta y no en las de otra', function () {
    $mine = User::factory()->staff()->create();
    $other = User::factory()->staff()->create();
    $delegated = Piece::factory()->assignedTo($mine)->create();
    Piece::factory()->assignedTo($other)->create();
    Piece::factory()->create();

    expect(Piece::query()->delegatedTo($mine)->pluck('id')->all())->toBe([$delegated->id]);
});

it('RF-15, RF-16, RF-38: carga, modifica y quita la fecha de entrega comprometida, registrando cada cambio', function () {
    $action = app(SetPieceDueDateAction::class);

    $action->handle($this->piece, Carbon::parse('2026-11-01'), $this->actor);
    $action->handle($this->piece, Carbon::parse('2026-11-20'), $this->actor);
    $action->handle($this->piece, null, $this->actor);

    $entries = PieceHistory::query()->where('piece_id', $this->piece->id)->orderBy('id')->get();

    expect($this->piece->refresh()->due_date)->toBeNull()
        ->and($entries)->toHaveCount(3)
        ->and($entries->pluck('field')->unique()->all())->toBe([PieceHistoryField::DueDateChanged])
        ->and($entries[0]->old_value)->toBe(['due_date' => null])
        ->and($entries[0]->new_value)->toBe(['due_date' => '2026-11-01'])
        ->and($entries[1]->old_value)->toBe(['due_date' => '2026-11-01'])
        ->and($entries[1]->new_value)->toBe(['due_date' => '2026-11-20'])
        ->and($entries[2]->new_value)->toBe(['due_date' => null])
        ->and($entries->pluck('author_id')->unique()->all())->toBe([$this->actor->id]);
});

it('RF-15: la fecha de entrega queda guardada como fecha, sin hora', function () {
    app(SetPieceDueDateAction::class)->handle($this->piece, Carbon::parse('2026-11-01 17:45:00'), $this->actor);

    expect($this->piece->refresh()->due_date->toDateString())->toBe('2026-11-01');
});

it('RF-17: una pieza sin entregar con la fecha vencida está atrasada', function (PieceStatus $status, ?string $dueDate, bool $overdue) {
    $piece = Piece::factory()->status($status)->create(['due_date' => $dueDate]);

    expect($piece->isOverdue())->toBe($overdue)
        ->and(Piece::query()->overdue()->whereKey($piece->id)->exists())->toBe($overdue);
})->with([
    'pendiente y vencida' => [PieceStatus::Pending, '2026-10-14', true],
    'aprobada y vencida' => [PieceStatus::Approved, '2026-10-01', true],
    'vence hoy' => [PieceStatus::Pending, '2026-10-15', false],
    'vence mañana' => [PieceStatus::InProduction, '2026-10-16', false],
    'sin fecha' => [PieceStatus::Pending, null, false],
    'entregada y vencida' => [PieceStatus::Delivered, '2026-10-01', false],
]);
