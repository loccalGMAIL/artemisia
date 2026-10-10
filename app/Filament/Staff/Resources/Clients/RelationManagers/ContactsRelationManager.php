<?php

namespace App\Filament\Staff\Resources\Clients\RelationManagers;

use App\Actions\AddClientContactAction;
use App\Actions\RemoveClientContactAction;
use App\Actions\SetPrimaryContactAction;
use App\Actions\UpdateClientContactAction;
use App\Filament\Support\FormValidation;
use App\Models\Client;
use App\Models\ClientContact;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * @method Client getOwnerRecord()
 */
class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('clients.relations.contacts');
    }

    /** Contacts are managed right from the client's record card (RF-13, RF-19). */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('clients.fields.contact_name'))->maxLength(150),
            TextInput::make('role')->label(__('clients.fields.contact_role'))->maxLength(100),
            TextInput::make('phone')->label(__('clients.fields.phone'))->maxLength(30),
            TextInput::make('email')->label(__('clients.fields.email'))->maxLength(150),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('clients.fields.contact_name')),
                TextColumn::make('role')->label(__('clients.fields.contact_role'))->placeholder('—'),
                TextColumn::make('phone')->label(__('clients.fields.phone'))->placeholder('—'),
                TextColumn::make('email')->label(__('clients.fields.email'))->placeholder('—'),
                IconColumn::make('is_primary')->label(__('clients.fields.is_primary'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, Action $action): ClientContact {
                        try {
                            return app(AddClientContactAction::class)->handle($this->getOwnerRecord(), $data, Auth::user());
                        } catch (ValidationException $exception) {
                            FormValidation::forAction($exception, $action);
                        }
                    }),
            ])
            ->recordActions([
                Action::make('setPrimary')
                    ->label(__('clients.actions.set_primary'))
                    ->icon('heroicon-o-star')
                    ->hidden(fn (ClientContact $record): bool => $record->is_primary)
                    ->action(fn (ClientContact $record) => app(SetPrimaryContactAction::class)->handle($this->getOwnerRecord(), $record, Auth::user())),
                EditAction::make()
                    ->using(function (ClientContact $record, array $data, Action $action): ClientContact {
                        try {
                            return app(UpdateClientContactAction::class)->handle($record, $data, Auth::user());
                        } catch (ValidationException $exception) {
                            FormValidation::forAction($exception, $action);
                        }
                    }),
                DeleteAction::make()
                    ->using(function (ClientContact $record): bool {
                        app(RemoveClientContactAction::class)->handle($record, Auth::user());

                        return true;
                    }),
            ]);
    }
}
