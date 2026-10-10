<?php

namespace App\Filament\Client\Pages;

use App\Actions\ApprovePieceAction;
use App\Actions\RejectPieceAction;
use App\Enums\PieceStatus;
use App\Exceptions\InvalidPieceTransitionException;
use App\Filament\Support\FormValidation;
use App\Models\Piece;
use App\Models\PieceApprovalSubmission;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
                $this->approveAction(),
                $this->rejectAction(),
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

    /** Approves the pending submission; who may and when is decided by the Action (RF-32, RF-33). */
    private function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('pieces.portal.actions.approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Piece $record): bool => $this->isAwaitingAnswer($record))
            ->action(function (Piece $record): void {
                $this->answer($record, fn (PieceApprovalSubmission $submission) => app(ApprovePieceAction::class)->handle($submission, Auth::user()), __('pieces.portal.notifications.approved'));
            });
    }

    /** Rejects the pending submission, with an optional reason (RF-34, RF-35). */
    private function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('pieces.portal.actions.reject'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Piece $record): bool => $this->isAwaitingAnswer($record))
            ->schema([
                Textarea::make('rejection_reason')
                    ->label(__('pieces.portal.rejection_reason'))
                    ->helperText(__('pieces.portal.rejection_reason_help')),
            ])
            ->action(function (array $data, Action $action, Piece $record): void {
                try {
                    $this->answer($record, fn (PieceApprovalSubmission $submission) => app(RejectPieceAction::class)->handle($submission, Auth::user(), $data['rejection_reason'] ?? null), __('pieces.portal.notifications.rejected'));
                } catch (ValidationException $exception) {
                    FormValidation::forAction($exception, $action);
                }
            });
    }

    /** Only a piece in the client's approval with an unanswered submission offers the buttons. */
    private function isAwaitingAnswer(Piece $piece): bool
    {
        return $piece->status === PieceStatus::ClientApproval && $piece->latestSubmission?->isPending() === true;
    }

    /**
     * Gives the answer on the current submission of a piece and tells how it went. The Action
     * authorizes it again, so a page that went stale gets "insufficient permission".
     *
     * @param  Closure(PieceApprovalSubmission): mixed  $answer
     *
     * @throws ValidationException
     */
    private function answer(Piece $piece, Closure $answer, string $successMessage): void
    {
        try {
            $answer($piece->latestSubmission()->firstOrFail());
        } catch (AuthorizationException|InvalidPieceTransitionException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($successMessage)->send();
    }

    /**
     * The submissions this account answered itself, rejected ones included, even if the piece
     * went back to production (RF-37). The state is a closure so it is read when the page is
     * rendered, after any answer given in the same request.
     *
     * @return array<int, Component>
     */
    private function responseComponents(): array
    {
        return [
            TextEntry::make('no_responses')
                ->hiddenLabel()
                ->state(__('pieces.portal.no_responses'))
                ->visible(fn (): bool => $this->responses()->isEmpty()),
            RepeatableEntry::make('responses')
                ->hiddenLabel()
                ->visible(fn (): bool => $this->responses()->isNotEmpty())
                ->state(fn (): array => $this->responses()->map(fn (PieceApprovalSubmission $response): array => [
                    'piece' => $response->piece->name,
                    'resolution' => $response->resolution->label(),
                    'resolved_at' => $response->resolved_at->format('d/m/Y'),
                    'rejection_reason' => $response->rejection_reason,
                ])->all())
                ->schema([
                    TextEntry::make('piece')->label(__('pieces.fields.name')),
                    TextEntry::make('resolution')->label(__('pieces.submissions.resolution'))->badge(),
                    TextEntry::make('resolved_at')->label(__('pieces.submissions.resolved_at')),
                    TextEntry::make('rejection_reason')->label(__('pieces.submissions.rejection_reason'))->placeholder('—'),
                ])
                ->columns(['default' => 1, 'md' => 4]),
        ];
    }

    /**
     * @return Collection<int, PieceApprovalSubmission>
     */
    private function responses(): Collection
    {
        return PieceApprovalSubmission::query()
            ->resolvedBy(Auth::user())
            ->with('piece')
            ->orderByDesc('resolved_at')
            ->orderByDesc('id')
            ->get();
    }
}
