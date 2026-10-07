<?php

namespace App\Filament\Staff\Resources\Clients\RelationManagers;

use App\Actions\LinkAccountToClientAction;
use App\Actions\UnlinkAccountFromClientAction;
use App\Filament\Support\FormValidation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Portal accounts linked to the client (RF-38 to RF-41). Authorization comes from the
 * client policy, not from the account policy, which is admin-only.
 */
class AccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'accounts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('clients.relations.accounts');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('clients.fields.account_name')),
                TextColumn::make('email')->label(__('clients.fields.email')),
                IconColumn::make('is_active')->label(__('clients.fields.account_active'))->boolean(),
            ])
            ->headerActions([
                Action::make('link')
                    ->label(__('clients.actions.link_account'))
                    ->icon('heroicon-o-link')
                    ->authorize(fn (): bool => Gate::allows('linkAccount', $this->getOwnerRecord()))
                    ->schema([
                        Select::make('account_id')
                            ->label(__('clients.fields.account'))
                            ->options(fn (): array => User::query()
                                ->role('client')
                                ->whereNull('client_id')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (User $account): array => [$account->id => "{$account->name} ({$account->email})"])
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data, Action $action): void {
                        try {
                            app(LinkAccountToClientAction::class)->handle(
                                $this->getOwnerRecord(),
                                User::query()->findOrFail($data['account_id']),
                                Auth::user(),
                            );
                        } catch (ValidationException $exception) {
                            FormValidation::forAction($exception, $action);
                        }
                    }),
            ])
            ->recordActions([
                Action::make('unlink')
                    ->label(__('clients.actions.unlink_account'))
                    ->icon('heroicon-o-link-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => Gate::allows('unlinkAccount', $this->getOwnerRecord()))
                    ->action(fn (User $record) => app(UnlinkAccountFromClientAction::class)->handle($record, Auth::user())),
            ]);
    }
}
