<?php

namespace App\Filament\Resources\Criterias\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CriteriaInfolist
{
    /**
     * Konfigurasi Schema Infolist untuk Master Kriteria.
     * Mengikuti pola modular Filament v4 untuk detail view yang rapi.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // --- SECTION 1: DETAIL TEKNIS ---
            Section::make('Informasi Kriteria')
                ->description('Detail parameter kriteria yang tersimpan dalam sistem.')
                ->schema([
                    TextEntry::make('kategori.nama_kategori')
                        ->label('Kategori Barang')
                        ->badge()
                        ->color('gray'),

                    TextEntry::make('nama_kriteria')
                        ->label('Nama Kriteria / Pertanyaan')
                        ->columnSpan(2),
                ])->columns(2),

            // --- SECTION 2: PARAMETER SISTEM PAKAR ---
            Section::make('Konfigurasi Mesin Inferensi')
                ->description('Pengaturan bobot dan urutan untuk sistem pakar forward chaining.')
                ->schema([
                    TextEntry::make('bobot')
                        ->label('Bobot Penalti')
                        ->numeric(2)
                        ->badge()
                        ->color('amber'),

                    TextEntry::make('urutan')
                        ->label('Urutan Tampil'),

                    IconEntry::make('is_active')
                        ->label('Status Kriteria')
                        ->boolean(),

                    TextEntry::make('deskripsi')
                        ->label('Deskripsi Lengkap / Instruksi Operator')
                        ->markdown() // Mendukung format teks kaya jika ada
                        ->columnSpanFull(),
                ])->columns(3),

            // --- SECTION 3: METADATA ---
            Section::make('Audit Trail')
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Dibuat Pada')
                        ->dateTime('d M Y H:i:s'),

                    TextEntry::make('updated_at')
                        ->label('Terakhir Diperbarui')
                        ->dateTime('d M Y H:i:s'),
                ])->columns(2)
                ->collapsed(), // Disembunyikan secara default agar fokus pada konten utama
        ]);
    }
}
