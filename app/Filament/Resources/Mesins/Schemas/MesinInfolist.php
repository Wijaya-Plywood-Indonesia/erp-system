<?php

namespace App\Filament\Resources\Mesins\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MesinInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('kategoriMesin.nama_kategori_mesin')
                    ->label('Kategori Mesin'),
                TextEntry::make('nama_mesin'),
                TextEntry::make('ongkos_mesin')
                    ->numeric(),
                TextEntry::make('no_akun'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
