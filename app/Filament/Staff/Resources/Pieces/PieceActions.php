<?php

namespace App\Filament\Staff\Resources\Pieces;

use App\Actions\AssignPieceOwnerAction;
use App\Actions\CreateLoosePieceAction;
use App\Actions\CreatePiecesFromProposalAction;
use App\Actions\DiscardPieceAction;
use App\Actions\MarkPieceDeliveredAction;
use App\Actions\MarkPieceInProductionAction;
use App\Actions\MarkPieceInReviewAction;
use App\Actions\ProposePiecesFromBudgetAction;
use App\Actions\SendPieceForClientApprovalAction;
use App\Actions\SetPieceDueDateAction;
use App\Enums\PieceStatus;
use App\Exceptions\BudgetNotAcceptedException;
use App\Exceptions\InvalidPieceAssigneeException;
use App\Exceptions\InvalidPieceTransitionException;
use App\Exceptions\PieceDeliveredException;
use App\Exceptions\PieceNotDiscardableException;
use App\Filament\Support\FormValidation;
use App\Models\Budget;
use App\Models\Piece;
use App\Models\User;
use App\Models\WorkCategory;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Actions on pieces: creating them from an accepted budget or as loose pieces, and working on
 * one (delegating, dating, moving it through production, discarding). Each one only collects
 * the data and triggers an Action; what is valid and what fits the piece's state is decided by
 * the Actions (constitution, principle 3).
 */
