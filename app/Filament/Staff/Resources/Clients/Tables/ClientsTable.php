<?php

namespace App\Filament\Staff\Resources\Clients\Tables;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Filament\Staff\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The query logic (search, display-name ordering) lives in the Client model; this table
 * only wires it to the columns and filters (constitution, principle 3).
 */
class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('clients.fields.display_name'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->search($search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        Client::displayNameSql().' '.($direction === 'desc' ? 'desc' : 'asc'),
                    )),
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
                    ->dateTime()
                    ->sortable(),
            ])
            ->splitSearchTerms(false)
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('clients.fields.status'))
                    ->options(collect(ClientStatus::cases())->mapWithKeys(fn (ClientStatus $status) => [$status->value => $status->label()])->all()),
                SelectFilter::make('person_type')
                    ->label(__('clients.fields.person_type'))
                    ->options(collect(ClientType::cases())->mapWithKeys(fn (ClientType $type) => [$type->value => $type->label()])->all()),
                TrashedFilter::make()
                    ->label(__('clients.fields.archived')),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->recordUrl(fn (Client $record): string => ClientResource::getUrl('view', ['record' => $record]));
    }
}
