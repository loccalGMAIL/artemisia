<?php

namespace App\Filament\Staff\Resources\Pieces\RelationManagers;

use App\Enums\PieceApprovalResolution;
use App\Models\PieceApprovalSubmission;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only record of every submission to the client with its file, result and reason, oldest
 * first (RF-30, RF-36).
 */
class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pieces.relations.submissions');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['submitter', 'resolver']))
            ->columns([
                TextColumn::make('submitted_at')->label(__('pieces.submissions.submitted_at'))->dateTime('d/m/Y H:i'),
                TextColumn::make('submitter.name')->label(__('pieces.submissions.submitted_by')),
                TextColumn::make('file_extension')
                    ->label(__('pieces.submissions.file'))
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                TextColumn::make('resolution')
                    ->label(__('pieces.submissions.resolution'))
                    ->badge()
                    ->color(fn (PieceApprovalSubmission $record): string => match ($record->resolution) {
                        PieceApprovalResolution::Approved => 'success',
                        PieceApprovalResolution::Rejected => 'danger',
                        null => 'gray',
                    })
                    // A pending submission has no resolution to format, so the state is given.
                    ->state(fn (PieceApprovalSubmission $record): string => $record->resolution?->label() ?? __('pieces.submissions.pending')),
                TextColumn::make('resolved_at')->label(__('pieces.submissions.resolved_at'))->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('rejection_reason')->label(__('pieces.submissions.rejection_reason'))->placeholder('—'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('pieces.submissions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (PieceApprovalSubmission $record): StreamedResponse => Storage::download(
                        $record->file_path,
                        "pieza-{$record->piece_id}-envio-{$record->id}.{$record->file_extension}",
                    )),
            ])
            ->paginated(false);
    }
}
