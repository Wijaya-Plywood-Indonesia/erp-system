<?php

namespace App\Filament\Resources\KategoriMesins\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class KategoriMesinInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('kode_kategori'),
                TextEntry::make('nama_kategori_mesin'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
