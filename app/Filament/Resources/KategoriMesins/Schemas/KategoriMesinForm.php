<?php

namespace App\Filament\Resources\KategoriMesins\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class KategoriMesinForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_kategori')
                    ->required(),
                TextInput::make('nama_kategori_mesin')
                    ->required(),
            ]);
    }
}
