<?php

use App\Actions\RejectPieceAction;
use App\Actions\SendPieceForClientApprovalAction;
use App\Enums\PieceApprovalResolution;
use App\Enums\PieceStatus;
use App\Models\Budget;
use App\Models\Client;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\PieceHistory;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    app()->setLocale('es');
    $this->piece = Piece::factory()->create();
});

it('RF-39: el historial de la pieza se consulta en orden cronológico', function () {
    $third = PieceHistory::factory()->for($this->piece)->create(['created_at' => '2026-10-03 09:00:00']);
    $first = PieceHistory::factory()->for($this->piece)->create(['created_at' => '2026-10-01 09:00:00']);
    $secondA = PieceHistory::factory()->for($this->piece)->create(['created_at' => '2026-10-02 09:00:00']);
    $secondB = PieceHistory::factory()->for($this->piece)->create(['created_at' => '2026-10-02 09:00:00']);
    PieceHistory::factory()->create(['created_at' => '2026-09-01 09:00:00']);

    expect($this->piece->histories()->toBase()->orders)->not->toBeEmpty()
        ->and($this->piece->histories->pluck('id')->all())
        ->toBe([$first->id, $secondA->id, $secondB->id, $third->id]);
});

it('RF-36: el staff consulta todos los envíos de la pieza, del primero al último, con archivo, resultado y motivo', function () {
    $late = PieceApprovalSubmission::factory()->for($this->piece)->create(['submitted_at' => '2026-10-10 10:00:00']);
    $rejected = PieceApprovalSubmission::factory()->for($this->piece)->rejected('Cambiar el color')->create(['submitted_at' => '2026-10-01 10:00:00']);
    $approved = PieceApprovalSubmission::factory()->for($this->piece)->approved()->create(['submitted_at' => '2026-10-05 10:00:00']);
    PieceApprovalSubmission::factory()->create();

    $submissions = $this->piece->submissions;

    expect($submissions->pluck('id')->all())->toBe([$rejected->id, $approved->id, $late->id])
        ->and($submissions[0]->resolution)->toBe(PieceApprovalResolution::Rejected)
        ->and($submissions[0]->rejection_reason)->toBe('Cambiar el color')
        ->and($submissions[0]->file_path)->not->toBeEmpty()
        ->and($submissions[1]->resolution)->toBe(PieceApprovalResolution::Approved)
        ->and($submissions[2]->isPending())->toBeTrue();
});

it('RF-37: la cuenta cliente consulta solo los envíos que ella misma resolvió, incluidos los rechazados', function () {
    $mine = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $rejected = PieceApprovalSubmission::factory()->for($this->piece)->create([
        'resolution' => PieceApprovalResolution::Rejected,
        'resolved_by' => $mine->id,
        'resolved_at' => now(),
        'rejection_reason' => 'No me convence',
    ]);
    $approved = PieceApprovalSubmission::factory()->for($this->piece)->create([
        'resolution' => PieceApprovalResolution::Approved,
        'resolved_by' => $mine->id,
        'resolved_at' => now(),
    ]);
    PieceApprovalSubmission::factory()->for($this->piece)->create([
        'resolution' => PieceApprovalResolution::Approved,
        'resolved_by' => $other->id,
        'resolved_at' => now(),
    ]);
    PieceApprovalSubmission::factory()->for($this->piece)->create();

    $visible = $this->piece->submissions()->resolvedBy($mine)->get();

    expect($visible->pluck('id')->all())->toBe([$rejected->id, $approved->id])
        ->and($visible[0]->rejection_reason)->toBe('No me convence');
});

it('RF-37: el envío rechazado sigue consultable por la cuenta que lo resolvió aunque la pieza haya vuelto a producción', function () {
    Storage::fake();
    $client = Client::factory()->create();
    $account = User::factory()->client()->create(['client_id' => $client->id]);
    $staff = User::factory()->staff()->create();
    $piece = Piece::factory()->for(Budget::factory()->accepted()->create(['client_id' => $client->id]))
        ->status(PieceStatus::InReview)->create();

    $submission = app(SendPieceForClientApprovalAction::class)
        ->handle($piece, UploadedFile::fake()->create('v1.pdf', 10, 'application/pdf'), $staff);
    app(RejectPieceAction::class)->handle($submission, $account, 'Falta el logo');

    $visible = $piece->refresh()->submissions()->resolvedBy($account)->get();

    expect($piece->status)->toBe(PieceStatus::InProduction)
        ->and($visible)->toHaveCount(1)
        ->and($visible[0]->resolution)->toBe(PieceApprovalResolution::Rejected)
        ->and($visible[0]->rejection_reason)->toBe('Falta el logo');
});

it('RF-40, RNF-3: no existe comando ni tarea programada que purgue piezas, historiales o envíos', function () {
    $commands = collect(array_keys(Artisan::all()))->filter(fn (string $name) => str_contains($name, 'piece'));
    $scheduled = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'piece'));

    expect($commands)->toBeEmpty()
        ->and($scheduled)->toBeEmpty();
});
