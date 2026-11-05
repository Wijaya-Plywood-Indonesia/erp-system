<?php

namespace App\Filament\Resources\Bks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('no_nota')->label('No. Nota'),
            TextEntry::make('kategori')->badge()->formatStateUsing(fn ($state) => strtoupper($state)),
            TextEntry::make('status')->label('Status')->badge()
                ->formatStateUsing(fn ($state, $record) => $record->labelStatus())
                ->color(fn ($state, $record) => $record->warna()),
            TextEntry::make('tanggal')->date(),
            TextEntry::make('tujuan_nota')->label('Kepada'),
            TextEntry::make('alamat')->placeholder('-'),
            TextEntry::make('keterangan')->placeholder('-'),
            TextEntry::make('pembuat.name')->label('Dibuat Oleh')->placeholder('-'),
            TextEntry::make('validator.name')->label('Divalidasi Oleh')->placeholder('Belum divalidasi'),
            TextEntry::make('bml_ref')->label('BML (barang kembali)')
                ->getStateUsing(fn ($record) => $record->bmTerkait()->where('kategori', 'bml')->first()?->no_nota)
                ->placeholder('-')
                ->color('primary')->weight('bold')
                ->action(\App\Filament\Resources\Bks\Actions\ReferensiBmlAction::make())
                ->visible(fn ($record) => $record->kategori === 'bkl'),
        ])->columns(3);
    }
}
