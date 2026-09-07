<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\Activity;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s'),
                TextEntry::make('log_name')
                    ->label('Tipo')
                    ->badge(),
                TextEntry::make('event')
                    ->label('Evento')
                    ->badge(),
                TextEntry::make('description')
                    ->label('Descripción'),
                TextEntry::make('causer.name')
                    ->label('Usuario')
                    ->placeholder('Sistema'),
                TextEntry::make('subject_type')
                    ->label('Tipo de sujeto')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? class_basename($state) : null)
                    ->placeholder('-'),
                TextEntry::make('subject_id')
                    ->label('ID del sujeto')
                    ->placeholder('-'),
                Section::make('Cambios')
                    ->columns(2)
                    ->visible(fn (Activity $record): bool => filled($record->properties) && $record->properties->isNotEmpty())
                    ->components([
                        KeyValueEntry::make('properties.old')
                            ->label('Antes')
                            ->visible(fn (Activity $record): bool => filled($record->properties['old'] ?? null)),
                        KeyValueEntry::make('properties.attributes')
                            ->label('Después')
                            ->visible(fn (Activity $record): bool => filled($record->properties['attributes'] ?? null)),
                        // Los eventos de auth/accesos no siguen el formato old/attributes
                        // de LogsActivity: guardan sus propios datos sueltos (ip, panel,
                        // roles/permisos afectados, etc.), así que se muestran tal cual.
                        KeyValueEntry::make('properties')
                            ->label('Datos')
                            ->columnSpanFull()
                            ->visible(fn (Activity $record): bool => blank($record->properties['old'] ?? null) && blank($record->properties['attributes'] ?? null)),
                    ]),
            ]);
    }
}
