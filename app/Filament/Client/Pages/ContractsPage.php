<?php

namespace App\Filament\Client\Pages;

use App\Actions\BuildClientStatementAction;
use App\Enums\ContractStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Support\Money;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * The account statement of the client the signed-in account is linked to, read only (spec 005,
 * RF-93 to RF-96, RF-98): its contracts with their periods, extras, discounts, totals, balances
 * and valid payments. The client is never taken from the request, only from the account
 * (RF-97), and the page has no action that writes anything.
 */
class ContractsPage extends Page
{
    protected static ?string $slug = 'contracts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('contracts.portal.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('contracts.portal.title');
    }

    public function content(Schema $schema): Schema
    {
        $account = Auth::user();
        $decision = Gate::inspect('portal.view', $this->linkedClient());

        if ($decision->denied()) {
            return $schema->components([
                Section::make()->schema([
                    TextEntry::make('notice')->hiddenLabel()->state($decision->message()),
                ]),
            ]);
        }

        $contracts = app(BuildClientStatementAction::class)->handle($account);

        if ($contracts === []) {
            return $schema->components([
                Section::make()->schema([
                    TextEntry::make('empty')->hiddenLabel()->state(__('contracts.portal.empty')),
                ]),
            ]);
        }

        return $schema->components(array_map(fn (array $contract): Component => $this->contractSection($contract), $contracts));
    }

    /**
     * @param  array{id: int, status: ContractStatus, monthly_amount: string, start_date: CarbonInterface, term_months: int|null, periods: array<int, array<string, mixed>>}  $contract
     */
    private function contractSection(array $contract): Section
    {
        return Section::make(__('contracts.portal.contract_heading', ['id' => $contract['id']]))
            ->schema([
                TextEntry::make("contract_{$contract['id']}_status")
                    ->label(__('contracts.fields.status'))
                    ->badge()
                    ->state($contract['status']->label())
                    ->color(match ($contract['status']) {
                        ContractStatus::Active => 'success',
                        ContractStatus::Finished => 'gray',
                        ContractStatus::Cancelled => 'danger',
                    }),
                TextEntry::make("contract_{$contract['id']}_amount")
                    ->label(__('contracts.fields.monthly_amount'))
                    ->state(Money::display($contract['monthly_amount'])),
                TextEntry::make("contract_{$contract['id']}_start")
                    ->label(__('contracts.fields.start_date'))
                    ->state($contract['start_date']->format('d/m/Y')),
                TextEntry::make("contract_{$contract['id']}_term")
                    ->label(__('contracts.fields.term'))
                    ->state($contract['term_months'] === null
                        ? __('contracts.term.indefinite')
                        : trans_choice('contracts.term.months', $contract['term_months'], ['count' => $contract['term_months']])),
                RepeatableEntry::make("contract_{$contract['id']}_periods")
                    ->label(__('contracts.relations.periods'))
                    ->state($contract['periods'])
                    ->placeholder(__('contracts.portal.no_periods'))
                    ->columnSpanFull()
                    ->schema($this->periodEntries())
                    ->columns(4),
            ])
            ->columns(4);
    }

    /**
     * @return array<int, Component>
     */
    private function periodEntries(): array
    {
        return [
            TextEntry::make('month')->label(__('contracts.periods.month'))->date('m/Y'),
            TextEntry::make('due_date')->label(__('contracts.periods.due_date'))->date('d/m/Y'),
            TextEntry::make('fee')->label(__('contracts.periods.fee'))->formatStateUsing(fn (string $state): string => Money::display($state)),
            TextEntry::make('payment_status')
                ->label(__('contracts.periods.payment_status'))
                ->badge()
                ->color(fn (PaymentStatus $state): string => match ($state) {
                    PaymentStatus::Pending => 'warning',
                    PaymentStatus::Partial => 'info',
                    PaymentStatus::Paid => 'success',
                })
                ->formatStateUsing(fn (PaymentStatus $state): string => $state->label()),
            RepeatableEntry::make('extras')
                ->label(__('contracts.portal.extras'))
                ->visible(fn (?array $state): bool => filled($state))
                ->schema($this->conceptEntries())
                ->columns(2)
                ->columnSpan(2),
            RepeatableEntry::make('discounts')
                ->label(__('contracts.portal.discounts'))
                ->visible(fn (?array $state): bool => filled($state))
                ->schema($this->conceptEntries())
                ->columns(2)
                ->columnSpan(2),
            TextEntry::make('total')->label(__('contracts.periods.total'))->weight('bold')->formatStateUsing(fn (string $state): string => Money::display($state)),
            TextEntry::make('paid_amount')->label(__('contracts.periods.paid'))->formatStateUsing(fn (string $state): string => Money::display($state)),
            TextEntry::make('balance')->label(__('contracts.periods.balance'))->formatStateUsing(fn (string $state): string => Money::display($state)),
            TextEntry::make('overdue')
                ->label(__('contracts.periods.overdue_column'))
                ->badge()
                ->color('danger')
                ->visible(fn (?bool $state): bool => (bool) $state)
                ->formatStateUsing(fn (): string => __('contracts.periods.overdue')),
            RepeatableEntry::make('payments')
                ->label(__('contracts.portal.payments'))
                ->visible(fn (?array $state): bool => filled($state))
                ->schema([
                    TextEntry::make('paid_on')->label(__('contracts.payments.paid_on'))->date('d/m/Y'),
                    TextEntry::make('amount')->label(__('contracts.payments.amount'))->formatStateUsing(fn (string $state): string => Money::display($state)),
                    TextEntry::make('method')->label(__('contracts.payments.method'))->formatStateUsing(fn (PaymentMethod $state): string => $state->label()),
                    TextEntry::make('reference')->label(__('contracts.payments.reference'))->placeholder('—'),
                ])
                ->columns(4)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function conceptEntries(): array
    {
        return [
            TextEntry::make('description')->label(__('contracts.concepts.description')),
            TextEntry::make('amount')->label(__('contracts.concepts.amount'))->formatStateUsing(fn (string $state): string => Money::display($state)),
        ];
    }

    private function linkedClient(): ?Client
    {
        $clientId = Auth::user()?->client_id;

        return $clientId === null ? null : Client::withTrashed()->find($clientId);
    }
}
