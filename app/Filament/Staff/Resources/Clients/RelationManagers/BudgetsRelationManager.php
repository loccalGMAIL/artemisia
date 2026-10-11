<?php

namespace App\Filament\Staff\Resources\Clients\RelationManagers;

use App\Enums\BudgetStatus;
use App\Filament\Staff\Resources\Budgets\BudgetResource;
use App\Models\Budget;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The budgets of the client, a tab of its card (spec 003, RF-46): number, title, total, status
 * and issue date, newest first, each one linking to its own card.
 */
class BudgetsRelationManager extends RelationManager
{
    protected static string $relationship = 'budgets';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('budgets.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('budgets.fields.id'))
                    ->formatStateUsing(fn (int $state): string => "#{$state}")
                    ->url(fn (Budget $record): string => BudgetResource::getUrl('view', ['record' => $record])),
                TextColumn::make('title')->label(__('budgets.fields.title')),
                TextColumn::make('total')
                    ->label(__('budgets.fields.total'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextColumn::make('status')
                    ->label(__('budgets.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (BudgetStatus $state): string => $state->label()),
                TextColumn::make('issue_date')->label(__('budgets.fields.issue_date'))->date('d/m/Y'),
            ])
            ->defaultSort('issue_date', 'desc')
            ->emptyStateHeading(__('budgets.client_section.empty'))
            ->paginated([10, 25, 50]);
    }
}
