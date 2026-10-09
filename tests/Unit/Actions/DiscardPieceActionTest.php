<?php

use App\Actions\DiscardPieceAction;
use App\Enums\PieceStatus;
use App\Exceptions\PieceNotDiscardableException;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\PieceHistory;
use App\Models\User;

beforeEach(function () {
    app()->setLocale('es');
    $this->actor = User::factory()->staff()->create();
});

it('RF-26, RF-27: descarta una pieza pendiente, que deja de mostrarse en el listado normal', function () {
    $piece = Piece::factory()->create();
    $other = Piece::factory()->create();

    app(DiscardPieceAction::class)->handle($piece, $this->actor);

    expect($piece->refresh()->trashed())->toBeTrue()
        ->and(Piece::query()->pluck('id')->all())->toBe([$other->id])
        ->and(Piece::withTrashed()->count())->toBe(2)
        ->and(Piece::onlyTrashed()->pluck('id')->all())->toBe([$piece->id]);
});

it('RF-26: solo se descarta una pieza pendiente', function (PieceStatus $status) {
    $piece = Piece::factory()->status($status)->create();

    try {
        app(DiscardPieceAction::class)->handle($piece, $this->actor);
        $this->fail('Debió rechazar el descarte.');
    } catch (PieceNotDiscardableException $exception) {
        expect($exception->getMessage())->toContain('pendiente');
    }

    expect($piece->refresh()->trashed())->toBeFalse()
        ->and(Piece::query()->whereKey($piece->id)->exists())->toBeTrue();
})->with([
    PieceStatus::InProduction,
    PieceStatus::InReview,
    PieceStatus::ClientApproval,
    PieceStatus::Approved,
    PieceStatus::Delivered,
]);

it('RF-27, RF-40, RNF-3: descartar conserva el historial y los envíos de la pieza', function () {
    $piece = Piece::factory()->create();
    PieceHistory::factory()->count(2)->for($piece)->create();
    PieceApprovalSubmission::factory()->for($piece)->create();

    app(DiscardPieceAction::class)->handle($piece, $this->actor);

    expect(PieceHistory::query()->where('piece_id', $piece->id)->count())->toBe(2)
        ->and(PieceApprovalSubmission::query()->where('piece_id', $piece->id)->count())->toBe(1)
        ->and($piece->histories()->count())->toBe(2)
        ->and(PieceHistory::query()->first()->piece->is($piece))->toBeTrue();
});

it('RF-40: una pieza no se elimina físicamente', function () {
    $piece = Piece::factory()->create();

    expect(fn () => $piece->forceDelete())->toThrow(LogicException::class)
        ->and(Piece::withTrashed()->whereKey($piece->id)->exists())->toBeTrue();
});
