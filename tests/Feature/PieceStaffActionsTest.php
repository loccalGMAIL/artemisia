<?php

use App\Enums\PieceHistoryField;
use App\Enums\PieceStatus;
use App\Filament\Staff\Resources\Pieces\Pages\ListPieces;
use App\Filament\Staff\Resources\Pieces\Pages\ViewPiece;
use App\Filament\Staff\Resources\Pieces\PieceResource;
use App\Filament\Staff\Resources\Pieces\RelationManagers\HistoriesRelationManager;
use App\Filament\Staff\Resources\Pieces\RelationManagers\SubmissionsRelationManager;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use App\Models\PieceHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()->setLocale('es');
    Filament::setCurrentPanel('staff');
    Storage::fake();
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
    $this->actor = User::factory()->staff()->create();
    $this->actingAs($this->actor);
});

function piecePage(Piece $piece)
{
    return Livewire::test(ViewPiece::class, ['record' => $piece->getKey()]);
}

it('RF-41: el listado lleva a la ficha de cada pieza, que muestra sus datos', function () {
    $piece = Piece::factory()->create(['name' => 'Logo principal', 'description' => 'Versión horizontal']);

    Livewire::test(ListPieces::class)->assertCanSeeTableRecords([$piece]);

    piecePage($piece)
        ->assertSee('Logo principal')
        ->assertSee('Versión horizontal')
        ->assertSee('Pendiente');

    expect(PieceResource::getUrl('view', ['record' => $piece]))->toContain("/pieces/{$piece->id}");
});

it('RF-11, RF-12, RF-14, RF-38: delega la pieza a una cuenta admin o staff y la reasigna', function () {
    $first = User::factory()->staff()->create();
    $second = User::factory()->admin()->create();
    $client = User::factory()->client()->create();
    $piece = Piece::factory()->create();

    piecePage($piece)
        ->mountAction('assignPiece')
        ->assertFormFieldExists('assignee_id', checkFieldUsing: fn (Select $field): bool => array_key_exists($first->id, $field->getOptions())
            && array_key_exists($second->id, $field->getOptions())
            && ! array_key_exists($client->id, $field->getOptions()));

    piecePage($piece)->callAction('assignPiece', ['assignee_id' => $first->id])->assertHasNoActionErrors()->assertNotified();
    piecePage($piece)->callAction('assignPiece', ['assignee_id' => $second->id])->assertHasNoActionErrors();

    expect($piece->refresh()->assignee_id)->toBe($second->id)
        ->and($piece->histories()->where('field', PieceHistoryField::AssigneeChanged)->count())->toBe(2);
});

it('RF-11, RF-25: una pieza entregada no se puede delegar ni reasignar', function () {
    $piece = Piece::factory()->status(PieceStatus::Delivered)->create();

    piecePage($piece)->assertActionHidden('assignPiece');
});

it('RF-15, RF-16: carga, modifica y quita la fecha de entrega comprometida', function () {
    $piece = Piece::factory()->create();

    piecePage($piece)->callAction('setDueDate', ['due_date' => '2026-11-10'])->assertHasNoActionErrors();
    expect($piece->refresh()->due_date->toDateString())->toBe('2026-11-10');

    piecePage($piece)->callAction('setDueDate', ['due_date' => '2026-11-30']);
    expect($piece->refresh()->due_date->toDateString())->toBe('2026-11-30');

    piecePage($piece)->callAction('setDueDate', ['due_date' => null]);
    expect($piece->refresh()->due_date)->toBeNull()
        ->and($piece->histories()->where('field', PieceHistoryField::DueDateChanged)->count())->toBe(3);
});

it('RF-20, RF-21, RF-38: pasa la pieza a producción y a revisión, y solo ofrece cada paso en su estado', function () {
    $piece = Piece::factory()->create();

    piecePage($piece)
        ->assertActionVisible('markInProduction')
        ->assertActionHidden('markInReview')
        ->assertActionHidden('sendForApproval')
        ->assertActionHidden('markDelivered')
        ->callAction('markInProduction')
        ->assertNotified();

    expect($piece->refresh()->status)->toBe(PieceStatus::InProduction);

    piecePage($piece)
        ->assertActionHidden('markInProduction')
        ->assertActionVisible('markInReview')
        ->callAction('markInReview');

    expect($piece->refresh()->status)->toBe(PieceStatus::InReview)
        ->and($piece->histories()->where('field', PieceHistoryField::StatusChanged)->count())->toBe(2);
});

