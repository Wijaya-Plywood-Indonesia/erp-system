<?php

namespace App\Filament\Resources\ListPekerjaanMenumpuks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;

class ListPekerjaanMenumpuksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('barangSetengahJadiHp.id')
                    ->label('Detail Barang')
                    ->formatStateUsing(fn ($record) => 
                        ($record->barangSetengahJadiHp->jenisBarang->nama_jenis_barang ?? '-') . ' | ' .
                        ($record->barangSetengahJadiHp->ukuran->nama_ukuran ?? '-') . ' | ' .
                        ($record->barangSetengahJadiHp->grade->nama_grade ?? '-')
                    ),
                TextColumn::make('jumlah')
                    ->label('Jumlah')

            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
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
