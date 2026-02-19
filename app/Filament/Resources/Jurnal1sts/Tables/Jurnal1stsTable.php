<?php

namespace App\Filament\Resources\Jurnal1sts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class Jurnal1stsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('modif10', 'asc')
            ->defaultSort('no_akun', 'asc')
            ->columns([
                TextColumn::make('modif10')
                    ->label('Modif 10')
                    ->sortable(),

                TextColumn::make('no_akun')
                    ->label('No Akun')
                    ->sortable(),

                TextColumn::make('nama_akun')
                    ->label('Nama Akun'),


                TextColumn::make('bagian')
                    ->label('Bagian')
                    ->formatStateUsing(fn($state) => $state === 'd' ? 'Debit' : 'Kredit')
                    ->sortable(),

                TextColumn::make('banyak')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('m3')
                    ->label('m³')
                    ->suffix('m³')
                    ->formatStateUsing(fn($state) => number_format($state, 4))
                    ->sortable(),

                TextColumn::make('harga')
                    ->label('Harga')
                    ->money('Rp. ', true) // Format otomatis ke Rp 1.000
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('Rp. ', true)
                    ->sortable(),

                TextColumn::make('created_by') // pakai relasi creator()
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->searchable(),

                TextColumn::make('synced_at')
                    ->label('Waktu Sinkron')
                    ->dateTime('d M Y H:i')
                    ->toggleable(true),

                TextColumn::make('synced_by')
                    ->label('Disinkron Oleh')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(true),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diubah Pada')
                    ->dateTime('d M Y H:i')
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
