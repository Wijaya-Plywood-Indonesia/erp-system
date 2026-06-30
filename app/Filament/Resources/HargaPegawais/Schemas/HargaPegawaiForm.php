<?php

namespace App\Filament\Resources\HargaPegawais\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HargaPegawaiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('harga')
                    ->label('Harga Pegawai')
                    ->integer()
            ]);
    }
}
