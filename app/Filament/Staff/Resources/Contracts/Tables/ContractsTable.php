<?php

namespace App\Filament\Staff\Resources\Contracts\Tables;

use App\Enums\ContractStatus;
use App\Filament\Staff\Resources\Contracts\ContractResource;
use App\Models\Client;
use App\Models\Contract;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // The client is shown even when it was archived afterwards (RF-35).
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('client'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('contracts.fields.id'))
                    ->formatStateUsing(fn (int $state): string => "#{$state}")
                    ->sortable(),
                TextColumn::make('client.display_name')
                    ->label(__('contracts.fields.client')),
                TextColumn::make('monthly_amount')
                    ->label(__('contracts.fields.monthly_amount'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextColumn::make('start_date')
                    ->label(__('contracts.fields.start_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('term')
                    ->label(__('contracts.fields.term'))
                    ->state(fn (Contract $record): string => self::termLabel($record)),
                TextColumn::make('status')
                    ->label(__('contracts.fields.status'))
                    ->badge()
                    ->color(fn (ContractStatus $state): string => match ($state) {
                        ContractStatus::Active => 'success',
                        ContractStatus::Finished => 'gray',
                        ContractStatus::Cancelled => 'danger',
                    })
                    ->formatStateUsing(fn (ContractStatus $state): string => $state->label()),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('client_id')
                    ->label(__('contracts.fields.client'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Client::query()
                        ->search($search)
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Client $client): array => [$client->id => $client->display_name])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Client::withTrashed()->find($value)?->display_name),
                SelectFilter::make('status')
                    ->label(__('contracts.fields.status'))
                    ->options(collect(ContractStatus::cases())->mapWithKeys(fn (ContractStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->recordUrl(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record]));
    }

    /** "6 meses" for a fixed term, "Indefinido" otherwise (RF-8). */
    public static function termLabel(Contract $contract): string
    {
        return $contract->isIndefinite()
            ? __('contracts.term.indefinite')
            : trans_choice('contracts.term.months', $contract->term_months, ['count' => $contract->term_months]);
    }
}
