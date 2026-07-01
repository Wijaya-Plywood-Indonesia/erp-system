<?php

namespace App\Filament\Resources\IndukAkuns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IndukAkunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
                TextInput::make('kode_induk_akun')
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('nama_induk_akun')
                    ->required(),

                Select::make('saldo_normal')
                    ->options([
                        'debet' => 'Debet',
                        'kredit' => 'Kredit',
                    ])
                    ->required(),

                Select::make('status')
                    ->options([
                        'aktif' => 'Aktif',
                        'nonaktif' => 'Nonaktif',
                    ])
                    ->required(),

                Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }
}
