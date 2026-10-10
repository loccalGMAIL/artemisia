<?php

namespace App\Filament\Staff\Resources\ContractPeriods;

use App\Actions\AddPeriodDiscountAction;
use App\Actions\AddPeriodExtraAction;
use App\Actions\RemovePeriodConceptAction;
use App\Actions\SetPeriodInvoiceAction;
use App\Exceptions\PeriodLimitExceededException;
use App\Exceptions\PeriodTotalException;
use App\Filament\Support\FormValidation;
use App\Models\ContractPeriod;
use App\Models\PeriodConcept;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Actions on a period: its extras, its discounts and its invoice reference. Each one only
 * collects the data and triggers an Action; the totals, the limits and what a valid concept is
 * are decided by the Actions (constitution, principle 3).
 */
final class PeriodActions
{
    public static function addExtra(): Action
    {
        return Action::make('addExtra')
            ->label(__('contracts.actions.add_extra'))
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->authorize(fn (ContractPeriod $record): bool => Gate::allows('update', $record))
            ->modalHeading(__('contracts.actions.add_extra'))
            ->schema(self::conceptForm(__('contracts.actions.extra_description')))
            ->action(function (array $data, Action $action, ContractPeriod $record): void {
                self::attempt($action, fn () => app(AddPeriodExtraAction::class)->handle($record, $data, Auth::user()));

                Notification::make()->success()->title(__('contracts.notifications.extra_added'))->send();
            });
    }

    public static function addDiscount(): Action
    {
        return Action::make('addDiscount')
            ->label(__('contracts.actions.add_discount'))
            ->icon('heroicon-o-minus-circle')
            ->color('gray')
            ->authorize(fn (ContractPeriod $record): bool => Gate::allows('update', $record))
            ->modalHeading(__('contracts.actions.add_discount'))
            ->schema(self::conceptForm(__('contracts.actions.discount_justification')))
            ->action(function (array $data, Action $action, ContractPeriod $record): void {
                self::attempt($action, fn () => app(AddPeriodDiscountAction::class)->handle($record, $data, Auth::user()));

                Notification::make()->success()->title(__('contracts.notifications.discount_added'))->send();
            });
    }

    public static function setInvoice(): Action
    {
        return Action::make('setInvoice')
            ->label(__('contracts.actions.set_invoice'))
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->authorize(fn (ContractPeriod $record): bool => Gate::allows('update', $record))
            ->modalHeading(__('contracts.actions.set_invoice'))
            ->modalDescription(__('contracts.actions.invoice_help'))
            ->mountUsing(function (Schema $schema, ContractPeriod $record): void {
                $schema->fill([
                    'invoice_number' => $record->invoice_number,
                    'invoice_date' => $record->invoice_date?->toDateString(),
                ]);
            })
            ->schema([
                TextInput::make('invoice_number')->label(__('contracts.periods.invoice_number')),
                DatePicker::make('invoice_date')->label(__('contracts.periods.invoice_date'))->native(false),
            ])
            ->action(function (array $data, Action $action, ContractPeriod $record): void {
                self::attempt($action, fn () => app(SetPeriodInvoiceAction::class)->handle(
                    $record,
                    $data['invoice_number'] ?? null,
                    $data['invoice_date'] ?? null,
                    Auth::user(),
                ));

                Notification::make()->success()->title(__('contracts.notifications.invoice_saved'))->send();
            });
    }

    public static function removeConcept(): Action
    {
        return Action::make('removeConcept')
            ->label(__('contracts.actions.remove_concept'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('contracts.actions.remove_concept'))
            ->modalDescription(__('contracts.actions.remove_concept_help'))
            ->authorize(fn (PeriodConcept $record): bool => Gate::allows('update', $record->contractPeriod))
            ->action(function (Action $action, PeriodConcept $record): void {
                self::attempt($action, fn () => app(RemovePeriodConceptAction::class)->handle($record, Auth::user()));

                Notification::make()->success()->title(__('contracts.notifications.concept_removed'))->send();
            });
    }

    /**
     * @return list<TextInput>
     */
    private static function conceptForm(string $descriptionLabel): array
    {
        return [
            TextInput::make('description')->label($descriptionLabel),
            TextInput::make('amount')
                ->label(__('contracts.concepts.amount'))
                ->numeric()
                ->inputMode('decimal'),
        ];
    }

    /**
     * Runs the Action and reports what it refused: a rejected period goes to a notification with
     * its reason and keeps the modal open, a field error goes to the form (RF-43, RF-46, RF-50,
     * RF-51).
     */
    private static function attempt(Action $action, callable $run): void
    {
        try {
            $run();
        } catch (PeriodLimitExceededException|PeriodTotalException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            $action->halt();
        } catch (ValidationException $exception) {
            FormValidation::forAction($exception, $action);
        }
    }
}
