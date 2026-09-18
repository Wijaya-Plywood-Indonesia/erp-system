<?php

namespace App\Filament\Resources\Lahans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LahanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_lahan')
                    ->required()
                    ->unique( 
                        table: 'lahans', 
                        column: 'kode_lahan', 
                        ignoreRecord: true, // supaya tidak dianggap duplikat saat edit record yang sama 
                    )
                    ->validationMessages([
                        'unique' => 'Nama lahan ini sudah terdaftar, silakan gunakan nama lain.',
                    ]),
                TextInput::make('nama_lahan')
                    ->required(),
                TextInput::make('detail'),
            ]);
    }
}
