<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'modelo' => 'Modelo',
                        'auth' => 'Autenticación',
                        'accesos' => 'Accesos',
                        default => $state ?? '-',
                    }),
                TextColumn::make('event')
                    ->label('Evento')
                    ->badge(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->wrap(),
                TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->placeholder('Sistema'),
                TextColumn::make('subject_type')
                    ->label('Sujeto')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? class_basename($state) : null)
                    ->placeholder('-'),
                TextColumn::make('subject_id')
                    ->label('ID')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Tipo')
                    ->options([
                        'modelo' => 'Modelo',
                        'auth' => 'Autenticación',
                        'accesos' => 'Accesos',
                    ]),
                // `causer` es una relación polimórfica (MorphTo): el helper
                // relationship() de SelectFilter no la resuelve bien (intenta
                // consultar la tabla del propio modelo). Se arma a mano contra
                // `App\Models\User`, que es el único causante posible hoy.
                SelectFilter::make('causer_id')
                    ->label('Usuario')
                    ->options(fn (): array => User::query()->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $q): Builder => $q->where('causer_type', User::class)->where('causer_id', $data['value']),
                        );
                    }),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, string $desde): Builder => $q->whereDate('created_at', '>=', $desde))
                            ->when($data['hasta'] ?? null, fn (Builder $q, string $hasta): Builder => $q->whereDate('created_at', '<=', $hasta));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
