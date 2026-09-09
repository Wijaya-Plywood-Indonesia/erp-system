<?php

namespace App\Filament\Resources\RekeningPerusahaans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RekeningPerusahaansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('pemilik_rekening')
                    ->label('Pemilik Rekening')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('nama_bank')
                    ->label('Nama Bank')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('no_rekening')
                    ->label('No Rekening')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('atas_nama')
                    ->label('Atas Nama')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
