<?php

namespace App\Filament\Resources\BahanProduksis\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class BahanProduksiForm
{
    public static function getBahanOptions(): array
    {
        return [
            'lem_dover' => 'Lem Dover (kg)',
            'tepung' => 'Tepung (kg)',
            'lem_pai' => 'Lem Pai (kg)',
            'aruki' => 'Aruki'
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
