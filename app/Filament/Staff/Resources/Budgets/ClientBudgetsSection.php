<?php

namespace App\Filament\Staff\Resources\Budgets;

use App\Enums\BudgetStatus;
use App\Filament\Staff\Resources\Clients\ClientCardExtensions;
use App\Models\Budget;
use App\Models\Client;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

/**
 * The budgets of a client on the client card (spec 003, RF-46): number, title, total, status
 * and issue date, newest first, each one linking to its own card.
 */
final class ClientBudgetsSection
{
    public static function register(): void
    {
        ClientCardExtensions::register('budgets', fn (Client $client): Section => self::section($client));
    }

    private static function section(Client $client): Section
    {
        return Section::make(__('budgets.plural'))->schema([
            RepeatableEntry::make('client_budgets')
                ->hiddenLabel()
                ->state(fn () => $client->budgets()->latest('issue_date')->latest('id')->get())
                ->placeholder(__('budgets.client_section.empty'))
                ->schema([
                    TextEntry::make('id')
                        ->label(__('budgets.fields.id'))
                        ->formatStateUsing(fn (int $state): string => "#{$state}")
                        ->url(fn (Budget $record): string => BudgetResource::getUrl('view', ['record' => $record])),
                    TextEntry::make('title')->label(__('budgets.fields.title')),
                    TextEntry::make('total')
                        ->label(__('budgets.fields.total'))
                        ->formatStateUsing(fn (string $state): string => Money::display($state)),
                    TextEntry::make('status')
                        ->label(__('budgets.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (BudgetStatus $state): string => $state->label()),
                    TextEntry::make('issue_date')->label(__('budgets.fields.issue_date'))->date('d/m/Y'),
                ])
                ->columns(5),
        ]);
    }
}
