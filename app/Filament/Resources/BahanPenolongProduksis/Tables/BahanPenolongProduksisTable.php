<?php

namespace App\Filament\Resources\BahanPenolongProduksis\Tables;

use App\Filament\Resources\BahanPenolongProduksis\Schemas\BahanPenolongProduksiForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BahanPenolongProduksisTable
{
    public static function configure(Table $table): Table
    {
        $produksiOptions = BahanPenolongProduksiForm::getProduksiOptions();
        return $table
            ->columns([
                TextColumn::make('nama_bahan_penolong')
                    ->searchable()
                    ->label('Nama bahan penolong'),
                 
                TextColumn::make('satuan')
                    ->label('Satuan'),
                
                TextColumn::make('kategori_produksi')
                    ->searchable()
                    ->label('Kategori produksi')
                    // Gunakan formatStateUsing untuk menampilkan label panjang
                    ->formatStateUsing(fn (string $state): string => 
                        $produksiOptions[$state] ?? $state 
                    ),
                    
                TextColumn::make('harga')
                    ->label('Harga'),
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
