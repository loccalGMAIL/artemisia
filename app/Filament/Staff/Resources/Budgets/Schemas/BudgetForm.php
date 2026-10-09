<?php

namespace App\Filament\Staff\Resources\Budgets\Schemas;

use App\Enums\BudgetModality;
use App\Models\Client;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * The header of a budget. Only clients available for new budgets are offered (RF-26 of the
 * clients spec), but a budget whose client was later deactivated or archived still shows it
 * (RF-20).
 */
class BudgetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('client_id')
                ->label(__('budgets.fields.client'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => Client::availableForBudgets()
                    ->search($search)
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (Client $client): array => [$client->id => $client->display_name])
                    ->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => Client::withTrashed()->find($value)?->display_name),
            TextInput::make('title')
                ->label(__('budgets.fields.title'))
                ->maxLength(150),
            Select::make('modality')
                ->label(__('budgets.fields.modality'))
                ->options(collect(BudgetModality::cases())->mapWithKeys(fn (BudgetModality $modality): array => [$modality->value => $modality->label()])->all()),
            DatePicker::make('issue_date')
                ->label(__('budgets.fields.issue_date'))
                ->default(today())
                ->native(false),
            DatePicker::make('validity_date')
                ->label(__('budgets.fields.validity_date'))
                ->native(false),
        ])->columns(2);
    }
}
