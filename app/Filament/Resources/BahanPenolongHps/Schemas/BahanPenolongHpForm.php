<?php

namespace App\Filament\Resources\BahanPenolongHps\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BahanPenolongHpForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('nama_bahan')
                    ->label('Nama Bahan')
                    ->options(
                        fn() =>
                        \App\Models\BahanPenolongProduksi::where('kategori_produksi', 'hot_press')
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
