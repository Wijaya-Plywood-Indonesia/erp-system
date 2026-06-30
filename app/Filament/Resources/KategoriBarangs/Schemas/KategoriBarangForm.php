<?php

namespace App\Filament\Resources\KategoriBarangs\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class KategoriBarangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_kategori')
                    ->label('Nama Kategori')
                    ->required()
                    ->maxLength(255)
            ]);
    }
}
