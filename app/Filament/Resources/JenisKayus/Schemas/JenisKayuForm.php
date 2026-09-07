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
                    ->required()
                    ->unique( 
                        table: 'jenis_kayus',
                        column: 'kode_kayu',
                        ignoreRecord: true, // supaya tidak dianggap duplikat saat edit record yang sama
                    )
                    ->validationMessages([
                        'unique' => 'Kode kayu ini sudah terdaftar, silakan gunakan kode lain.',
                    ]),
                TextInput::make('nama_kayu')
                    ->required(),
                TextInput::make('keterangan')
                    ->required(),
            ]);
    }
}
