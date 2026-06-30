<?php

namespace App\Filament\Resources\Komposisis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class KomposisisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('grade_display')
                    ->label('Grade')
                    ->getStateUsing(fn ($record) =>
                        ($record->barangSetengahJadiHp?->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                        . ' | ' .
                        ($record->barangSetengahJadiHp?->grade?->nama_grade ?? '-')
                    )
                    ->sortable(),

                TextColumn::make('barangSetengahJadiHp.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grade_display')
                    ->label('Grade')
                    ->getStateUsing(fn ($record) =>
                        ($record->barangSetengahJadiHp?->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                        . ' | ' .
                        ($record->barangSetengahJadiHp?->grade?->nama_grade ?? '-')
                    )
                    ->sortable(),

                TextColumn::make('barangSetengahJadiHp.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->sortable(),
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
