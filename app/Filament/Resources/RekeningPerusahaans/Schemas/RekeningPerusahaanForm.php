<?php

namespace App\Filament\Resources\RekeningPerusahaans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RekeningPerusahaanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('pemilik_rekening')
                    ->label('Pemilik Rekening')
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('nama_bank')
                    ->label('Nama Bank')
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('no_rekening')
                    ->label('No Rekening')
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('atas_nama')
                    ->label('Atas Nama')
                    ->maxLength(255)
                    ->nullable(),
            ]);
    }
}
