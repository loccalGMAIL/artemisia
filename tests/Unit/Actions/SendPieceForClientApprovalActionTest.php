<?php

use App\Actions\SendPieceForClientApprovalAction;
use App\Enums\PieceHistoryField;
use App\Enums\PieceStatus;
use App\Exceptions\InvalidPieceTransitionException;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\PieceHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app()->setLocale('es');
    Storage::fake();
    Carbon::setTestNow('2026-10-15 10:00:00');
    $this->actor = User::factory()->staff()->create();
    $this->piece = Piece::factory()->status(PieceStatus::InReview)->create();
});

afterEach(fn () => Carbon::setTestNow());

function approvalFileErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('RF-22, RF-23, RF-28, RF-30: envía a aprobación una pieza en revisión, guardando el archivo y la fecha del envío', function () {
    $file = UploadedFile::fake()->create('logo-v1.pdf', 500, 'application/pdf');

    $submission = app(SendPieceForClientApprovalAction::class)->handle($this->piece, $file, $this->actor);

    expect($submission)->toBeInstanceOf(PieceApprovalSubmission::class)
        ->and($submission->piece_id)->toBe($this->piece->id)
        ->and($submission->file_extension)->toBe('pdf')
        ->and($submission->submitted_by)->toBe($this->actor->id)
        ->and($submission->submitted_at->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and($submission->isPending())->toBeTrue()
        ->and($this->piece->refresh()->status)->toBe(PieceStatus::ClientApproval);

    Storage::disk()->assertExists($submission->file_path);
});

it('RF-23, RF-38: registra el cambio de estado de la pieza con su autor', function () {
    app(SendPieceForClientApprovalAction::class)
        ->handle($this->piece, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), $this->actor);

    $entry = PieceHistory::query()->where('piece_id', $this->piece->id)->sole();

    expect($entry->field)->toBe(PieceHistoryField::StatusChanged)
        ->and($entry->old_value)->toBe(['status' => 'in_review'])
        ->and($entry->new_value)->toBe(['status' => 'client_approval'])
        ->and($entry->author_id)->toBe($this->actor->id);
});

it('RF-29: admite archivos JPG, PNG, PDF y MP4', function (string $name, string $mime, string $extension) {
    $submission = app(SendPieceForClientApprovalAction::class)
        ->handle($this->piece, UploadedFile::fake()->create($name, 100, $mime), $this->actor);

    expect($submission->file_extension)->toBe($extension);
    Storage::disk()->assertExists($submission->file_path);
})->with([
    'jpg' => ['foto.jpg', 'image/jpeg', 'jpg'],
    'png' => ['foto.png', 'image/png', 'png'],
    'pdf' => ['doc.pdf', 'application/pdf', 'pdf'],
    'mp4' => ['video.mp4', 'video/mp4', 'mp4'],
]);

it('RF-31: rechaza con motivo un archivo de otro formato y no cambia nada', function (string $name, string $mime) {
    $errors = approvalFileErrors(fn () => app(SendPieceForClientApprovalAction::class)
        ->handle($this->piece, UploadedFile::fake()->create($name, 100, $mime), $this->actor));

    expect($errors)->toHaveKey('file')
        ->and($errors['file'][0])->toContain('JPG')->toContain('MP4')
        ->and($this->piece->refresh()->status)->toBe(PieceStatus::InReview)
        ->and(PieceApprovalSubmission::query()->count())->toBe(0)
        ->and(PieceHistory::query()->count())->toBe(0)
        ->and(Storage::disk()->allFiles())->toBe([]);
})->with([
    'gif' => ['animacion.gif', 'image/gif'],
    'zip' => ['entrega.zip', 'application/zip'],
    'docx' => ['texto.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
]);

it('RNF-1, RF-31: acepta hasta 100 MB y rechaza con motivo un archivo más grande', function () {
    $limit = UploadedFile::fake()->create('grande.mp4', 102400, 'video/mp4');
    $over = UploadedFile::fake()->create('enorme.mp4', 102401, 'video/mp4');

    $accepted = app(SendPieceForClientApprovalAction::class)->handle($this->piece, $limit, $this->actor);
    $piece = Piece::factory()->status(PieceStatus::InReview)->create();
    $errors = approvalFileErrors(fn () => app(SendPieceForClientApprovalAction::class)->handle($piece, $over, $this->actor));

    expect($accepted->file_extension)->toBe('mp4')
        ->and($errors)->toHaveKey('file')
        ->and($errors['file'][0])->toContain('100 MB')
        ->and($piece->refresh()->status)->toBe(PieceStatus::InReview)
        ->and(PieceApprovalSubmission::query()->count())->toBe(1);
});

it('RF-28: exige adjuntar un archivo', function () {
    $errors = approvalFileErrors(fn () => app(SendPieceForClientApprovalAction::class)
        ->handle($this->piece, null, $this->actor));

    expect($errors)->toHaveKey('file')
        ->and($this->piece->refresh()->status)->toBe(PieceStatus::InReview);
});

it('RF-22: solo se envía a aprobación una pieza en revisión', function (PieceStatus $status) {
    $piece = Piece::factory()->status($status)->create();

    expect(fn () => app(SendPieceForClientApprovalAction::class)
        ->handle($piece, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), $this->actor))
        ->toThrow(InvalidPieceTransitionException::class)
        ->and($piece->refresh()->status)->toBe($status)
        ->and(PieceApprovalSubmission::query()->count())->toBe(0)
        ->and(Storage::disk()->allFiles())->toBe([]);
})->with([
    PieceStatus::Pending,
    PieceStatus::InProduction,
    PieceStatus::ClientApproval,
    PieceStatus::Approved,
    PieceStatus::Delivered,
]);

it('RF-30, RNF-3: conserva cada archivo enviado con su fecha aunque la pieza reciba más de un envío', function () {
    $action = app(SendPieceForClientApprovalAction::class);

    $first = $action->handle($this->piece, UploadedFile::fake()->create('v1.pdf', 10, 'application/pdf'), $this->actor);

    Carbon::setTestNow('2026-10-20 09:30:00');
    $this->piece->forceFill(['status' => PieceStatus::InReview])->save();
    $second = $action->handle($this->piece, UploadedFile::fake()->create('v2.png', 10, 'image/png'), $this->actor);

    expect(PieceApprovalSubmission::query()->where('piece_id', $this->piece->id)->count())->toBe(2)
        ->and($first->refresh()->file_path)->not->toBe($second->file_path)
        ->and($first->submitted_at->toDateString())->toBe('2026-10-15')
        ->and($second->submitted_at->toDateString())->toBe('2026-10-20');

    Storage::disk()->assertExists([$first->file_path, $second->file_path]);
});
