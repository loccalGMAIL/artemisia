<?php

use App\Enums\PieceApprovalResolution;
use App\Enums\PieceStatus;
use App\Filament\Client\Pages\PieceApprovalPage;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\PieceHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('client');
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
    $this->client = Client::factory()->create();
    $this->budget = Budget::factory()->accepted()->create(['client_id' => $this->client->id]);
    $this->account = User::factory()->client()->create(['client_id' => $this->client->id]);
    $this->actingAs($this->account);
    $this->piece = Piece::factory()->for($this->budget)->status(PieceStatus::ClientApproval)->create(['name' => 'Logo principal']);
    $this->submission = PieceApprovalSubmission::factory()->for($this->piece)->create();
});

function approvalPage()
{
    return Livewire::test(PieceApprovalPage::class);
}

it('RF-32: ofrece aprobar y rechazar solo mientras la pieza está en aprobación del cliente', function (PieceStatus $status, bool $offered) {
    $piece = Piece::factory()->for($this->budget)->status($status)->create();
    PieceApprovalSubmission::factory()->for($piece)->create();

    $page = approvalPage();

    foreach (['approve', 'reject'] as $action) {
        $offered
            ? $page->assertTableActionVisible($action, $piece)
            : $page->assertTableActionHidden($action, $piece);
    }
})->with([
    'en aprobación' => [PieceStatus::ClientApproval, true],
    'aprobada' => [PieceStatus::Approved, false],
    'entregada' => [PieceStatus::Delivered, false],
]);

it('RF-32, RF-33: aprueba la pieza, que queda aprobada con la fecha de la aprobación', function () {
    approvalPage()
        ->callTableAction('approve', $this->piece)
        ->assertNotified('Pieza aprobada.');

    $this->submission->refresh();

    expect($this->piece->refresh()->status)->toBe(PieceStatus::Approved)
        ->and($this->submission->resolution)->toBe(PieceApprovalResolution::Approved)
        ->and($this->submission->resolved_by)->toBe($this->account->id)
        ->and($this->submission->resolved_at->toDateString())->toBe('2026-10-15');
});

it('RF-34: rechaza la pieza con un motivo, que vuelve a producción y queda entre las respuestas del cliente', function () {
    approvalPage()
        ->callTableAction('reject', $this->piece, ['rejection_reason' => 'El logo está cortado'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Pieza rechazada.')
        ->assertCanNotSeeTableRecords([$this->piece])
        ->assertSee('Mis respuestas')
        ->assertSee('Logo principal')
        ->assertSee('El logo está cortado');

    $this->submission->refresh();

    expect($this->piece->refresh()->status)->toBe(PieceStatus::InProduction)
        ->and($this->submission->resolution)->toBe(PieceApprovalResolution::Rejected)
        ->and($this->submission->rejection_reason)->toBe('El logo está cortado')
        ->and(PieceHistory::query()->where('piece_id', $this->piece->id)->count())->toBe(1);
});

it('RF-35: el motivo del rechazo es opcional', function (?string $reason) {
    approvalPage()
        ->callTableAction('reject', $this->piece, ['rejection_reason' => $reason])
        ->assertHasNoTableActionErrors();

    expect($this->piece->refresh()->status)->toBe(PieceStatus::InProduction)
        ->and($this->submission->refresh()->rejection_reason)->toBeNull();
})->with([null, '']);

it('RF-35: el motivo del rechazo admite hasta 500 caracteres', function () {
    approvalPage()
        ->callTableAction('reject', $this->piece, ['rejection_reason' => str_repeat('a', 501)])
        ->assertHasTableActionErrors(['rejection_reason']);

    expect($this->piece->refresh()->status)->toBe(PieceStatus::ClientApproval)
        ->and($this->submission->refresh()->isPending())->toBeTrue();
});

it('RF-47: si la pieza ya no está en aprobación cuando se confirma, la acción se rechaza y no cambia nada', function (string $action) {
    $page = approvalPage()->mountTableAction($action, $this->piece);

    $this->piece->forceFill(['status' => PieceStatus::InProduction])->save();

    $page->callMountedTableAction()->assertNotNotified();

    expect($this->piece->refresh()->status)->toBe(PieceStatus::InProduction)
        ->and($this->submission->refresh()->isPending())->toBeTrue()
        ->and(PieceHistory::query()->count())->toBe(0);
})->with(['approve', 'reject']);

it('RF-48: una pieza de otro cliente no se puede aprobar ni rechazar, aunque se conozca su identificador', function (string $action) {
    $foreign = Piece::factory()->for(Budget::factory()->accepted()->create())->status(PieceStatus::ClientApproval)->create();
    $foreignSubmission = PieceApprovalSubmission::factory()->for($foreign)->create();

    $page = approvalPage()
        ->assertTableActionVisible($action, $this->piece)
        ->assertCanNotSeeTableRecords([$foreign]);

    try {
        $page->callTableAction($action, $foreign);
    } catch (Throwable) {
        // The record is not reachable from this page at all.
    }

    expect($foreign->refresh()->status)->toBe(PieceStatus::ClientApproval)
        ->and($foreignSubmission->refresh()->isPending())->toBeTrue();
})->with(['approve', 'reject']);
