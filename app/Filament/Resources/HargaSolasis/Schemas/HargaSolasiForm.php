<?php

namespace App\Filament\Resources\HargaSolasis\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HargaSolasiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('harga')
                    ->label('Harga')
                    ->integer()
            ]);
    }
}