final class PieceActions
{
    /** The proposal of an accepted budget, editable before confirming it (RF-1, RF-2, RF-4). */
    public static function generate(): Action
    {
        return Action::make('generatePieces')
            ->label(__('pieces.actions.generate'))
            ->icon('heroicon-o-squares-plus')
            ->color('primary')
            ->authorize(fn (): bool => Gate::allows('create', Piece::class))
            ->hidden(fn (Budget $record): bool => $record->trashed())
            ->modalHeading(__('pieces.actions.generate'))
            ->mountUsing(function (Schema $schema, Action $action, Budget $record): void {
                try {
                    $lines = app(ProposePiecesFromBudgetAction::class)->handle($record);
                } catch (BudgetNotAcceptedException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    $action->halt();

                    return;
                }

                $schema->fill(['lines' => $lines]);
            })
            ->schema([
                Repeater::make('lines')
                    ->label(__('pieces.fields.proposal'))
                    ->helperText(__('pieces.actions.proposal_help'))
                    ->addable(false)
                    ->cloneable()
                    ->deletable()
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? "{$state['name']} × ".($state['quantity'] ?? 1) : null)
                    ->schema([
                        Hidden::make('budget_item_id'),
                        Hidden::make('quantity'),
                        TextInput::make('name')
                            ->label(__('pieces.fields.name'))
                            ->maxLength(150),
                        Select::make('work_category_id')
                            ->label(__('pieces.fields.category'))
                            ->options(fn (): array => WorkCategory::query()->orderBy('name')->pluck('name', 'id')->all()),
                        Textarea::make('description')
                            ->label(__('pieces.fields.description')),
                    ]),
            ])
            ->action(function (array $data, Action $action, Budget $record): void {
                try {
                    $pieces = app(CreatePiecesFromProposalAction::class)->handle($record, $data['lines'] ?? [], Auth::user());
                } catch (BudgetNotAcceptedException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                } catch (ValidationException $exception) {
                    FormValidation::forAction($exception, $action);
                }

                Notification::make()->success()->title(trans_choice('pieces.notifications.generated', $pieces->count(), ['count' => $pieces->count()]))->send();
            });
    }

    /** A piece with no source item, tied straight to an accepted budget (RF-5, RF-10). */
    public static function createLoose(): Action
    {
        return Action::make('createLoosePiece')
            ->label(__('pieces.actions.create_loose'))
            ->icon('heroicon-o-plus')
            ->authorize(fn (): bool => Gate::allows('create', Piece::class))
            ->modalHeading(__('pieces.actions.create_loose'))
            ->schema([
                Select::make('budget_id')
                    ->label(__('pieces.fields.budget'))
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Budget::query()
                        ->acceptedForPieces()
                        ->with('client')
                        ->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('id', ltrim($search, '#')))
                        ->orderByDesc('id')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Budget $budget): array => [$budget->id => self::budgetLabel($budget)])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => ($budget = Budget::withTrashed()->with('client')->find($value)) ? self::budgetLabel($budget) : null),
                TextInput::make('name')
                    ->label(__('pieces.fields.name'))
                    ->maxLength(150),
                Textarea::make('description')
                    ->label(__('pieces.fields.description')),
                Select::make('work_category_id')
                    ->label(__('pieces.fields.category'))
                    ->options(fn (): array => WorkCategory::query()->orderBy('name')->pluck('name', 'id')->all()),
                DatePicker::make('due_date')
                    ->label(__('pieces.fields.due_date'))
                    ->native(false),
            ])
            ->action(function (array $data, Action $action): void {
                $budget = Budget::query()->findOrFail($data['budget_id']);

                try {
                    app(CreateLoosePieceAction::class)->handle($budget, $data, Auth::user());
                } catch (BudgetNotAcceptedException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                } catch (ValidationException $exception) {
                    FormValidation::forAction($exception, $action);
                }

                Notification::make()->success()->title(__('pieces.notifications.created'))->send();
            });
    }

    /**
     * @return array<int, Action>
     */
    public static function forRecord(): array
    {
        return [
            self::assign(),
            self::setDueDate(),
            self::markInProduction(),
            self::markInReview(),
            self::sendForApproval(),
            self::markDelivered(),
            self::discard(),
        ];
    }

    /** Delegates or reassigns the piece to an admin or staff account (RF-11, RF-14). */
    public static function assign(): Action
    {
        return Action::make('assignPiece')
            ->label(__('pieces.actions.assign'))
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->authorize('update')
            ->hidden(fn (Piece $record): bool => $record->trashed() || $record->status === PieceStatus::Delivered)
            ->fillForm(fn (Piece $record): array => ['assignee_id' => $record->assignee_id])
            ->schema([
                Select::make('assignee_id')
                    ->label(__('pieces.fields.assignee'))
                    ->options(fn (): array => User::query()->assignableToPieces()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
            ])
            ->action(function (array $data, Piece $record): void {
                $assignee = User::query()->find($data['assignee_id'] ?? null);

                if ($assignee === null) {
                    Notification::make()->danger()->title(__('pieces.validation.assignee_invalid'))->send();

                    return;
                }

                self::report(
                    fn () => app(AssignPieceOwnerAction::class)->handle($record, $assignee, Auth::user()),
                    __('pieces.notifications.assigned'),
                );
            });
    }

    /** Loads, changes or clears the committed delivery date (RF-15, RF-16). */
    public static function setDueDate(): Action
    {
        return Action::make('setDueDate')
            ->label(__('pieces.actions.set_due_date'))
            ->icon('heroicon-o-calendar-days')
            ->color('gray')
            ->authorize('update')
            ->hidden(fn (Piece $record): bool => $record->trashed())
            ->fillForm(fn (Piece $record): array => ['due_date' => $record->due_date?->toDateString()])
            ->schema([
                DatePicker::make('due_date')
                    ->label(__('pieces.fields.due_date'))
                    ->native(false),
            ])
            ->action(function (array $data, Piece $record): void {
                $dueDate = filled($data['due_date'] ?? null) ? CarbonImmutable::parse($data['due_date']) : null;

                self::report(
                    fn () => app(SetPieceDueDateAction::class)->handle($record, $dueDate, Auth::user()),
                    __('pieces.notifications.due_date_saved'),
                );
            });
    }

    public static function markInProduction(): Action
    {
        return self::stateAction('markInProduction', 'heroicon-o-play', 'info', PieceStatus::Pending, fn (Piece $record) => app(MarkPieceInProductionAction::class)->handle($record, Auth::user()));
    }

    public static function markInReview(): Action
    {
        return self::stateAction('markInReview', 'heroicon-o-eye', 'warning', PieceStatus::InProduction, fn (Piece $record) => app(MarkPieceInReviewAction::class)->handle($record, Auth::user()));
    }

    public static function markDelivered(): Action
    {
        return self::stateAction('markDelivered', 'heroicon-o-truck', 'success', PieceStatus::Approved, fn (Piece $record) => app(MarkPieceDeliveredAction::class)->handle($record, Auth::user()));
    }

    /** Sends a piece in review to the client with its file (RF-22, RF-23, RF-28, RF-31). */
    public static function sendForApproval(): Action
    {
        return Action::make('sendForApproval')
            ->label(__('pieces.actions.sendForApproval'))
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->authorize('update')
            ->hidden(fn (Piece $record): bool => $record->trashed() || $record->status !== PieceStatus::InReview)
            ->schema([
                FileUpload::make('file')
                    ->label(__('pieces.fields.file'))
                    ->helperText(__('pieces.actions.file_help'))
                    // The Action receives the uploaded file itself and validates format and size.
                    ->storeFiles(false),
            ])
            ->action(function (array $data, Action $action, Piece $record): void {
                try {
                    app(SendPieceForClientApprovalAction::class)->handle($record, $data['file'] ?? null, Auth::user());
                } catch (ValidationException $exception) {
                    FormValidation::forAction($exception, $action);
                } catch (InvalidPieceTransitionException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('pieces.notifications.sendForApproval'))->send();
            });
    }

    /** Discards a pending piece; it is kept with its history (RF-26, RF-27). */
    public static function discard(): Action
    {
        return Action::make('discardPiece')
            ->label(__('pieces.actions.discard'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->authorize('discard')
            ->hidden(fn (Piece $record): bool => $record->trashed() || $record->status !== PieceStatus::Pending)
            ->action(function (Piece $record, $livewire): void {
                try {
                    app(DiscardPieceAction::class)->handle($record, Auth::user());
                } catch (PieceNotDiscardableException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('pieces.notifications.discarded'))->send();

                $livewire->redirect(PieceResource::getUrl('index'));
            });
    }

    /**
     * @param  Closure(Piece): mixed  $handler
     */
    private static function stateAction(string $name, string $icon, string $color, PieceStatus $visibleFrom, Closure $handler): Action
    {
        return Action::make($name)
            ->label(__("pieces.actions.{$name}"))
            ->icon($icon)
            ->color($color)
            ->authorize('update')
            ->hidden(fn (Piece $record): bool => $record->trashed() || $record->status !== $visibleFrom)
            ->action(function (Piece $record) use ($handler, $name): void {
                self::report(fn () => $handler($record), __("pieces.notifications.{$name}"));
            });
    }

    /** Runs a change and tells the user how it went. */
    private static function report(Closure $change, string $successMessage): void
    {
        try {
            $change();
        } catch (InvalidPieceTransitionException|InvalidPieceAssigneeException|PieceDeliveredException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($successMessage)->send();
    }

    private static function budgetLabel(Budget $budget): string
    {
        return "#{$budget->id} · {$budget->title} — {$budget->client->display_name}";
    }
}
