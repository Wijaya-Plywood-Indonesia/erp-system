<?php

namespace App\Filament\Resources\KayuMasuks\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KayuMasukInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 🔹 Bagian 1: Informasi Dokumen Angkut
                Section::make('Informasi Dokumen Angkut')
                    ->schema([
                        TextEntry::make('jenis_dokumen_angkut')
                            ->label('Jenis Dokumen Angkut')
                            ->badge()
                            ->color('info'),

                        ImageEntry::make('upload_dokumen_angkut')
                            ->label('File Dokumen Angkut')
                            ->badge()
                            ->square()
                            ->size(50),

                        TextEntry::make('tgl_kayu_masuk')
                            ->label('Tanggal Kayu Masuk')
                            ->date()
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('seri')
                            ->label('Seri Kayu')
                            ->numeric()
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('kubikasi')
                            ->label('Kubikasi Sekarang (m³)')
                            ->formatStateUsing(fn($state) => number_format((float) $state, 6, ',', '.'))
                            ->suffix(' m³')
                            ->badge()
                            ->color('gray'),

                    ])
                    ->columns(2),

                // 🔹 Bagian 2: Relasi Supplier Kayu
                Section::make('Data Supplier Kayu')
                    ->schema([
                        TextEntry::make('penggunaanSupplier.nama_supplier')
                            ->label('Nama Supplier')
                            ->badge()
                            ->color('success'),

                        TextEntry::make('penggunaanSupplier.no_telepon')
                            ->label('Nomor Telepon')
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(2),

                // 🔹 Bagian 3: Relasi Kendaraan Supplier
                Section::make('Data Kendaraan Supplier')
                    ->schema([
                        TextEntry::make('penggunaanKendaraanSupplier.nopol_kendaraan')
                            ->label('Nomor Polisi')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('penggunaanKendaraanSupplier.jenis_kendaraan')
                            ->label('Jenis Kendaraan')
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(2),

                // 🔹 Bagian 4: Dokumen Legal Kayu
                Section::make('Data Dokumen Kayu')
                    ->schema([
                        TextEntry::make('penggunaanDokumenKayu.nama_legal')
                            ->label('Nama Dokumen Legal')
                            ->badge()
                            ->color('success'),

                        TextEntry::make('dokumen_info')
                            ->label('Dokumen Legal')
                            ->default(
                                fn($record) =>
                                $record->penggunaanDokumenKayu
                                    ? ($record->penggunaanDokumenKayu->dokumen_legal ?? '-')
                                    . 'No : (' . ($record->penggunaanDokumenKayu->no_dokumen_legal ?? '-') . ')'
                                    : '-'
                            )
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(2),

                // 🔹 Bagian 5: Metadata Record

            ]);
    }
}
