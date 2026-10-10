<?php

namespace App\Filament\Staff\Resources\Pieces\Tables;

use App\Enums\PieceStatus;
use App\Filament\Staff\Resources\Pieces\PieceResource;
use App\Models\Client;
use App\Models\Piece;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PiecesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Discarded pieces leave the list through the soft delete scope (RF-27); the
            // relations shown in each row are loaded at once.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed()->with(['budget.client', 'assignee']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('pieces.fields.name'))
                    ->searchable(),
                TextColumn::make('budget.title')
                    ->label(__('pieces.fields.budget'))
                    ->formatStateUsing(fn (string $state, Piece $record): string => "#{$record->budget_id} · {$state}"),
                TextColumn::make('budget.client.display_name')
                    ->label(__('pieces.fields.client')),
                TextColumn::make('status')
                    ->label(__('pieces.fields.status'))
                    ->badge()
                    ->color(fn (PieceStatus $state): string => match ($state) {
                        PieceStatus::Pending => 'gray',
                        PieceStatus::InProduction => 'info',
                        PieceStatus::InReview => 'warning',
                        PieceStatus::ClientApproval => 'primary',
                        PieceStatus::Approved, PieceStatus::Delivered => 'success',
                    })
                    ->formatStateUsing(fn (PieceStatus $state): string => $state->label()),
                TextColumn::make('assignee.name')
                    ->label(__('pieces.fields.assignee'))
                    ->placeholder(__('pieces.unassigned')),
                TextColumn::make('due_date')
                    ->label(__('pieces.fields.due_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('overdue')
                    ->label('')
                    ->badge()
                    ->color('danger')
                    ->state(fn (Piece $record): ?string => $record->isOverdue() ? __('pieces.overdue') : null),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Piece $record): string => PieceResource::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('pieces.fields.status'))
                    ->options(collect(PieceStatus::cases())->mapWithKeys(fn (PieceStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('assignee_id')
                    ->label(__('pieces.fields.assignee'))
                    ->options(fn (): array => User::query()->assignableToPieces()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('client')
                    ->label(__('pieces.fields.client'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Client::query()
                        ->search($search)
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Client $client): array => [$client->id => $client->display_name])
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Client::withTrashed()->find($value)?->display_name)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->forClient((int) $data['value']) : $query),
                Filter::make('mine')
                    ->label(__('pieces.filters.mine'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->delegatedTo(Auth::user())),
                Filter::make('overdue')
                    ->label(__('pieces.filters.overdue'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->overdue()),
            ]);
    }
}
