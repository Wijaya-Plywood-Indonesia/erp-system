<?php

namespace App\Filament\Resources\BahanDempuls\Schemas;

use App\Models\BahanPenolongProduksi;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class BahanDempulForm
{

    public static function getBahanOptions(): array
    {
        return BahanPenolongProduksi::where('kategori_produksi', 'dempul')
            ->get()
            ->mapWithKeys(fn($item) => [
                $item->id => "{$item->nama_bahan_penolong} ({$item->satuan})"
            ])
            ->toArray();
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('nama_bahan')
                    ->label('Nama Bahan')
                    // Menggunakan method static untuk options
                    ->options(self::getBahanOptions())
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
