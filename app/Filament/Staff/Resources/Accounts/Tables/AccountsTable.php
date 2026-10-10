<?php

namespace App\Filament\Staff\Resources\Accounts\Tables;

use App\Actions\ActivateAccountAction;
use App\Actions\ChangeAccountRoleAction;
use App\Actions\DeactivateAccountAction;
use App\Enums\AccountRole;
use App\Exceptions\CannotChangeOwnRoleException;
use App\Exceptions\CannotDeactivateOwnAccountException;
use App\Exceptions\LastActiveAdminException;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('accounts.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('accounts.fields.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label(__('accounts.fields.role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AccountRole::from($state)->label()),
                IconColumn::make('is_active')
                    ->label(__('accounts.fields.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('accounts.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('changeRole')
                    ->label(__('accounts.actions.change_role'))
                    ->icon('heroicon-o-arrows-right-left')
                    ->authorize('changeRole')
                    ->hidden(fn (User $record): bool => $record->is(Auth::user()))
                    ->fillForm(fn (User $record): array => ['role' => $record->getRoleNames()->first()])
                    ->schema([
                        Select::make('role')
                            ->label(__('accounts.fields.role'))
                            ->options(AccountRole::options())
                            ->required(),
                    ])
                    ->action(fn (User $record, array $data) => self::run(
                        fn () => app(ChangeAccountRoleAction::class)->handle($record, $data['role'], Auth::user()),
                        'role_changed',
                    )),
                Action::make('deactivate')
                    ->label(__('accounts.actions.deactivate'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize('deactivate')
                    ->hidden(fn (User $record): bool => $record->is(Auth::user()) || ! $record->is_active)
                    ->action(fn (User $record) => self::run(
                        fn () => app(DeactivateAccountAction::class)->handle($record, Auth::user()),
                        'deactivated',
                    )),
                Action::make('activate')
                    ->label(__('accounts.actions.activate'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize('activate')
                    ->hidden(fn (User $record): bool => $record->is(Auth::user()) || $record->is_active)
                    ->action(fn (User $record) => self::run(
                        fn () => app(ActivateAccountAction::class)->handle($record, Auth::user()),
                        'activated',
                    )),
            ]);
    }

    /**
     * Runs an account Action and reports its outcome; the rules themselves live in the Action.
     */
    private static function run(Closure $operation, string $successKey): void
    {
        try {
            $operation();
        } catch (CannotChangeOwnRoleException|CannotDeactivateOwnAccountException|LastActiveAdminException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(__("accounts.notifications.{$successKey}"))->send();
    }
}
