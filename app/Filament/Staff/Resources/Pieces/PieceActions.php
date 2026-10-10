<?php

namespace App\Filament\Staff\Resources\Pieces;

use App\Actions\CreateLoosePieceAction;
use App\Actions\CreatePiecesFromProposalAction;
use App\Actions\ProposePiecesFromBudgetAction;
use App\Exceptions\BudgetNotAcceptedException;
use App\Filament\Support\FormValidation;
use App\Models\Budget;
use App\Models\WorkCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Actions that create pieces: the proposal of an accepted budget and the loose piece. Each
 * one only collects the data and triggers an Action; whether the budget may originate pieces
 * and what a valid piece is are decided by the Actions (constitution, principle 3).
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
            ->hidden(fn (Budget $record): bool => $record->trashed())
            ->modalHeading(__('pieces.actions.generate'))
            ->mountUsing(function (Schema $schema, Action $action, Budget $record): void {
                try {
                    $lines = app(ProposePiecesFromBudgetAction::class)->handle($record);
                } catch (BudgetNotAcceptedException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    $action->halt();
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

    private static function budgetLabel(Budget $budget): string
    {
        return "#{$budget->id} · {$budget->title} — {$budget->client->display_name}";
    }
}
