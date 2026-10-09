<?php

namespace App\Filament\Staff\Resources\Budgets;

use App\Actions\AcceptBudgetAction;
use App\Actions\DiscardBudgetAction;
use App\Actions\RejectBudgetAction;
use App\Actions\RevertBudgetToSentAction;
use App\Actions\SendBudgetAction;
use App\Actions\SetBudgetDiscountAction;
use App\Enums\BudgetDiscountType;
use App\Enums\BudgetStatus;
use App\Exceptions\BudgetNotDiscardableException;
use App\Exceptions\BudgetNotEditableException;
use App\Exceptions\InvalidBudgetTransitionException;
use App\Filament\Support\FormValidation;
use App\Models\Budget;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Header actions of a budget: state changes, discount and discard. Each one only triggers an
 * Action and reports its outcome; who may use it comes from BudgetPolicy and what fits the
 * budget's state is decided by the Actions.
 */
final class BudgetActions
{
    /**
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [self::setDiscount(), self::send(), self::accept(), self::reject(), self::revert(), self::discard()];
    }

    public static function setDiscount(): Action
    {
        return Action::make('setDiscount')
            ->label(__('budgets.actions.set_discount'))
            ->icon('heroicon-o-receipt-percent')
            ->color('gray')
            ->authorize('update')
            ->hidden(fn (Budget $record): bool => $record->trashed() || ! $record->status->isEditable())
            ->fillForm(fn (Budget $record): array => [
                'discount_type' => $record->discount_type?->value,
                'discount_value' => $record->discount_value,
            ])
            ->schema([
                Select::make('discount_type')
                    ->label(__('budgets.fields.discount_type'))
                    ->placeholder(__('budgets.actions.no_discount'))
                    ->options(collect(BudgetDiscountType::cases())->mapWithKeys(fn (BudgetDiscountType $type): array => [$type->value => $type->label()])->all()),
                TextInput::make('discount_value')
                    ->label(__('budgets.fields.discount_value')),
            ])
            ->action(function (array $data, Action $action, Budget $record): void {
                try {
                    app(SetBudgetDiscountAction::class)->handle($record, $data['discount_type'] ?? null, $data['discount_value'] ?? null, Auth::user());
                } catch (ValidationException $exception) {
                    FormValidation::forAction($exception, $action);
                } catch (BudgetNotEditableException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('budgets.notifications.discount_saved'))->send();
            });
    }

    public static function send(): Action
    {
        return self::stateAction('send', 'heroicon-o-paper-airplane', 'primary', BudgetStatus::Draft, 'sent', fn (Budget $record) => app(SendBudgetAction::class)->handle($record, Auth::user()));
    }

    public static function accept(): Action
    {
        return self::stateAction('accept', 'heroicon-o-check-circle', 'success', BudgetStatus::Sent, 'accepted', fn (Budget $record) => app(AcceptBudgetAction::class)->handle($record, Auth::user()));
    }

    public static function reject(): Action
    {
        return self::stateAction(
            'reject',
            'heroicon-o-x-circle',
            'danger',
            BudgetStatus::Sent,
            'rejected',
            fn (Budget $record, array $data) => app(RejectBudgetAction::class)->handle($record, $data['rejection_reason'] ?? null, Auth::user()),
        )->schema([
            Textarea::make('rejection_reason')
                ->label(__('budgets.fields.rejection_reason'))
                ->maxLength(255),
        ]);
    }

    public static function revert(): Action
    {
        return Action::make('revert')
            ->label(__('budgets.actions.revert'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->authorize('revert')
            ->hidden(fn (Budget $record): bool => $record->trashed() || $record->status->isEditable())
            ->action(function (Budget $record): void {
                self::report(fn () => app(RevertBudgetToSentAction::class)->handle($record, Auth::user()), 'sent');
            });
    }

    public static function discard(): Action
    {
        return Action::make('discard')
            ->label(__('budgets.actions.discard'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->authorize('discard')
            ->hidden(fn (Budget $record): bool => $record->trashed() || $record->status !== BudgetStatus::Draft)
            ->action(function (Budget $record, $livewire): void {
                try {
                    app(DiscardBudgetAction::class)->handle($record, Auth::user());
                } catch (BudgetNotDiscardableException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('budgets.notifications.discarded'))->send();

                $livewire->redirect(BudgetResource::getUrl('index'));
            });
    }

    /**
     * @param  Closure(Budget, array<string, mixed>): mixed  $handler
     */
    private static function stateAction(string $name, string $icon, string $color, BudgetStatus $visibleFrom, string $resultingState, Closure $handler): Action
    {
        return Action::make($name)
            ->label(__("budgets.actions.{$name}"))
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation(fn (): bool => $name !== 'reject')
            ->authorize($name)
            ->hidden(fn (Budget $record): bool => $record->trashed() || $record->status !== $visibleFrom)
            ->action(function (Budget $record, array $data) use ($handler, $resultingState): void {
                self::report(fn () => $handler($record, $data), $resultingState);
            });
    }

    /** Runs a state change and tells the user how it went. */
    private static function report(Closure $change, string $resultingState): void
    {
        try {
            $change();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(collect($exception->errors())->flatten()->first())->send();

            return;
        } catch (InvalidBudgetTransitionException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(__("budgets.notifications.{$resultingState}"))->send();
    }
}
