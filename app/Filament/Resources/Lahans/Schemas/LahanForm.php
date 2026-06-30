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
                    ->required(),
                TextInput::make('nama_lahan')
                    ->required(),
                TextInput::make('detail'),
            ]);
    }
}
