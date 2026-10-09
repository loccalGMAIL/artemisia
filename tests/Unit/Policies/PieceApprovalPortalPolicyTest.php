<?php

use App\Enums\PieceStatus;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    app()->setLocale('es');
    $this->client = Client::factory()->create();
    $this->account = User::factory()->client()->create(['client_id' => $this->client->id]);
    $budget = Budget::factory()->accepted()->create(['client_id' => $this->client->id]);
    $this->piece = Piece::factory()->for($budget)->status(PieceStatus::ClientApproval)->create();
    $this->submission = PieceApprovalSubmission::factory()->for($this->piece)->create();
});

it('RF-32: la cuenta vinculada al cliente del presupuesto aprueba y rechaza un envío pendiente en aprobación', function (string $ability) {
    expect(Gate::forUser($this->account)->allows($ability, $this->submission))->toBeTrue();
})->with(['portal.pieces.approve', 'portal.pieces.reject']);

it('RF-47: no se puede actuar si la pieza no está en aprobación del cliente', function (string $ability) {
    $this->piece->forceFill(['status' => PieceStatus::InProduction])->save();

    $inspection = Gate::forUser($this->account)->inspect($ability, $this->submission->refresh());

    expect($inspection->denied())->toBeTrue()
        ->and($inspection->message())->toContain('permiso');
})->with(['portal.pieces.approve', 'portal.pieces.reject']);

it('RF-48: no se puede actuar sobre la pieza de otro cliente', function (string $ability) {
    $stranger = User::factory()->client()->create(['client_id' => Client::factory()->create()->id]);

    $inspection = Gate::forUser($stranger)->inspect($ability, $this->submission);

    expect($inspection->denied())->toBeTrue()
        ->and($inspection->message())->toContain('permiso');
})->with(['portal.pieces.approve', 'portal.pieces.reject']);

it('RF-32: las cuentas de staff y admin no usan las habilidades del portal de piezas', function (string $role, string $ability) {
    $user = User::factory()->{$role}()->create();

    expect(Gate::has($ability))->toBeTrue()
        ->and(Gate::forUser($user)->denies($ability, $this->submission))->toBeTrue();
})->with(function () {
    foreach (['staff', 'admin'] as $role) {
        foreach (['portal.pieces.approve', 'portal.pieces.reject'] as $ability) {
            yield "{$role} / {$ability}" => [$role, $ability];
        }
    }
});
