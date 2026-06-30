<?php

namespace App\Filament\Resources\Absensis\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AbsensiInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal')
                    ->date(),
                TextEntry::make('uploaded_by'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
