<?php

namespace App\Filament\Resources\NotaBarangKeluars\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class NotaBarangKeluarInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal')
                    ->date(),
                TextEntry::make('no_nota'),
                TextEntry::make('tujuan_nota'),
                TextEntry::make('pembuat.name')
                    ->label('Dibuat Oleh')
                    ->placeholder('Tidak diketahui'),
                TextEntry::make('validator_status')
                    ->label('Divalidasi Oleh')
                    ->placeholder("Belum Divalidasi")
                    ->getStateUsing(function ($record) {
                        return $record->validator?->name ?? null;
                    })
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ?? 'Belum divalidasi')
                    ->color(fn($state) => $state ? 'success' : 'danger'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
