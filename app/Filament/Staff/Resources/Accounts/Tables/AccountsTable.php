<?php

namespace App\Filament\Staff\Resources\Accounts\Tables;

use App\Actions\ActivateAccountAction;
use App\Actions\ChangeAccountRoleAction;
use App\Actions\DeactivateAccountAction;
use App\Actions\UpdateAccountProfileAction;
use App\Enums\AccountRole;
use App\Exceptions\CannotChangeOwnEmailException;
use App\Exceptions\CannotChangeOwnRoleException;
use App\Exceptions\CannotDeactivateOwnAccountException;
use App\Exceptions\DuplicateAccountEmailException;
use App\Exceptions\LastActiveAdminException;
use App\Filament\Support\FormValidation;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
                self::editAction(),
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
     * Name and email of another account (spec 006). The own account is edited from the profile
     * screen, where the email cannot change.
     */
    private static function editAction(): Action
    {
        return Action::make('edit')
            ->label(__('accounts.actions.edit'))
            ->icon('heroicon-o-pencil-square')
            ->authorize('update')
            ->hidden(fn (User $record): bool => $record->is(Auth::user()))
            ->fillForm(fn (User $record): array => $record->only(['name', 'email']))
            ->schema([
                TextInput::make('name')
                    ->label(__('accounts.fields.name'))
                    ->required()
                    ->maxLength(150),
                TextInput::make('email')
                    ->label(__('accounts.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(150),
            ])
            ->action(function (User $record, array $data, Action $action): void {
                try {
                    app(UpdateAccountProfileAction::class)->handle($record, $data, Auth::user());
                } catch (DuplicateAccountEmailException $exception) {
                    FormValidation::forAction(ValidationException::withMessages(['email' => $exception->getMessage()]), $action);
                } catch (ValidationException $exception) {
                    FormValidation::forAction($exception, $action);
                } catch (CannotChangeOwnEmailException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('accounts.notifications.updated'))->send();
            });
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
