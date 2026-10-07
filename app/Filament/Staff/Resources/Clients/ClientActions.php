<?php

namespace App\Filament\Staff\Resources\Clients;

use App\Actions\ActivateClientAction;
use App\Actions\ArchiveClientAction;
use App\Actions\DeactivateClientAction;
use App\Actions\RestoreClientAction;
use App\Enums\ClientStatus;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Header actions to change the state of a client. Each one only triggers an Action; who may
 * use it comes from ClientPolicy.
 */
final class ClientActions
{
    /**
     * @return array<int, Action>
     */
    public static function state(): array
    {
        return [self::deactivate(), self::activate(), self::archive(), self::restore()];
    }

    public static function deactivate(): Action
    {
        return Action::make('deactivate')
            ->label(__('clients.actions.deactivate'))
            ->icon('heroicon-o-no-symbol')
            ->color('warning')
            ->requiresConfirmation()
            ->authorize('update')
            ->hidden(fn (Client $record): bool => $record->trashed() || $record->status !== ClientStatus::Active)
            ->action(function (Client $record): void {
                app(DeactivateClientAction::class)->handle($record, Auth::user());

                Notification::make()->success()->title(__('clients.notifications.deactivated'))->send();
            });
    }

    public static function activate(): Action
    {
        return Action::make('activate')
            ->label(__('clients.actions.activate'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->authorize('update')
            ->hidden(fn (Client $record): bool => $record->trashed() || $record->status !== ClientStatus::Inactive)
            ->action(function (Client $record): void {
                app(ActivateClientAction::class)->handle($record, Auth::user());

                Notification::make()->success()->title(__('clients.notifications.activated'))->send();
            });
    }

    public static function archive(): Action
    {
        return Action::make('archive')
            ->label(__('clients.actions.archive'))
            ->icon('heroicon-o-archive-box')
            ->color('danger')
            ->requiresConfirmation()
            ->authorize('archive')
            ->hidden(fn (Client $record): bool => $record->trashed())
            ->action(function (Client $record): void {
                app(ArchiveClientAction::class)->handle($record, Auth::user());

                Notification::make()->success()->title(__('clients.notifications.archived'))->send();
            });
    }

    public static function restore(): Action
    {
        return Action::make('restore')
            ->label(__('clients.actions.restore'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->requiresConfirmation()
            ->authorize('restore')
            ->hidden(fn (Client $record): bool => ! $record->trashed())
            ->action(function (Client $record): void {
                app(RestoreClientAction::class)->handle($record, Auth::user());

                Notification::make()->success()->title(__('clients.notifications.restored'))->send();
            });
    }
}
