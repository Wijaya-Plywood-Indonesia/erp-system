<?php

namespace App\Filament\Resources\GradeRules\Schemas;

use App\Models\Criteria;
use App\Models\Grade;
use App\Models\KategoriBarang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class GradeRuleForm
{
    /**
     * Konfigurasi Schema Form untuk Aturan Grade (Knowledge Base).
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // --- SECTION 1: PEMILIHAN ENTITAS ---
            Section::make('Konfigurasi Aturan')
                ->description('Tentukan hubungan antara Grade dan Kriteria pemeriksaan.')
                ->schema([
                    // Filter pembantu untuk mempersempit pilihan Grade & Kriteria
                    Select::make('kategori_filter')
                        ->label('Filter Kategori')
                        ->options(KategoriBarang::pluck('nama_kategori', 'id'))
                        ->live()
                        ->dehydrated(false)
                        ->native(false)
                        ->helperText('Pilih kategori untuk memfilter daftar di bawah.'),

                    Grid::make(2)->schema([
                        Select::make('id_grade')
                            ->label('Target Grade')
                            ->options(function (Get $get) {
                                $kategoriId = $get('kategori_filter');
                                if (! $kategoriId) return Grade::pluck('nama_grade', 'id');
                                return Grade::where('id_kategori_barang', $kategoriId)
                                    ->pluck('nama_grade', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),

                        Select::make('id_criteria')
                            ->label('Kriteria / Parameter')
                            ->options(function (Get $get) {
                                $kategoriId = $get('kategori_filter');
                                if (! $kategoriId) return Criteria::pluck('nama_kriteria', 'id');
                                return Criteria::where('id_kategori_barang', $kategoriId)
                                    ->orderBy('urutan')
                                    ->pluck('nama_kriteria', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                    ]),
                ]),

            // --- SECTION 2: LOGIKA SISTEM PAKAR ---
            Section::make('Logika Kondisi & Skor')
                ->description('Pengaturan poin yang akan diproses oleh Inference Engine.')
                ->schema([
                    Select::make('kondisi')
                        ->label('Kebijakan Cacat')
                        ->options([
                            'not_allowed' => '🚫 Tidak Boleh Ada (Fatal)',
                            'conditional' => '⚠️  Boleh Ada dengan Toleransi (Parsial)',
                            'allowed'     => '✅ Diizinkan Penuh (Bebas)',
                        ])
                        ->required()
                        ->live()
                        ->default('not_allowed'),

                    Textarea::make('penjelasan')
                        ->label('Dasar Keputusan (Alasan)')
                        ->placeholder('Contoh: Untuk Grade BBCC, celah tidak boleh lebih dari 2mm...')
                        ->rows(3)
                        ->required()
                        ->columnSpanFull(),

                    Grid::make(2)->schema([
                        TextInput::make('poin_lulus')
                            ->label('Skor Maksimal (Lulus)')
                            ->numeric()
                            ->default(100)
                            ->required()
                            ->helperText('Poin jika fakta pemeriksaan adalah "TIDAK ADA CACAT".'),

                        TextInput::make('poin_parsial')
                            ->label('Skor Parsial')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->visible(fn(Get $get) => $get('kondisi') === 'conditional')
                            ->helperText('Poin jika fakta adalah "ADA CACAT" namun kondisi bersyarat.'),
                    ]),
                ])->columns(1),
        ]);
    }
}
