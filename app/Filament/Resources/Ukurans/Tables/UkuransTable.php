<?php

namespace App\Filament\Resources\Ukurans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UkuransTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('panjang')
                    ->label('Panjang')
                    ->formatStateUsing(fn($state) => $state ? "{$state} cm" : '-')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('lebar')
                    ->label('Lebar')
                    ->formatStateUsing(fn($state) => $state ? "{$state} cm" : '-')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('tebal')
                    ->label('Tebal')
                    ->formatStateUsing(fn($state) => $state ? "{$state} cm" : '-')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
