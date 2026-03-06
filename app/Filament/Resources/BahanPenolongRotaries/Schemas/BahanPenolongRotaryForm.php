<?php

namespace App\Filament\Resources\BahanPenolongRotaries\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class BahanPenolongRotaryForm
{
    public static function getBahanOptions(): array
    {
        return [
            'reeling_tape' => 'Reeling Tape (roll)',
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
