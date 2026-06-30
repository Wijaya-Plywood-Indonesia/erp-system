<?php

namespace App\Filament\Resources\JenisKayus\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JenisKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_kayu')
                    ->required(),
                TextInput::make('nama_kayu')
                    ->required(),
                TextInput::make('keterangan')
                    ->required(),
            ]);
    }
}
