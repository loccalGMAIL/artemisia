<?php

namespace App\Filament\Staff\Resources\Contracts;

use App\Enums\PaymentMethod;
use App\Filament\Staff\Resources\Clients\ClientCardExtensions;
use App\Models\Client;
use App\Models\Payment;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

/**
 * The payment history of a client on the client card (spec 005, RF-92): every payment received
 * through its contracts, the valid ones and the voided ones with their justification, newest
 * first.
 */
final class ClientPaymentsSection
{
    public static function register(): void
    {
        ClientCardExtensions::register('payments', fn (Client $client): Section => self::section($client));
    }

    private static function section(Client $client): Section
    {
        return Section::make(__('contracts.client_section.payments_title'))->schema([
            RepeatableEntry::make('client_payments')
                ->hiddenLabel()
                ->state(fn () => $client->payments()->with('contractPeriod')->orderByDesc('paid_on')->orderByDesc('payments.id')->get())
                ->placeholder(__('contracts.client_section.payments_empty'))
                ->schema([
                    TextEntry::make('paid_on')->label(__('contracts.payments.paid_on'))->date('d/m/Y'),
                    TextEntry::make('amount')
                        ->label(__('contracts.payments.amount'))
                        ->formatStateUsing(fn (string $state): string => Money::display($state)),
                    TextEntry::make('method')
                        ->label(__('contracts.payments.method'))
                        ->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                    TextEntry::make('reference')->label(__('contracts.payments.reference'))->placeholder('—'),
                    TextEntry::make('period')
                        ->label(__('contracts.periods.month'))
                        ->state(fn (Payment $record): string => $record->contractPeriod->month->format('m/Y')),
                    TextEntry::make('status')
                        ->label(__('contracts.payments.status'))
                        ->badge()
                        ->state(fn (Payment $record): string => $record->isVoided() ? __('contracts.payments.voided') : __('contracts.payments.valid'))
                        ->color(fn (Payment $record): string => $record->isVoided() ? 'danger' : 'success'),
                    TextEntry::make('void_justification')
                        ->label(__('contracts.payments.void_justification'))
                        ->visible(fn (Payment $record): bool => $record->isVoided()),
                ])
                ->columns(4),
        ]);
    }
}
