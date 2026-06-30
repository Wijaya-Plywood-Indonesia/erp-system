<?php

namespace App\Filament\Resources\JenisBarangs\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class JenisBarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_jenis_barang')
                    ->label('Kode Jenis barang')
                    ->required()
                    ->maxLength(255),

                TextInput::make('nama_jenis_barang')
                    ->label('Nama Jenis barang')
                    ->required()
                    ->maxLength(255)
            ]);
    }
}
