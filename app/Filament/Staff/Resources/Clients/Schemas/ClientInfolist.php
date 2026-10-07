<?php

namespace App\Filament\Staff\Resources\Clients\Schemas;

use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Filament\Staff\Resources\Clients\ClientCardExtensions;
use App\Models\Client;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The client's record card (RF-45): identification, address, contact phone and status.
 * Contacts, linked accounts and history are the relation managers below it.
 */
class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('clients.sections.identification'))->schema([
                TextEntry::make('display_name')->label(__('clients.fields.display_name')),
                TextEntry::make('person_type')
                    ->label(__('clients.fields.person_type'))
                    ->formatStateUsing(fn (ClientType $state): string => $state->label()),
                TextEntry::make('document')
                    ->label(fn (Client $record): string => $record->person_type->documentLabel()),
                TextEntry::make('status')
                    ->label(__('clients.fields.status'))
                    ->badge()
                    ->color(fn (ClientStatus $state): string => $state === ClientStatus::Active ? 'success' : 'gray')
                    ->formatStateUsing(fn (ClientStatus $state): string => $state->label()),
                TextEntry::make('archived')
                    ->label(__('clients.fields.archived'))
                    ->state(fn (Client $record): ?string => $record->trashed() ? __('clients.view.archived_on', ['date' => $record->deleted_at->format('d/m/Y')]) : null)
                    ->visible(fn (Client $record): bool => $record->trashed()),
                TextEntry::make('contact_phone')
                    ->label(__('clients.fields.contact_phone'))
                    ->state(fn (Client $record): string => $record->contactPhone() ?? __('clients.view.no_contact_phone')),
            ])->columns(2),

            Section::make(__('clients.sections.address'))->schema([
                TextEntry::make('street')->label(__('clients.fields.street'))->placeholder('—'),
                TextEntry::make('street_number')->label(__('clients.fields.street_number'))->placeholder('—'),
                TextEntry::make('city')->label(__('clients.fields.city'))->placeholder('—'),
                TextEntry::make('province.name')->label(__('clients.fields.province'))->placeholder('—'),
                TextEntry::make('postal_code')->label(__('clients.fields.postal_code'))->placeholder('—'),
            ])->columns(2),

            // Budgets, contracts and payments plug in here once those modules exist (RF-46 to RF-48).
            Group::make()
                ->schema(fn (Client $record): array => ClientCardExtensions::componentsFor($record))
                ->columnSpanFull(),
        ]);
    }
}
