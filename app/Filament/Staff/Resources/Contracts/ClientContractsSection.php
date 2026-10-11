<?php

namespace App\Filament\Staff\Resources\Contracts;

use App\Enums\ContractStatus;
use App\Filament\Staff\Resources\Clients\ClientCardExtensions;
use App\Filament\Staff\Resources\Contracts\Tables\ContractsTable;
use App\Models\Client;
use App\Models\Contract;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

/**
 * The contracts of a client on the client card (spec 005, RF-91): number, monthly amount, start,
 * term and status, newest first, each one linking to its own card.
 */
final class ClientContractsSection
{
    public static function register(): void
    {
        ClientCardExtensions::register('contracts', fn (Client $client): Section => self::section($client));
    }

    private static function section(Client $client): Section
    {
        return Section::make(__('contracts.plural'))->schema([
            RepeatableEntry::make('client_contracts')
                ->hiddenLabel()
                ->state(fn () => $client->contracts()->latest('start_date')->latest('id')->get())
                ->placeholder(__('contracts.client_section.contracts_empty'))
                ->schema([
                    TextEntry::make('id')
                        ->label(__('contracts.fields.id'))
                        ->formatStateUsing(fn (int $state): string => "#{$state}")
                        ->url(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record])),
                    TextEntry::make('monthly_amount')
                        ->label(__('contracts.fields.monthly_amount'))
                        ->formatStateUsing(fn (string $state): string => Money::display($state)),
                    TextEntry::make('start_date')->label(__('contracts.fields.start_date'))->date('d/m/Y'),
                    TextEntry::make('term')
                        ->label(__('contracts.fields.term'))
                        ->state(fn (Contract $record): string => ContractsTable::termLabel($record)),
                    TextEntry::make('status')
                        ->label(__('contracts.fields.status'))
                        ->badge()
                        ->color(fn (ContractStatus $state): string => match ($state) {
                            ContractStatus::Active => 'success',
                            ContractStatus::Finished => 'gray',
                            ContractStatus::Cancelled => 'danger',
                        })
                        ->formatStateUsing(fn (ContractStatus $state): string => $state->label()),
                ])
                ->columns(5),
        ]);
    }
}
