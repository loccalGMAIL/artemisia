<?php

namespace App\Filament\Staff\Resources\Budgets\Schemas;

use App\Actions\BuildWhatsAppLinkAction;
use App\Enums\BudgetModality;
use App\Enums\BudgetStatus;
use App\Models\Budget;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The record card of a budget (RF-54, RF-41). Items and history are the relation managers
 * below it.
 */
class BudgetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('budgets.sections.header'))->schema([
                TextEntry::make('id')
                    ->label(__('budgets.fields.id'))
                    ->formatStateUsing(fn (int $state): string => "#{$state}"),
                TextEntry::make('title')->label(__('budgets.fields.title')),
                TextEntry::make('client.display_name')->label(__('budgets.fields.client')),
                TextEntry::make('modality')
                    ->label(__('budgets.fields.modality'))
                    ->formatStateUsing(fn (BudgetModality $state): string => $state->label()),
                TextEntry::make('status')
                    ->label(__('budgets.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (BudgetStatus $state): string => $state->label()),
                TextEntry::make('issue_date')->label(__('budgets.fields.issue_date'))->date('d/m/Y'),
                TextEntry::make('validity_date')
                    ->label(__('budgets.fields.validity_date'))
                    ->date('d/m/Y')
                    ->helperText(fn (Budget $record): ?string => $record->isExpired() ? __('budgets.expired') : null),
                TextEntry::make('response_date')
                    ->label(__('budgets.fields.response_date'))
                    ->date('d/m/Y')
                    ->visible(fn (Budget $record): bool => $record->response_date !== null),
                TextEntry::make('rejection_reason')
                    ->label(__('budgets.fields.rejection_reason'))
                    ->visible(fn (Budget $record): bool => $record->rejection_reason !== null),
                TextEntry::make('creator.name')->label(__('budgets.fields.author')),
                TextEntry::make('whatsapp_notice')
                    ->hiddenLabel()
                    ->color('warning')
                    ->state(__('budgets.whatsapp.missing_phone'))
                    ->visible(fn (Budget $record): bool => app(BuildWhatsAppLinkAction::class)->handle($record) === null),
            ])->columns(2),

            Section::make(__('budgets.sections.amounts'))->schema([
                TextEntry::make('subtotal')
                    ->label(__('budgets.fields.subtotal'))
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextEntry::make('discount_amount')
                    ->label(fn (Budget $record): string => $record->discount_type === null
                        ? __('budgets.fields.discount')
                        : __('budgets.fields.discount').' ('.$record->discount_type->label().')')
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
                TextEntry::make('total')
                    ->label(fn (Budget $record): string => $record->totalLabel())
                    ->weight('bold')
                    ->formatStateUsing(fn (string $state): string => Money::display($state)),
            ])->columns(3),
        ]);
    }
}
