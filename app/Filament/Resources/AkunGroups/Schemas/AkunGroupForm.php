<?php

namespace App\Filament\Resources\AkunGroups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AkunGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama Grup')
                    ->required(),

                Select::make('parent_id')
                    ->label('Parent Grup')
                    ->relationship('parent', 'nama')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('order')
                    ->required()
                    ->numeric(),

                Toggle::make('hidden')
                    ->label('Sembunyikan')
                    ->default(false),
            ]);
    }
}
