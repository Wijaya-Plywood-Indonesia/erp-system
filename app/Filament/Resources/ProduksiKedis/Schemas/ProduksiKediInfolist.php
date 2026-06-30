<?php

namespace App\Filament\Resources\ProduksiKedis\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiKediInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal')
                    ->date(),
                TextEntry::make('status'),

                TextEntry::make('mesin.nama_mesin')
                    ->label('Mesin Kedi'),
                // TextEntry::make('created_at')
                //     ->dateTime(),
                // TextEntry::make('updated_at')
                //     ->dateTime(),
            ]);
    }
}
