<?php

namespace App\Filament\Resources\Perusahaans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PerusahaanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')
                    ->label('Kode Perusahaan')
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('nama')
                    ->label('Nama Perusahaan')
                    ->required(),

                Textarea::make('alamat')
                    ->label('Alamat')
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('telepon')
                    ->label('Nomor Telepon')
                    ->tel()
                    ->maxLength(20),

                TextInput::make('email')
                    ->label('Email Perusahaan')
                    ->email()
                    ->nullable(),
            ]);
    }
}
