<?php

namespace App\Filament\Resources\Targets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TargetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('mesin.nama_mesin')
                    ->numeric(),
                TextEntry::make('ukuranModel.dimensi')
                    ->numeric(),
                TextEntry::make('jenisKayu.nama_kayu')
                    ->numeric(),
                TextEntry::make('ukuran'),
                TextEntry::make('target')
                    ->numeric(),
                TextEntry::make('orang')
                    ->numeric(),
                TextEntry::make('jam')
                    ->numeric(),
                TextEntry::make('targetperjam')
                    ->numeric(),
                TextEntry::make('targetperorang')
                    ->numeric(),
                TextEntry::make('gaji')
                    ->numeric(),
                TextEntry::make('potongan')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('status'),
            ]);
    }
}
