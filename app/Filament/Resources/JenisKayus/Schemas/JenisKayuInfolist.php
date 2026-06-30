<?php

namespace App\Filament\Resources\JenisKayus\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class JenisKayuInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('kode_kayu'),
                TextEntry::make('nama_kayu'),
                TextEntry::make('keterangan'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
