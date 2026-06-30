<?php

namespace App\Filament\Resources\NotaKayus\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NotaKayuInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1) // <- PENTING: sekarang semua section berada di 1 kolom besar
            ->components([
                Section::make('Informasi Nota')
                    ->schema([

                        // --- Sub Section: Kayu ---
                        Section::make('Informasi Kayu')
                            ->schema([
                                TextEntry::make('kayu_seri')
                                    ->label('Seri Kayu')
                                    ->getStateUsing(
                                        fn($record) => $record->kayuMasuk->seri ?? '-'
                                    ),

                            ])
                            ->columns(1),

                        // --- Sub Section: Supplier ---
                        Section::make('Informasi Supplier')
                            ->schema([
                                TextEntry::make('supplier')
                                    ->label('Supplier')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->kayuMasuk->penggunaanSupplier->nama_supplier
                                        ?? '-'
                                    ),

                                TextEntry::make('no_telepon')
                                    ->label('Telepon Supplier')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->kayuMasuk->penggunaanSupplier->no_telepon
                                        ?? '-'
                                    ),
                            ])
                            ->columns(2),

                        // --- Sub Section: Kendaraan ---
                        Section::make('Informasi Kendaraan')
                            ->schema([
                                TextEntry::make('jenis_kendaraan')
                                    ->label('Jenis Kendaraan')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->kayuMasuk->penggunaanKendaraanSupplier->jenis_kendaraan
                                        ?? '-'
                                    ),

                                TextEntry::make('nopol')
                                    ->label('Nomor Polisi')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->kayuMasuk->penggunaanKendaraanSupplier->nopol_kendaraan
                                        ?? '-'
                                    ),

                                TextEntry::make('pemilik_kendaraan')
                                    ->label('Pemilik Kendaraan')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->kayuMasuk->penggunaanKendaraanSupplier->pemilik_kendaraan
                                        ?? '-'
                                    ),
                            ])
                            ->columns(3),

                        // --- Sub Section: Nota ---
                        Section::make('Detail Nota')
                            ->schema([
                                TextEntry::make('no_nota')
                                    ->label('Nomor Nota'),
                            ])
                            ->columns(columns: 1),
                    ])
                    ->columns(1),

                Section::make('Stakeholder')
                    ->schema([
                        TextEntry::make('penanggung_jawab')
                            ->label('Penanggung Jawab'),

                        TextEntry::make('penerima')
                            ->label('Penerima'),

                        TextEntry::make('satpam')
                            ->label('Satpam'),
                    ])
                    ->columns(3),
            ]);

    }
}
