<?php

namespace App\Filament\Resources\Criterias\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CriteriaForm
{
    /**
     * Konfigurasi Schema Form untuk Master Kriteria.
     * Menggunakan pola modular sesuai standar Filament v4.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // --- SECTION 1: IDENTITAS KRITERIA ---
            Section::make('Informasi Dasar Kriteria')
                ->description('Tentukan parameter utama yang akan digunakan dalam kuesioner pemeriksaan.')
                ->schema([
                    Select::make('id_kategori_barang')
                        ->label('Kategori Barang')
                        ->relationship('kategoriBarang', 'nama_kategori') // Pastikan relasi 'kategori' ada di Model Criterion
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(2),

                    TextInput::make('nama_kriteria')
                        ->label('Pertanyaan')
                        ->placeholder('Contoh: Pecah Terbuka')
                        ->required()
                        ->columnSpan(2),
                ])->columns(2),

            // --- SECTION 2: KONFIGURASI SISTEM PAKAR ---
            Section::make('Konfigurasi Penilaian & Tampilan')
                ->description('Pengaturan bobot untuk mesin inferensi dan urutan kuesioner.')
                ->schema([
                    Textarea::make('deskripsi')
                        ->label('Instruksi Teknis (Deskripsi)')
                        ->placeholder('Berikan penjelasan detail kriteria ini untuk operator lapangan...')
                        ->rows(3)
                        ->columnSpanFull(),

                    TextInput::make('bobot')
                        ->label('Bobot Penalti Dasar')
                        ->numeric()
                        ->step(0.1)
                        ->default(1.0)
                        ->required()
                        ->helperText('Nilai pengali untuk perhitungan skor akhir.'),

                    TextInput::make('urutan')
                        ->label('Urutan Tampil')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    Toggle::make('is_active')
                        ->label('Status Aktif')
                        ->default(true)
                        ->helperText('Hanya kriteria aktif yang akan muncul di Wizard Grading.')
                        ->required(),
                ])->columns(3),
        ]);
    }
}
