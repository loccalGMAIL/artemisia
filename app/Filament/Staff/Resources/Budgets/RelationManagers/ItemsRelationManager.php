<?php

namespace App\Filament\Staff\Resources\Budgets\RelationManagers;

use App\Actions\AddBudgetItemAction;
use App\Actions\RefreshBudgetItemPriceAction;
use App\Actions\RemoveBudgetItemAction;
use App\Actions\UpdateBudgetItemDescriptionAction;
use App\Actions\UpdateBudgetItemQuantityAction;
use App\Exceptions\BudgetNotEditableException;
use App\Filament\Support\FormValidation;
use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Service;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Items of a budget. Every change goes through the item Actions, which copy prices from the
 * catalog, recalculate the totals and write the history; nothing is computed here.
 *
 * @method Budget getOwnerRecord()
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('budgets.relations.items');
    }

    /** Items can change only while the budget is draft or sent (RF-48). */
    public function isReadOnly(): bool
    {
        $budget = $this->getOwnerRecord();

        return $budget->trashed() || ! $budget->status->isEditable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('budgets.items.name')),
                TextColumn::make('description')->label(__('budgets.items.description'))->limit(60)->placeholder('—'),
                TextColumn::make('unit_price')
                    ->label(__('budgets.items.unit_price'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextColumn::make('quantity')->label(__('budgets.items.quantity')),
                TextColumn::make('amount')
                    ->label(__('budgets.items.amount'))
                    ->state(fn (BudgetItem $record): string => Money::display($record->amount)),
            ])
            ->paginated(false)
            ->headerActions([
                Action::make('addItem')
                    ->label(__('budgets.actions.add_item'))
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->schema([
                        Select::make('service_id')
                            ->label(__('budgets.items.service'))
                            ->options(fn (): array => Service::active()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Service $service): array => [$service->id => "{$service->name} — ".Money::display($service->list_price)])
                                ->all()),
                        TextInput::make('quantity')
                            ->label(__('budgets.items.quantity'))
                            ->default(1),
                        Textarea::make('description')
                            ->label(__('budgets.items.description')),
                    ])
                    ->action(function (array $data, Action $action): void {
                        $service = Service::query()->find($data['service_id'] ?? null);

                        if ($service === null) {
                            throw ValidationException::withMessages([
                                FormValidation::actionPath($action->getNestingIndex()).'.service_id' => __('budgets.validation.service_required'),
                            ]);
                        }

                        $this->perform($action, fn () => app(AddBudgetItemAction::class)->handle(
                            $this->getOwnerRecord(),
                            $service,
                            $data['quantity'] ?? null,
                            $data['description'] ?? null,
                            Auth::user(),
                        ));

                        Notification::make()->success()->title(__('budgets.notifications.item_added'))->send();
                    }),
            ])
            ->recordActions([
                Action::make('editItem')
                    ->label(__('budgets.actions.edit_item'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->fillForm(fn (BudgetItem $record): array => $record->only(['quantity', 'description']))
                    ->schema([
                        TextInput::make('quantity')->label(__('budgets.items.quantity')),
                        Textarea::make('description')->label(__('budgets.items.description')),
                    ])
                    ->action(function (BudgetItem $record, array $data, Action $action): void {
                        $this->perform($action, function () use ($record, $data): void {
                            app(UpdateBudgetItemQuantityAction::class)->handle($record, $data['quantity'] ?? null, Auth::user());
                            app(UpdateBudgetItemDescriptionAction::class)->handle($record, $data['description'] ?? null, Auth::user());
                        });

                        Notification::make()->success()->title(__('budgets.notifications.item_saved'))->send();
                    }),
                Action::make('refreshPrice')
                    ->label(__('budgets.actions.refresh_price'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->action(function (BudgetItem $record, Action $action): void {
                        $this->perform($action, fn () => app(RefreshBudgetItemPriceAction::class)->handle($record, Auth::user()));

                        Notification::make()->success()->title(__('budgets.notifications.item_saved'))->send();
                    }),
                Action::make('removeItem')
                    ->label(__('budgets.actions.remove_item'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => ! $this->isReadOnly())
                    ->authorize(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->action(function (BudgetItem $record, Action $action): void {
                        $this->perform($action, fn () => app(RemoveBudgetItemAction::class)->handle($record, Auth::user()));

                        Notification::make()->success()->title(__('budgets.notifications.item_removed'))->send();
                    }),
            ]);
    }

    /** Runs an item Action, sending its validation errors to the modal form. */
    private function perform(Action $action, callable $operation): void
    {
        try {
            $operation();
        } catch (ValidationException $exception) {
            FormValidation::forAction($exception, $action);
        } catch (BudgetNotEditableException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            $action->halt();
        }
    }
}
