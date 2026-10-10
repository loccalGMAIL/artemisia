<?php

use App\Actions\MarkPieceDeliveredAction;
use App\Actions\MarkPieceInProductionAction;
use App\Actions\MarkPieceInReviewAction;
use App\Enums\PieceHistoryField;
use App\Enums\PieceStatus;
use App\Exceptions\InvalidPieceTransitionException;
use App\Models\Piece;
use App\Models\PieceHistory;
use App\Models\User;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
});

it('RF-18: el estado de la pieza es uno de los seis del ciclo de producción', function () {
    expect(array_map(fn (PieceStatus $status) => $status->value, PieceStatus::cases()))->toBe([
        'pending', 'in_production', 'in_review', 'client_approval', 'approved', 'delivered',
    ]);
});

it('RF-20, RF-21, RF-22, RF-24, RF-34: el ciclo solo avanza por el camino previsto, con la única vuelta del rechazo', function (PieceStatus $from, array $allowed) {
    expect($from->allowedNext())->toBe($allowed);
})->with([
    'pendiente' => [PieceStatus::Pending, [PieceStatus::InProduction]],
    'en producción' => [PieceStatus::InProduction, [PieceStatus::InReview]],
    'en revisión' => [PieceStatus::InReview, [PieceStatus::ClientApproval]],
    'en aprobación del cliente' => [PieceStatus::ClientApproval, [PieceStatus::Approved, PieceStatus::InProduction]],
    'aprobada' => [PieceStatus::Approved, [PieceStatus::Delivered]],
    'entregada' => [PieceStatus::Delivered, []],
]);

it('RF-20, RF-21, RF-24, RF-38: cada acción mueve la pieza a su estado y lo registra con su autor', function (string $action, PieceStatus $from, PieceStatus $to) {
    $piece = Piece::factory()->status($from)->create();

    $result = app($action)->handle($piece, $this->actor);

    $entry = PieceHistory::query()->where('piece_id', $piece->id)->sole();

    expect($result->is($piece))->toBeTrue()
        ->and($piece->refresh()->status)->toBe($to)
        ->and($entry->field)->toBe(PieceHistoryField::StatusChanged)
        ->and($entry->old_value)->toBe(['status' => $from->value])
        ->and($entry->new_value)->toBe(['status' => $to->value])
        ->and($entry->author_id)->toBe($this->actor->id);
})->with([
    'pendiente a en producción' => [MarkPieceInProductionAction::class, PieceStatus::Pending, PieceStatus::InProduction],
    'en producción a en revisión' => [MarkPieceInReviewAction::class, PieceStatus::InProduction, PieceStatus::InReview],
    'aprobada a entregada' => [MarkPieceDeliveredAction::class, PieceStatus::Approved, PieceStatus::Delivered],
]);

it('RF-20, RF-21, RF-24: cada acción rechaza con motivo cualquier estado de origen que no sea el suyo', function (string $action, PieceStatus $origin, PieceStatus $target) {
    foreach (PieceStatus::cases() as $status) {
        if ($status === $origin) {
            continue;
        }

        $piece = Piece::factory()->status($status)->create();

        try {
            app($action)->handle($piece, $this->actor);
            $this->fail("Debió rechazar la transición desde {$status->value}.");
        } catch (InvalidPieceTransitionException $exception) {
            expect($exception->getMessage())->toContain($status->label())->toContain($target->label());
        }

        expect($piece->refresh()->status)->toBe($status);
    }

    expect(PieceHistory::query()->count())->toBe(0);
})->with([
    'a en producción' => [MarkPieceInProductionAction::class, PieceStatus::Pending, PieceStatus::InProduction],
    'a en revisión' => [MarkPieceInReviewAction::class, PieceStatus::InProduction, PieceStatus::InReview],
    'a entregada' => [MarkPieceDeliveredAction::class, PieceStatus::Approved, PieceStatus::Delivered],
]);

it('RF-25: una pieza entregada no admite ningún cambio de estado', function (string $action) {
    $piece = Piece::factory()->status(PieceStatus::Delivered)->create();

    expect(fn () => app($action)->handle($piece, $this->actor))->toThrow(InvalidPieceTransitionException::class)
        ->and($piece->refresh()->status)->toBe(PieceStatus::Delivered)
        ->and(PieceHistory::query()->count())->toBe(0);
})->with([
    MarkPieceInProductionAction::class,
    MarkPieceInReviewAction::class,
    MarkPieceDeliveredAction::class,
]);
