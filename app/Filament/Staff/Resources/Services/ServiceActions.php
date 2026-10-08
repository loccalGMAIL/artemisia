<?php

namespace App\Filament\Staff\Resources\Services;

use App\Actions\ToggleServiceActiveAction;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Activate/deactivate action shared by the list and the edit page. It only triggers the
 * Action; who may use it comes from ServicePolicy.
 */
final class ServiceActions
{
    public static function toggleActive(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (Service $record): string => $record->is_active
                ? __('budgets.services.actions.deactivate')
                : __('budgets.services.actions.activate'))
            ->icon(fn (Service $record): string => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
            ->color(fn (Service $record): string => $record->is_active ? 'warning' : 'success')
            ->requiresConfirmation()
            ->authorize('toggleActive')
            ->action(function (Service $record): void {
                $service = app(ToggleServiceActiveAction::class)->handle($record);

                Notification::make()->success()->title($service->is_active
                    ? __('budgets.services.notifications.activated')
                    : __('budgets.services.notifications.deactivated'))->send();
            });
    }
}
