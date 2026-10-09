<?php

namespace App\Filament\Staff\Resources\Budgets\Tables;

use App\Enums\BudgetModality;
use App\Enums\BudgetStatus;
use App\Filament\Staff\Resources\Budgets\BudgetResource;
use App\Models\Budget;
use App\Models\Client;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BudgetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Discarded budgets leave the list (RF-52); totals are stored, so no aggregation here.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed()->with('client'))
            ->columns([
                TextColumn::make('id')
                    ->label(__('budgets.fields.id'))
                    ->formatStateUsing(fn (int $state): string => "#{$state}")
                    ->sortable(),
                TextColumn::make('client.display_name')
                    ->label(__('budgets.fields.client')),
                TextColumn::make('title')
                    ->label(__('budgets.fields.title')),
                TextColumn::make('modality')
                    ->label(__('budgets.fields.modality'))
                    ->formatStateUsing(fn (BudgetModality $state): string => $state->label()),
                TextColumn::make('total')
                    ->label(__('budgets.fields.total'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextColumn::make('status')
                    ->label(__('budgets.fields.status'))
                    ->badge()
                    ->color(fn (BudgetStatus $state): string => match ($state) {
                        BudgetStatus::Draft => 'gray',
                        BudgetStatus::Sent => 'info',
                        BudgetStatus::Accepted => 'success',
                        BudgetStatus::Rejected => 'danger',
                    })
                    ->formatStateUsing(fn (BudgetStatus $state): string => $state->label()),
                TextColumn::make('expired')
                    ->label('')
                    ->badge()
                    ->color('warning')
                    ->state(fn (Budget $record): ?string => $record->isExpired() ? __('budgets.expired') : null),
                TextColumn::make('issue_date')
                    ->label(__('budgets.fields.issue_date'))
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('client_id')
                    ->label(__('budgets.fields.client'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Client::query()
                        ->search($search)
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Client $client): array => [$client->id => $client->display_name])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Client::withTrashed()->find($value)?->display_name),
                SelectFilter::make('status')
                    ->label(__('budgets.fields.status'))
                    ->options(collect(BudgetStatus::cases())->mapWithKeys(fn (BudgetStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->recordUrl(fn (Budget $record): string => BudgetResource::getUrl('view', ['record' => $record]));
    }
}
