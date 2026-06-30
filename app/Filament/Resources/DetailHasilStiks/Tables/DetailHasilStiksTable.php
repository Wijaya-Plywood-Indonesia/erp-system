<?php

namespace App\Filament\Resources\DetailHasilStiks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DetailHasilStiksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable(),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),

                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('isi')
                    ->label('Isi'),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // INI ADALAH TOMBOL UNTUK MEMBUAT DATA BARU
                CreateAction::make(),
            ])
            ->recordActions([
                // Tombol di setiap baris (Edit, Delete)
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
