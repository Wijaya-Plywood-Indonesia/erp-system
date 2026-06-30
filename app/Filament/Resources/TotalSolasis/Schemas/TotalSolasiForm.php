<?php

namespace App\Filament\Resources\TotalSolasis\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TotalSolasiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('total')
                    ->label('Total Solasi')
                    ->integer()
            ]);
    }
}
