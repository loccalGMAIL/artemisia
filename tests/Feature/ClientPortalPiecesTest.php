<?php

use App\Enums\PieceStatus;
use App\Filament\Client\Pages\PieceApprovalPage;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('client');
    Storage::fake();
    $this->client = Client::factory()->create(['first_name' => 'Ana', 'last_name' => 'Pérez']);
    $this->budget = Budget::factory()->accepted()->create(['client_id' => $this->client->id, 'title' => 'Identidad visual']);
    $this->account = User::factory()->client()->create(['client_id' => $this->client->id]);
    $this->actingAs($this->account);
});

function portalPieces()
{
    return Livewire::test(PieceApprovalPage::class);
}

it('RF-45: la cuenta vinculada ve las piezas de su cliente en aprobación, aprobadas o entregadas', function (PieceStatus $status) {
    $piece = Piece::factory()->for($this->budget)->status($status)->create(['name' => 'Logo principal']);

    $this->get('/portal/pieces')->assertOk()->assertSeeLivewire(PieceApprovalPage::class);

    portalPieces()
        ->assertCanSeeTableRecords([$piece])
        ->assertSee('Logo principal')
        ->assertSee($status->label());
})->with([PieceStatus::ClientApproval, PieceStatus::Approved, PieceStatus::Delivered]);

it('RF-46: no ve las piezas pendientes, en producción ni en revisión', function (PieceStatus $status) {
    $piece = Piece::factory()->for($this->budget)->status($status)->create(['name' => 'Borrador interno']);

    portalPieces()
        ->assertCanNotSeeTableRecords([$piece])
        ->assertDontSee('Borrador interno');
})->with([PieceStatus::Pending, PieceStatus::InProduction, PieceStatus::InReview]);

it('RF-45, RF-48: no ve las piezas de otros clientes ni las descartadas', function () {
    $mine = Piece::factory()->for($this->budget)->status(PieceStatus::ClientApproval)->create();
    $discarded = Piece::factory()->for($this->budget)->status(PieceStatus::Approved)->discarded()->create();
    $strangers = Piece::factory()
        ->for(Budget::factory()->accepted()->create())
        ->status(PieceStatus::ClientApproval)
        ->create(['name' => 'Pieza ajena']);

    portalPieces()
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$discarded, $strangers])
        ->assertDontSee('Pieza ajena');
});

it('RF-45: una cuenta sin vínculo a un cliente no ve ninguna pieza', function () {
    Piece::factory()->for($this->budget)->status(PieceStatus::ClientApproval)->create(['name' => 'Pieza de Ana']);
    $this->actingAs(User::factory()->client()->create());

    portalPieces()->assertDontSee('Pieza de Ana');
});

it('RF-37: ve sus propios envíos resueltos, incluidos los rechazados, aunque la pieza haya vuelto a producción', function () {
    $piece = Piece::factory()->for($this->budget)->status(PieceStatus::InProduction)->create(['name' => 'Flyer de lanzamiento']);
    PieceApprovalSubmission::factory()->for($piece)->create([
        'resolution' => 'rejected',
        'resolved_by' => $this->account->id,
        'resolved_at' => '2026-10-10 15:30:00',
        'rejection_reason' => 'Falta el logo en la esquina',
    ]);

    portalPieces()
        ->assertCanNotSeeTableRecords([$piece])
        ->assertSee('Mis respuestas')
        ->assertSee('Flyer de lanzamiento')
        ->assertSee('Rechazada')
        ->assertSee('Falta el logo en la esquina')
        ->assertSee('10/10/2026');
});

it('RF-37: no ve los envíos resueltos por otra cuenta, aunque sea del mismo cliente, ni los pendientes ni los de otros clientes', function () {
    $piece = Piece::factory()->for($this->budget)->status(PieceStatus::Approved)->create(['name' => 'Tarjeta personal']);
    $colleague = User::factory()->client()->create(['client_id' => $this->client->id]);
    PieceApprovalSubmission::factory()->for($piece)->create([
        'resolution' => 'rejected',
        'resolved_by' => $colleague->id,
        'resolved_at' => now(),
        'rejection_reason' => 'Motivo de la colega',
    ]);
    PieceApprovalSubmission::factory()->for($piece)->create();
    $stranger = User::factory()->client()->create(['client_id' => Client::factory()->create()->id]);
    PieceApprovalSubmission::factory()->for(Piece::factory()->create())->create([
        'resolution' => 'rejected',
        'resolved_by' => $stranger->id,
        'resolved_at' => now(),
        'rejection_reason' => 'Motivo de otro cliente',
    ]);

    portalPieces()
        ->assertDontSee('Motivo de la colega')
        ->assertDontSee('Motivo de otro cliente');
});

it('RF-37: avisa cuando todavía no hay respuestas propias', function () {
    portalPieces()->assertSee('Mis respuestas')->assertSee('Todavía no respondió ningún envío.');
});

it('RF-32, RF-48: descarga el archivo del envío vigente de su pieza, y ninguna otra cuenta puede descargarlo', function () {
    $piece = Piece::factory()->for($this->budget)->status(PieceStatus::ClientApproval)->create();
    $submission = PieceApprovalSubmission::factory()->for($piece)->create(['file_path' => 'piece-submissions/v1.pdf', 'file_extension' => 'pdf']);
    Storage::disk()->put('piece-submissions/v1.pdf', 'contenido');

    portalPieces()
        ->callTableAction('download', $piece)
        ->assertFileDownloaded("pieza-{$piece->id}-envio-{$submission->id}.pdf");

    expect(Gate::forUser($this->account)->allows('portal.pieces.download', $submission))->toBeTrue();

    $stranger = User::factory()->client()->create(['client_id' => Client::factory()->create()->id]);
    $inspection = Gate::forUser($stranger)->inspect('portal.pieces.download', $submission);

    expect($inspection->denied())->toBeTrue()
        ->and($inspection->message())->toContain('permiso')
        ->and(Gate::forUser(User::factory()->staff()->create())->denies('portal.pieces.download', $submission))->toBeTrue();
});

it('RF-37: descarga el archivo de un envío que ella rechazó aunque la pieza haya vuelto a producción', function () {
    $piece = Piece::factory()->for($this->budget)->status(PieceStatus::InProduction)->create();
    $rejected = PieceApprovalSubmission::factory()->for($piece)->create([
        'resolution' => 'rejected',
        'resolved_by' => $this->account->id,
        'resolved_at' => now(),
    ]);

    expect(Gate::forUser($this->account)->allows('portal.pieces.download', $rejected))->toBeTrue();
});

it('RF-45: un envío pendiente de una pieza que el cliente todavía no puede ver no se descarga', function () {
    $piece = Piece::factory()->for($this->budget)->status(PieceStatus::InReview)->create();
    $submission = PieceApprovalSubmission::factory()->for($piece)->create();

    expect(Gate::has('portal.pieces.download'))->toBeTrue()
        ->and(Gate::forUser($this->account)->denies('portal.pieces.download', $submission))->toBeTrue();
});
