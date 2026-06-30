<?php

namespace App\Filament\Resources\RiwayatKayus\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RiwayatKayusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_masuk')
                    ->sortable(),
                TextColumn::make('tanggal_digunakan')
                    ->sortable(),
                TextColumn::make('tanggal_habis')
                    ->sortable(),
                TextColumn::make('tempatKayu.select_label')
                    ->label('Tempat Kayu')
                    ->searchable()
                    ->sortable(false),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
