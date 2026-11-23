<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->required(),
                TextInput::make('telepon')
                    ->tel()
                    ->maxLength(50)
                    ->nullable(),
                TextInput::make('npwp')
                    ->label('NPWP')
                    ->placeholder('Contoh: 00.000.000.0-000.000')
                    ->maxLength(100)
                    ->nullable(),
                Textarea::make('alamat')
                    ->columnSpanFull(),
            ]);
    }
}
