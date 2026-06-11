<?php

namespace App\Filament\Resources\HargaVeneers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class HargaVeneerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('ukuran')
                    ->label('Ukuran / Posisi')
                    ->formatStateUsing(fn(string $state) => [
                        'faceback' => 'Faceback',
                        'face' => 'Face',
                        'back' => 'Back',
                        'core' => 'Core',
                        'ppc_faceback' => '0.5 PPC',
                        'ppc_core' => '3.7 PPC',
                    ][$state] ?? ucfirst($state)),
                TextEntry::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu'),
                TextEntry::make('harga_basah')
                    ->label('Harga Basah')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                TextEntry::make('harga_kering')
                    ->label('Harga Kering')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                TextEntry::make('harga_jadi')
                    ->label('Harga Jadi')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                TextEntry::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime('d M Y H:i'),
                TextEntry::make('updated_at')
                    ->label('Tanggal Diperbarui')
                    ->dateTime('d M Y H:i'),
            ]);
    }
}
