<?php

namespace App\Filament\Resources\KendaraanSupplierKayus\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class KendaraanSupplierKayuInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('nopol_kendaraan'),
                TextEntry::make('jenis_kendaraan'),
                TextEntry::make('pemilik_kendaraan'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
