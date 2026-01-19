<?php

namespace App\Filament\Resources\DokumenKayus\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get; // Import Get
use App\Forms\Components\CompressedFileUpload; // Gunakan Komponen Custom Anda
use Illuminate\Support\Str;

class DokumenKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Dokumen Sumber Kayu')
                    ->description('Lengkapi dokumen legalitas dan identitas sumber kayu.')
                    ->schema([

                        TextInput::make('nama_legal')
                            ->label('Nama Sesuai KTP dan Dokumen Legal')
                            ->required()
                            ->live(onBlur: true) // 🔥 PENTING: Agar nama terbaca saat upload
                            ->afterStateUpdated(function ($state) {
                                // Optional: logic tambahan
                            }),

                        Select::make('dokumen_legal')
                            ->label('Jenis Dokumen Legal')
                            ->options([
                                'SHM' => 'Sertifikat Hak Milik (SHM)',
                                'Letter C' => 'Letter C',
                            ])
                            ->live() // 🔥 PENTING: Agar jenis dokumen terbaca saat upload
                            ->native(false),

                        TextInput::make('no_dokumen_legal')
                            ->label('No di Dokumen Legal'),

                        // =====================================
                        // 1. UPLOAD DOKUMEN LEGAL
                        // Format: SHM_Budi-Santoso.webp
                        // =====================================
                        CompressedFileUpload::make('upload_dokumen')
                            ->label('Upload Dokumen Legal')
                            ->disk('public')
                            ->directory('sumber-kayu/dokumen')
                            ->nullable()
                            ->imageEditor()
                            ->fileName(function (Get $get) {
                                // Ambil Jenis Dokumen
                                $jenis = $get('dokumen_legal') ?: 'Dokumen';

                                // Ambil Nama Legal
                                $nama = $get('nama_legal') ?: 'Tanpa-Nama';

                                // Gabungkan: "SHM_Budi-Santoso"
                                // (Spasi otomatis jadi "-" oleh Str::slug di dalam komponen)
                                return "{$jenis}_{$nama}";
                            }),

                        // =====================================
                        // 2. UPLOAD KTP
                        // Format: KTP_Budi-Santoso.webp
                        // =====================================
                        CompressedFileUpload::make('upload_ktp')
                            ->label('Upload KTP Pemilik')
                            ->disk('public')
                            ->directory('sumber-kayu/ktp')
                            ->nullable()
                            ->imageEditor()
                            ->fileName(function (Get $get) {
                                $nama = $get('nama_legal') ?: 'Tanpa-Nama';
                                return "KTP_{$nama}";
                            }),

                        // =====================================
                        // 3. FOTO LOKASI
                        // Format: [Nama Asli].webp
                        // =====================================
                        CompressedFileUpload::make('foto_lokasi')
                            ->label('Foto Lokasi')
                            ->disk('public')
                            ->directory('sumber-kayu/foto-lokasi')
                            ->imageEditor()
                            // Tidak pakai ->fileName() agar kembali ke default (UUID atau nama asli jika dipreserve)
                            // Namun karena CompressedFileUpload DEFAULT-nya menggunakan UUID jika fileName kosong,
                            // Kita bisa memaksa menggunakan nama asli file yang diupload.
                            ->storeFileUsing(function ($file) {
                                // Kita override sedikit logic di sini KHUSUS untuk field ini
                                // Atau biarkan default (UUID) jika "nama_file" maksudnya nama acak.

                                // JIKA MAKSUD ANDA NAMA ASLI DARI KOMPUTER USER:
                                // Itu agak sulit karena livewire merename file jadi temporary hash.
                                // Saran terbaik: Gunakan UUID (default) atau nama tempat.

                                // Opsi: Gunakan Nama Tempat
                                // return "Lokasi_BlaBla";
                            })
                            // KITA GUNAKAN NAMA TEMPAT SEBAGAI NAMA FILE
                            ->fileName(function (Get $get) {
                                $tempat = $get('nama_tempat');
                                return $tempat ? "Lokasi_{$tempat}" : null;
                                // Jika null, komponen akan generate UUID (default).
                            }),
                    ]),

                /** =========================
                 * 📍 BAGIAN DATA LOKASI
                 * ========================= */
                Section::make('Informasi Lokasi Sumber Kayu')
                    ->description('Isi alamat lengkap dan tandai lokasi di peta.')
                    ->schema([
                        TextInput::make('nama_tempat')
                            ->label('Nama Tempat / Area')
                            ->nullable()
                            ->live(onBlur: true) // Supaya bisa dipakai untuk nama foto lokasi
                            ->maxLength(255),

                        Textarea::make('alamat_lengkap')
                            ->label('Alamat Lengkap')
                            ->nullable()
                            ->rows(3),

                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->reactive()
                            ->nullable(),

                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->reactive()
                            ->nullable(),
                    ]),
            ]);
    }
}
