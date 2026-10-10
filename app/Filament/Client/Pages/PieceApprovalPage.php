<?php

namespace App\Filament\Client\Pages;

use App\Enums\PieceStatus;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The pieces of the client the signed-in account is linked to that are in its approval, approved
 * or delivered, and the answers that same account gave (RF-37, RF-45, RF-46). The client is never
 * taken from the request, only from the account (RF-48).
 */
class PieceApprovalPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'pieces';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function getNavigationLabel(): string
    {
        return __('pieces.portal.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pieces.portal.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
            Section::make(__('pieces.portal.responses'))->schema($this->responseComponents()),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Piece::query()
                ->visibleToClient(Auth::user())
                ->with(['budget', 'latestSubmission']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('pieces.fields.name')),
                TextColumn::make('budget.title')
                    ->label(__('pieces.fields.budget')),
                TextColumn::make('status')
                    ->label(__('pieces.fields.status'))
                    ->badge()
                    ->color(fn (PieceStatus $state): string => match ($state) {
                        PieceStatus::ClientApproval => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (PieceStatus $state): string => $state->label()),
                TextColumn::make('latestSubmission.submitted_at')
                    ->label(__('pieces.portal.submitted_at'))
                    ->dateTime('d/m/Y')
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                $this->downloadAction(),
            ])
            ->emptyStateHeading(__('pieces.portal.empty'));
    }

    /** Downloads the file the client is asked to look at: the piece's latest submission. */
    private function downloadAction(): Action
    {
        return Action::make('download')
            ->label(__('pieces.submissions.download'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (Piece $record): bool => $record->latestSubmission !== null)
            ->action(function (Piece $record): StreamedResponse {
                $submission = $record->latestSubmission;

                Gate::authorize('portal.pieces.download', $submission);

                return Storage::download(
                    $submission->file_path,
                    "pieza-{$record->id}-envio-{$submission->id}.{$submission->file_extension}",
                );
            });
    }

    /**
     * The submissions this account answered itself, rejected ones included, even if the piece
     * went back to production (RF-37).
     *
     * @return array<int, Component>
     */
    private function responseComponents(): array
    {
        $responses = PieceApprovalSubmission::query()
            ->resolvedBy(Auth::user())
            ->with('piece')
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->get();

        if ($responses->isEmpty()) {
            return [TextEntry::make('no_responses')->hiddenLabel()->state(__('pieces.portal.no_responses'))];
        }

        return $responses->map(fn (PieceApprovalSubmission $response): Grid => Grid::make(['default' => 1, 'md' => 4])->schema([
            TextEntry::make("response_{$response->id}_piece")
                ->label(__('pieces.fields.name'))
                ->state($response->piece->name),
            TextEntry::make("response_{$response->id}_resolution")
                ->label(__('pieces.submissions.resolution'))
                ->badge()
                ->color($response->resolution->value === 'approved' ? 'success' : 'danger')
                ->state($response->resolution->label()),
            TextEntry::make("response_{$response->id}_date")
                ->label(__('pieces.submissions.resolved_at'))
                ->state($response->resolved_at->format('d/m/Y')),
            TextEntry::make("response_{$response->id}_reason")
                ->label(__('pieces.submissions.rejection_reason'))
                ->state($response->rejection_reason)
                ->placeholder('—'),
        ]))->all();
    }
}
