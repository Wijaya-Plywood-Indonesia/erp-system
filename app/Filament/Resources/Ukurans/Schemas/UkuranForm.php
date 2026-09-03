<?php

namespace App\Filament\Resources\Ukurans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Query\Builder as QueryBuilder;

class UkuranForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('panjang')
                    ->label('Panjang (cm)')
                    ->required()
                    ->numeric()
                    ->live(onBlur: true)
                    ->unique(
                        table: 'ukurans',
                        column: 'panjang',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule, $get) => $rule
                            ->where('lebar', $get('lebar'))
                            ->where('tebal', $get('tebal')),
                    )
                    ->validationMessages([
                        'unique' => 'Ukuran dengan panjang, lebar, dan tebal ini sudah ada.',
                    ]),

                TextInput::make('lebar')
                    ->label('Lebar (cm)')
                    ->required()
                    ->numeric()
                    ->live(onBlur: true),

                TextInput::make('tebal')
                    ->label('Tebal (cm)')
                    ->required()
                    ->numeric()
                    ->live(onBlur: true),
            ]);
    }
}