it('RF-22, RF-23, RF-28: envía a aprobación del cliente la pieza en revisión, subiendo el archivo', function () {
    $piece = Piece::factory()->status(PieceStatus::InReview)->create();

    piecePage($piece)
        ->assertActionVisible('sendForApproval')
        ->callAction('sendForApproval', ['file' => UploadedFile::fake()->create('logo-v1.pdf', 200, 'application/pdf')])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $submission = PieceApprovalSubmission::query()->sole();

    expect($piece->refresh()->status)->toBe(PieceStatus::ClientApproval)
        ->and($submission->piece_id)->toBe($piece->id)
        ->and($submission->file_extension)->toBe('pdf')
        ->and($submission->submitted_by)->toBe($this->actor->id);

    Storage::disk()->assertExists($submission->file_path);
});

it('RF-29, RF-31: un archivo de otro formato o sin adjuntar se rechaza con el motivo y la pieza no cambia', function (?string $name, ?string $mime) {
    $piece = Piece::factory()->status(PieceStatus::InReview)->create();
    $file = $name === null ? null : UploadedFile::fake()->create($name, 100, $mime);

    piecePage($piece)
        ->callAction('sendForApproval', ['file' => $file])
        ->assertHasActionErrors(['file']);

    expect($piece->refresh()->status)->toBe(PieceStatus::InReview)
        ->and(PieceApprovalSubmission::query()->count())->toBe(0);
})->with([
    'gif' => ['animacion.gif', 'image/gif'],
    'zip' => ['entrega.zip', 'application/zip'],
    'sin archivo' => [null, null],
]);

it('RF-24, RF-25: marca como entregada la pieza aprobada, y una entregada ya no ofrece ningún cambio', function () {
    $piece = Piece::factory()->status(PieceStatus::Approved)->create();

    piecePage($piece)
        ->assertActionVisible('markDelivered')
        ->callAction('markDelivered')
        ->assertNotified();

    expect($piece->refresh()->status)->toBe(PieceStatus::Delivered);

    piecePage($piece)
        ->assertActionHidden('markDelivered')
        ->assertActionHidden('markInProduction')
        ->assertActionHidden('markInReview')
        ->assertActionHidden('sendForApproval')
        ->assertActionHidden('assignPiece')
        ->assertActionHidden('discardPiece');
});

it('RF-26, RF-27: descarta una pieza pendiente, que se conserva pero deja de mostrarse en el listado', function () {
    $piece = Piece::factory()->create();
    PieceHistory::factory()->for($piece)->create();

    piecePage($piece)
        ->assertActionVisible('discardPiece')
        ->callAction('discardPiece')
        ->assertNotified()
        ->assertRedirect(PieceResource::getUrl('index'));

    expect($piece->refresh()->trashed())->toBeTrue()
        ->and($piece->histories()->count())->toBe(1);

    Livewire::test(ListPieces::class)->assertCanNotSeeTableRecords([$piece]);
});

it('RF-26: solo se ofrece descartar una pieza pendiente', function (PieceStatus $status) {
    piecePage(Piece::factory()->status($status)->create())->assertActionHidden('discardPiece');
})->with([
    PieceStatus::InProduction,
    PieceStatus::InReview,
    PieceStatus::ClientApproval,
    PieceStatus::Approved,
]);

it('RF-30, RF-36: consulta el historial completo de envíos a aprobación, con archivo, resultado y motivo, y descarga cada archivo', function () {
    $piece = Piece::factory()->create();
    $rejected = PieceApprovalSubmission::factory()->for($piece)->rejected('Cambiar el color')->create(['submitted_at' => '2026-10-01 10:00:00', 'file_path' => 'piece-submissions/v1.pdf']);
    $pending = PieceApprovalSubmission::factory()->for($piece)->create(['submitted_at' => '2026-10-08 10:00:00']);
    PieceApprovalSubmission::factory()->create();
    Storage::disk()->put('piece-submissions/v1.pdf', 'contenido');

    Livewire::test(SubmissionsRelationManager::class, ['ownerRecord' => $piece, 'pageClass' => ViewPiece::class])
        ->assertCanSeeTableRecords([$rejected, $pending], inOrder: true)
        ->assertSee('Rechazada')
        ->assertSee('Cambiar el color')
        ->assertSee('Pendiente de respuesta')
        ->callTableAction('download', $rejected)
        ->assertFileDownloaded('v1.pdf');
});

it('RF-39: consulta el historial de la pieza en orden cronológico', function () {
    $piece = Piece::factory()->create();
    $late = PieceHistory::factory()->for($piece)->create(['created_at' => '2026-10-03 09:00:00']);
    $early = PieceHistory::factory()->for($piece)->create(['created_at' => '2026-10-01 09:00:00']);
    PieceHistory::factory()->create();

    Livewire::test(HistoriesRelationManager::class, ['ownerRecord' => $piece, 'pageClass' => ViewPiece::class])
        ->assertCanSeeTableRecords([$early, $late], inOrder: true);
});
