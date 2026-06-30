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
                    ->date()
                    ->label('Tanggal Masuk'),
                TextEntry::make('status'),
                TextEntry::make('rencana_bongkar')
                    ->date(),
                TextEntry::make('tanggal_bongkar')
                    ->date(),

                TextEntry::make('mesin.nama_mesin')
                    ->label('Mesin Kedi'),
                TextEntry::make('kendala'),

                // TextEntry::make('created_at')
                //     ->dateTime(),
                // TextEntry::make('updated_at')
                //     ->dateTime(),
            ]);
    }
}
