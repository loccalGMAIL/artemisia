<?php

namespace App\Filament\Staff\Resources\Contracts;

use App\Actions\CancelContractAction;
use App\Actions\CreateContractAction;
use App\Actions\ProposeContractDefaultsAction;
use App\Actions\ReactivateContractAction;
use App\Actions\UpdateContractAmountAction;
use App\Actions\UpdateContractDueDayAction;
use App\Enums\ContractStatus;
use App\Exceptions\BudgetAlreadyHasContractException;
use App\Exceptions\BudgetNotEligibleForContractException;
use App\Exceptions\ContractTermElapsedException;
use App\Exceptions\InvalidContractTransitionException;
use App\Filament\Support\FormValidation;
use App\Models\Budget;
use App\Models\Contract;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Actions on contracts. Each one only collects the data and triggers an Action; whether the
 * budget can originate a contract, which state allows each change and what a valid contract is
 * are decided by the Actions
 * (constitution, principle 3).
 */
final class ContractActions
{
    /**
     * Creates the contract of the budget being shown. The form comes filled in with the bases of
     * the client's previous contract and can be changed before confirming (RF-13 to RF-16).
     */
    public static function create(): Action
    {
        return Action::make('createContract')
            ->label(__('contracts.actions.create'))
            ->icon('heroicon-o-document-check')
            ->color('primary')
            ->authorize(fn (): bool => Gate::allows('create', Contract::class))
            ->hidden(fn (Budget $record): bool => $record->trashed())
            ->modalHeading(__('contracts.actions.create'))
            ->mountUsing(function (Schema $schema, Budget $record): void {
                $schema->fill(app(ProposeContractDefaultsAction::class)->handle($record->client));
            })
            ->schema([
                DatePicker::make('start_date')
                    ->label(__('contracts.fields.start_date'))
                    ->native(false),
                TextInput::make('due_day')
                    ->label(__('contracts.fields.due_day'))
                    ->helperText(__('contracts.actions.due_day_help'))
                    ->numeric(),
                Toggle::make('indefinite')
                    ->label(__('contracts.fields.indefinite'))
                    ->live(),
                TextInput::make('term_months')
                    ->label(__('contracts.fields.term_months'))
                    ->numeric()
                    ->visible(fn (Get $get): bool => ! $get('indefinite')),
            ])
            ->action(function (array $data, Action $action, Budget $record): void {
                try {
                    app(CreateContractAction::class)->handle($record, $data, Auth::user());
                } catch (BudgetNotEligibleForContractException|BudgetAlreadyHasContractException|ContractTermElapsedException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                } catch (ValidationException $exception) {
                    FormValidation::forAction($exception, $action);
                }

                Notification::make()->success()->title(__('contracts.notifications.created'))->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancelContract')
            ->label(__('contracts.actions.cancel'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('contracts.actions.cancel'))
            ->modalDescription(__('contracts.actions.cancel_help'))
            ->authorize(fn (Contract $record): bool => Gate::allows('cancel', $record))
            ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Active)
            ->action(function (Action $action, Contract $record): void {
                self::attempt($action, fn () => app(CancelContractAction::class)->handle($record, Auth::user()));

                Notification::make()->success()->title(__('contracts.notifications.cancelled'))->send();
            });
    }

    public static function reactivate(): Action
    {
        return Action::make('reactivateContract')
            ->label(__('contracts.actions.reactivate'))
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('contracts.actions.reactivate'))
            ->modalDescription(__('contracts.actions.reactivate_help'))
            ->authorize(fn (Contract $record): bool => Gate::allows('reactivate', $record))
            ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Cancelled)
            ->action(function (Action $action, Contract $record): void {
                self::attempt($action, fn () => app(ReactivateContractAction::class)->handle($record, Auth::user()));

                Notification::make()->success()->title(__('contracts.notifications.reactivated'))->send();
            });
    }

    public static function updateAmount(): Action
    {
        return Action::make('updateAmount')
            ->label(__('contracts.actions.update_amount'))
            ->icon('heroicon-o-currency-dollar')
            ->color('gray')
            ->authorize(fn (Contract $record): bool => Gate::allows('update', $record))
            ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Active)
            ->modalHeading(__('contracts.actions.update_amount'))
            ->modalDescription(__('contracts.actions.forward_only_help'))
            ->mountUsing(function (Schema $schema, Contract $record): void {
                $schema->fill(['monthly_amount' => $record->monthly_amount]);
            })
            ->schema([
                TextInput::make('monthly_amount')
                    ->label(__('contracts.fields.monthly_amount'))
                    ->numeric()
                    ->inputMode('decimal'),
            ])
            ->action(function (array $data, Action $action, Contract $record): void {
                self::attempt($action, fn () => app(UpdateContractAmountAction::class)->handle($record, $data['monthly_amount'] ?? null, Auth::user()));

                Notification::make()->success()->title(__('contracts.notifications.amount_updated'))->send();
            });
    }

    public static function updateDueDay(): Action
    {
        return Action::make('updateDueDay')
            ->label(__('contracts.actions.update_due_day'))
            ->icon('heroicon-o-calendar-days')
            ->color('gray')
            ->authorize(fn (Contract $record): bool => Gate::allows('update', $record))
            ->visible(fn (Contract $record): bool => $record->status === ContractStatus::Active)
            ->modalHeading(__('contracts.actions.update_due_day'))
            ->modalDescription(__('contracts.actions.forward_only_help'))
            ->mountUsing(function (Schema $schema, Contract $record): void {
                $schema->fill(['due_day' => $record->due_day]);
            })
            ->schema([
                TextInput::make('due_day')
                    ->label(__('contracts.fields.due_day'))
                    ->helperText(__('contracts.actions.due_day_help'))
                    ->numeric(),
            ])
            ->action(function (array $data, Action $action, Contract $record): void {
                self::attempt($action, fn () => app(UpdateContractDueDayAction::class)->handle($record, $data['due_day'] ?? null, Auth::user()));

                Notification::make()->success()->title(__('contracts.notifications.due_day_updated'))->send();
            });
    }

    /**
     * Runs the Action and reports what it refused: a state that does not allow the change or a
     * term that has gone by goes to a notification with its reason and keeps the modal open, a
     * field error goes to the form.
     */
    private static function attempt(Action $action, callable $run): void
    {
        try {
            $run();
        } catch (InvalidContractTransitionException|ContractTermElapsedException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            $action->halt();
        } catch (ValidationException $exception) {
            FormValidation::forAction($exception, $action);
        }
    }
}
