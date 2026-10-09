<?php

use App\Actions\ApprovePieceAction;
use App\Actions\RejectPieceAction;
use App\Enums\PieceApprovalResolution;
use App\Enums\PieceHistoryField;
use App\Enums\PieceStatus;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\PieceHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    Carbon::setTestNow('2026-10-15 10:00:00');
    $this->client = Client::factory()->create();
    $this->account = User::factory()->client()->create(['client_id' => $this->client->id]);
    $budget = Budget::factory()->accepted()->create(['client_id' => $this->client->id]);
    $this->piece = Piece::factory()->for($budget)->status(PieceStatus::ClientApproval)->create();
    $this->submission = PieceApprovalSubmission::factory()->for($this->piece)->create();
});

afterEach(fn () => Carbon::setTestNow());

it('RF-32, RF-33: la cuenta vinculada aprueba la pieza, que queda aprobada con la fecha de la aprobación', function () {
    $piece = app(ApprovePieceAction::class)->handle($this->submission, $this->account);

    $this->submission->refresh();

    expect($piece->is($this->piece))->toBeTrue()
        ->and($piece->refresh()->status)->toBe(PieceStatus::Approved)
        ->and($this->submission->resolution)->toBe(PieceApprovalResolution::Approved)
        ->and($this->submission->resolved_by)->toBe($this->account->id)
        ->and($this->submission->resolved_at->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and($this->submission->rejection_reason)->toBeNull();
});

it('RF-33, RF-38: la aprobación queda registrada en el historial con la cuenta cliente como autora', function () {
    app(ApprovePieceAction::class)->handle($this->submission, $this->account);

    $entry = PieceHistory::query()->where('piece_id', $this->piece->id)->sole();

    expect($entry->field)->toBe(PieceHistoryField::StatusChanged)
        ->and($entry->old_value)->toBe(['status' => 'client_approval'])
        ->and($entry->new_value)->toBe(['status' => 'approved'])
        ->and($entry->author_id)->toBe($this->account->id);
});

it('RF-34: el rechazo devuelve la pieza a producción y registra el motivo, la fecha y el envío', function () {
    app(RejectPieceAction::class)->handle($this->submission, $this->account, 'El logo está cortado');

    $this->submission->refresh();

    expect($this->piece->refresh()->status)->toBe(PieceStatus::InProduction)
        ->and($this->submission->resolution)->toBe(PieceApprovalResolution::Rejected)
        ->and($this->submission->rejection_reason)->toBe('El logo está cortado')
        ->and($this->submission->resolved_by)->toBe($this->account->id)
        ->and($this->submission->resolved_at->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and($this->piece->submissions()->whereKey($this->submission->id)->exists())->toBeTrue();

    $entry = PieceHistory::query()->where('piece_id', $this->piece->id)->sole();

    expect($entry->old_value)->toBe(['status' => 'client_approval'])
        ->and($entry->new_value)->toBe(['status' => 'in_production']);
});

it('RF-35: acepta el rechazo sin motivo', function (?string $reason) {
    app(RejectPieceAction::class)->handle($this->submission, $this->account, $reason);

    expect($this->submission->refresh()->resolution)->toBe(PieceApprovalResolution::Rejected)
        ->and($this->submission->rejection_reason)->toBeNull()
        ->and($this->piece->refresh()->status)->toBe(PieceStatus::InProduction);
})->with([null, '', '   ']);

it('RF-35: el motivo del rechazo admite hasta 500 caracteres', function () {
    expect(fn () => app(RejectPieceAction::class)->handle($this->submission, $this->account, str_repeat('a', 501)))
        ->toThrow(ValidationException::class)
        ->and($this->piece->refresh()->status)->toBe(PieceStatus::ClientApproval);

    app(RejectPieceAction::class)->handle($this->submission, $this->account, str_repeat('a', 500));

    expect($this->submission->refresh()->rejection_reason)->toHaveLength(500);
});

it('RF-47: no se aprueba ni se rechaza una pieza que no está en aprobación del cliente', function (string $action, PieceStatus $status) {
    $this->piece->forceFill(['status' => $status])->save();

    try {
        $action === ApprovePieceAction::class
            ? app($action)->handle($this->submission, $this->account)
            : app($action)->handle($this->submission, $this->account, 'No');
        $this->fail('Debió rechazar la acción.');
    } catch (AuthorizationException $exception) {
        expect($exception->getMessage())->toContain('permiso');
    }

    expect($this->piece->refresh()->status)->toBe($status)
        ->and($this->submission->refresh()->isPending())->toBeTrue()
        ->and(PieceHistory::query()->count())->toBe(0);
})->with(function () {
    foreach ([ApprovePieceAction::class, RejectPieceAction::class] as $action) {
        foreach ([PieceStatus::Pending, PieceStatus::InProduction, PieceStatus::InReview, PieceStatus::Approved, PieceStatus::Delivered] as $status) {
            yield class_basename($action).' / '.$status->value => [$action, $status];
        }
    }
});

it('RF-47: un envío ya resuelto no se resuelve de nuevo', function () {
    app(RejectPieceAction::class)->handle($this->submission, $this->account, 'No me gusta');
    $this->piece->forceFill(['status' => PieceStatus::ClientApproval])->save();

    expect(fn () => app(ApprovePieceAction::class)->handle($this->submission->refresh(), $this->account))
        ->toThrow(AuthorizationException::class)
        ->and($this->submission->refresh()->resolution)->toBe(PieceApprovalResolution::Rejected);
});

it('RF-32: aprobar resuelve solo el envío vigente, sin crear envíos nuevos ni tocar los anteriores', function () {
    $older = PieceApprovalSubmission::factory()->for($this->piece)->rejected('Antes')->create(['submitted_at' => now()->subDays(3)]);
    $before = PieceApprovalSubmission::query()->count();

    app(ApprovePieceAction::class)->handle($this->submission, $this->account);

    expect(PieceApprovalSubmission::query()->count())->toBe($before)
        ->and($older->refresh()->resolution)->toBe(PieceApprovalResolution::Rejected)
        ->and($older->rejection_reason)->toBe('Antes')
        ->and($this->submission->refresh()->resolution)->toBe(PieceApprovalResolution::Approved);
});

it('RF-48: una cuenta de otro cliente no aprueba ni rechaza, y se le indica permiso insuficiente', function () {
    $stranger = User::factory()->client()->create(['client_id' => Client::factory()->create()->id]);

    foreach ([fn () => app(ApprovePieceAction::class)->handle($this->submission, $stranger),
        fn () => app(RejectPieceAction::class)->handle($this->submission, $stranger, 'No')] as $attempt) {
        try {
            $attempt();
            $this->fail('Debió rechazar la acción.');
        } catch (AuthorizationException $exception) {
            expect($exception->getMessage())->toContain('permiso');
        }
    }

    expect($this->piece->refresh()->status)->toBe(PieceStatus::ClientApproval)
        ->and($this->submission->refresh()->isPending())->toBeTrue();
});

it('RF-32, RF-48: ni el staff ni una cuenta cliente sin vínculo resuelven un envío', function (string $kind) {
    $account = match ($kind) {
        'staff' => User::factory()->staff()->create(),
        'admin' => User::factory()->admin()->create(),
        'sin vínculo' => User::factory()->client()->create(),
    };

    expect(fn () => app(ApprovePieceAction::class)->handle($this->submission, $account))
        ->toThrow(AuthorizationException::class)
        ->and($this->piece->refresh()->status)->toBe(PieceStatus::ClientApproval);
})->with(['staff', 'admin', 'sin vínculo']);
