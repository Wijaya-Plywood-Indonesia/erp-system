<?php

namespace App\Filament\Resources\BahanProduksis\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class BahanProduksiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('nama_bahan')
                    ->label('Nama Bahan')
                    ->options(
                        fn() =>
                        \App\Models\BahanPenolongProduksi::where('kategori_produksi', 'joint')
                            ->get()
                            ->mapWithKeys(fn($item) => [
                                $item->nama_bahan_penolong =>
                                $item->nama_bahan_penolong . ' (' . $item->satuan . ')'
                            ])
                            ->toArray()
                    )
                    ->required()
                    ->native(false)
                    ->searchable(),

                TextInput::make('jumlah')
                    ->label('Banyak')
                    ->required()
                    ->numeric(),
            ]);
    }
}
