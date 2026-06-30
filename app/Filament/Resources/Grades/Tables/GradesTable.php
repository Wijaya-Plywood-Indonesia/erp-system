<?php

namespace App\Filament\Resources\Grades\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Models\KategoriBarang;

class GradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kategoriBarang.nama_kategori')
                    ->label('Kategori Barang')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nama_grade')
                    ->label('Nama Grade'),
            ])
            ->filters([
    SelectFilter::make('id_kategori_barang')
        ->label('Kategori Barang')
        ->relationship('kategoriBarang', 'nama_kategori')
        ->searchable()
        ->preload()
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
