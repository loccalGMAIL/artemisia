<?php

use App\Enums\PieceApprovalResolution;
use App\Enums\PieceHistoryField;
use App\Enums\PieceStatus;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\PieceHistory;
use App\Models\User;
use App\Models\WorkCategory;

it('RF-18: la pieza tiene exactamente un estado y nace pendiente', function () {
    $piece = Piece::factory()->create()->refresh();

    expect($piece->status)->toBe(PieceStatus::Pending)
        ->and(PieceStatus::cases())->toHaveCount(6);
});

it('RF-3: la pieza carga su presupuesto, su categoría, su responsable y su ítem de origen', function () {
    $item = BudgetItem::factory()->create();
    $assignee = User::factory()->create();

    $piece = Piece::factory()->for($item->budget)->create([
        'budget_item_id' => $item->id,
        'assignee_id' => $assignee->id,
    ]);

    expect($piece->budget)->toBeInstanceOf(Budget::class)
        ->and($piece->category)->toBeInstanceOf(WorkCategory::class)
        ->and($piece->assignee->is($assignee))->toBeTrue()
        ->and($piece->budgetItem->is($item))->toBeTrue()
        ->and($piece->creator)->toBeInstanceOf(User::class);
});

it('RF-3: una pieza suelta no tiene ítem de origen', function () {
    $piece = Piece::factory()->loose()->create();

    expect($piece->budget_item_id)->toBeNull()
        ->and($piece->budgetItem)->toBeNull();
});

it('RF-30, RNF-3: la pieza carga sus envíos a aprobación y su historial', function () {
    $piece = Piece::factory()->create();
    PieceApprovalSubmission::factory()->count(2)->for($piece)->create();
    PieceHistory::factory()->for($piece)->create();

    expect($piece->submissions)->toHaveCount(2)
        ->and($piece->histories)->toHaveCount(1)
        ->and($piece->submissions->first()->piece->is($piece))->toBeTrue();
});

it('RF-33, RF-34: el envío nace pendiente y se resuelve con una cuenta cliente', function () {
    $pending = PieceApprovalSubmission::factory()->create()->refresh();
    $resolved = PieceApprovalSubmission::factory()->rejected('No me gusta el color')->create()->refresh();

    expect($pending->resolution)->toBeNull()
        ->and($pending->isPending())->toBeTrue()
        ->and($resolved->resolution)->toBe(PieceApprovalResolution::Rejected)
        ->and($resolved->resolver)->toBeInstanceOf(User::class)
        ->and($resolved->rejection_reason)->toBe('No me gusta el color')
        ->and($resolved->isPending())->toBeFalse();
});

it('RF-38: el historial guarda el campo, el valor anterior y el nuevo, y su autor', function () {
    $entry = PieceHistory::factory()->create([
        'field' => PieceHistoryField::StatusChanged,
        'old_value' => ['status' => 'pending'],
        'new_value' => ['status' => 'in_production'],
    ])->refresh();

    expect($entry->field)->toBe(PieceHistoryField::StatusChanged)
        ->and($entry->old_value)->toBe(['status' => 'pending'])
        ->and($entry->new_value)->toBe(['status' => 'in_production'])
        ->and($entry->author)->toBeInstanceOf(User::class)
        ->and($entry->piece)->toBeInstanceOf(Piece::class);
});

it('RF-40, RNF-3: un asiento del historial no se modifica ni se elimina', function () {
    $entry = PieceHistory::factory()->create();

    expect(fn () => $entry->update(['new_value' => ['x' => 1]]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('RF-30, RNF-3: un envío a aprobación no se elimina', function () {
    $submission = PieceApprovalSubmission::factory()->create();

    expect(fn () => $submission->delete())->toThrow(LogicException::class);
});

it('RF-27, RF-40: descartar una pieza la conserva con soft delete', function () {
    $piece = Piece::factory()->create();

    $piece->delete();

    expect(Piece::query()->count())->toBe(0)
        ->and(Piece::withTrashed()->count())->toBe(1);
});
