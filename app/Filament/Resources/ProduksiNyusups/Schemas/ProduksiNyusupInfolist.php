<?php

namespace App\Filament\Resources\ProduksiNyusups\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiNyusupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal_produksi')
                    ->date(),
                TextEntry::make('kendala'),
                // TextEntry::make('created_at')
                //     ->dateTime(),
                // TextEntry::make('updated_at')
                //     ->dateTime(),
            ]);
    }
}
