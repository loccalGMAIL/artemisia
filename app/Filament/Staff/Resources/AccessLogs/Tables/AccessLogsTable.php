<?php

namespace App\Filament\Staff\Resources\AccessLogs\Tables;

use App\Enums\AccessMethod;
use App\Enums\AccessOutcome;
use App\Enums\AccessPortal;
use App\Enums\AccessRejection;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccessLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('logs.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('email_used')
                    ->label(__('logs.fields.email_used'))
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label(__('logs.fields.user'))
                    ->placeholder('—'),
                TextColumn::make('portal')
                    ->label(__('logs.fields.portal'))
                    ->formatStateUsing(fn (AccessPortal $state): string => $state->label()),
                TextColumn::make('method')
                    ->label(__('logs.fields.method'))
                    ->formatStateUsing(fn (AccessMethod $state): string => $state->label()),
                TextColumn::make('outcome')
                    ->label(__('logs.fields.outcome'))
                    ->badge()
                    ->color(fn (AccessOutcome $state): string => $state === AccessOutcome::Success ? 'success' : 'danger')
                    ->formatStateUsing(fn (AccessOutcome $state): string => $state->label()),
                TextColumn::make('rejection_reason')
                    ->label(__('logs.fields.rejection_reason'))
                    ->placeholder('—')
                    ->formatStateUsing(fn (string $state): string => AccessRejection::tryFrom($state)?->message() ?? $state),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
