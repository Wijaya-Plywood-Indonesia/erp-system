<?php

namespace App\Filament\Resources\Perusahaans\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PerusahaanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('kode'),
                TextEntry::make('nama'),
                TextEntry::make('telepon'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
