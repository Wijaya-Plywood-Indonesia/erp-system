<?php

namespace App\Filament\Resources\ProduksiPotJeleks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiPotJelekInfoList
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->date(),

                TextEntry::make('kendala')
                    ->label('Kendala'),
            ]);
    }
}
