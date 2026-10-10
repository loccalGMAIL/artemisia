<?php

namespace App\Filament\Staff\Resources\Contracts;

use App\Actions\CreateContractAction;
use App\Actions\ProposeContractDefaultsAction;
use App\Exceptions\BudgetAlreadyHasContractException;
use App\Exceptions\BudgetNotEligibleForContractException;
use App\Exceptions\ContractTermElapsedException;
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
 * budget can originate a contract and what a valid contract is are decided by the Actions
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
}
