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
                    ->required()
                    ->unique(
                        table: 'kategori_mesins',
                        column: 'kode_kategori',
                        ignoreRecord: true, // supaya tidak dianggap duplikat saat edit record yang sama
                    )
                    ->validationMessages([
                        'unique' => 'Kode ini sudah terdaftar, silakan gunakan kode lain.',
                    ]),
                TextInput::make('nama_kategori_mesin')
                    ->required(),
            ]);
    }
}
