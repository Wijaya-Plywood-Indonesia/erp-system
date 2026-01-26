<?php

namespace App\Filament\Resources\BahanPenolongHps\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BahanPenolongHpForm
{
    // --- Method untuk mendefinisikan Options Bahan ---
    public static function getBahanOptions(): array
    {
        return [
            'lem_pai' => 'Lem Pai (kg)',
            'lem_dover' => 'Lem Dover (kg)',
            'air' => 'Air',
            'hdr' => 'HDR (gr)',
            'tepung_bgs' => 'Tepung BGS (kg)',
            'tepung_pjp' => 'Tepung PJP (kg)',
            'isi_steples' => 'Isi Steples (pack)',
            'solasi_putih' => 'Solasi Putih (roll)',
            'solasi_coklat' => 'Solasi Coklat (roll)',
            'pewarna' => 'Pewarna (gr)',
            'kalsium' => 'Kalsium (gram)',
            'semen' => 'Semen (kg)',
            'lem_pvac' => 'Lem PVAC (gr)',
            'tepung_anggrek' => 'Tepung Anggrek (kg)',
        ];
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