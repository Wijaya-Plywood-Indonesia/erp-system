<?php

namespace App\Filament\Resources\Bms\Schemas;

use App\Filament\Resources\Bks\BkResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BmInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('no_nota')->label('No. Nota'),
            TextEntry::make('kategori')->badge()->formatStateUsing(fn ($state) => strtoupper($state)),
            TextEntry::make('status')->badge()
                ->formatStateUsing(fn ($state) => $state === 'divalidasi' ? 'Divalidasi' : 'Draft')
                ->color(fn ($state) => $state === 'divalidasi' ? 'success' : 'gray'),
            TextEntry::make('tanggal')->date(),
            TextEntry::make('dari')->placeholder('-'),
            TextEntry::make('bkReferensi.no_nota')->label('Referensi BK')->placeholder('-')
                ->color('primary')->weight('bold')
                ->action(\App\Filament\Resources\Bms\Actions\ReferensiBkAction::make()),
            TextEntry::make('keterangan')->placeholder('-'),
            TextEntry::make('pembuat.name')->label('Dibuat Oleh')->placeholder('-'),
            TextEntry::make('validator.name')->label('Divalidasi Oleh')->placeholder('Belum divalidasi'),
        ])->columns(3);
    }
}
