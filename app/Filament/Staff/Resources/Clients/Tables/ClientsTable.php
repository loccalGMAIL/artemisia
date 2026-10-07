<?php

namespace App\Filament\Staff\Resources\Clients\Tables;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('clients.fields.display_name')),
                TextColumn::make('person_type')
                    ->label(__('clients.fields.person_type'))
                    ->formatStateUsing(fn (ClientType $state): string => $state->label()),
                TextColumn::make('document')
                    ->label(__('clients.fields.document')),
                TextColumn::make('status')
                    ->label(__('clients.fields.status'))
                    ->badge()
                    ->color(fn (ClientStatus $state): string => $state === ClientStatus::Active ? 'success' : 'gray')
                    ->formatStateUsing(fn (ClientStatus $state): string => $state->label()),
                TextColumn::make('created_at')
                    ->label(__('clients.fields.created_at'))
                    ->dateTime(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->recordUrl(fn (Client $record): string => ViewAction::make()->getRecordUrl($record));
    }
}
