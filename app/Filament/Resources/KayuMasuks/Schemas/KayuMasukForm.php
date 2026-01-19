<?php

namespace App\Filament\Resources\KayuMasuks\Schemas;

use App\Models\DokumenKayu;
use App\Models\KayuMasuk;
use App\Models\KendaraanSupplierKayu;
use App\Models\SupplierKayu;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class KayuMasukForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('jenis_dokumen_angkut')
                    ->label('Jenis Dokumen Angkut')
                    ->options([
                        'SAKR' => 'SAKR',
                        'SK SHHK' => 'SK SHHK',
                        'Nota Angkutan' => 'Nota Angkutan',
                    ])
                    ->required()
                    ->native(false)
                    ->default('SAKR')
                    ->searchable()
                    ->preload(),
                FileUpload::make('upload_dokumen_angkut')
                    ->label('Upload Dokumen Angkut')
                    ->disk('public')
                    ->directory('kayu_masuk/dokumen')
                    ->preserveFilenames()
                    ->required()
                    ->visibility('public')

                    // --- FITUR IMAGE & SMART COMPRESSION (V3) ---
                    ->image()

                    // 1. Izinkan format WebP (Format google yang sangat kecil & tajam)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])

                    // 2. RESIZE AGRESIF (Rahasia ukuran kecil)
                    //    Mengubah resolusi kamera HP (4000px) menjadi 1024px.
                    //    Ini akan mengurangi ukuran file dari ~5MB menjadi ~200KB.
                    ->imageResizeMode('contain') // Menjaga aspek rasio (tidak gepeng/terpotong)
                    ->imageResizeTargetWidth('1024') // Cukup untuk dokumen terbaca jelas di layar laptop
                    ->imageResizeTargetHeight('1024') // Batas tinggi maksimal

                    // 3. IMAGE EDITOR (Fitur Cerdas)
                    //    Memungkinkan user memotong (crop) bagian pinggir meja/lantai yang tidak perlu.
                    //    Membuang area tidak penting = Ukuran file lebih kecil.
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null, // Bebas
                        '16:9',
                        '4:3',
                        '1:1',
                    ])

                    // 4. Konfigurasi Preview
                    ->imagePreviewHeight('250')
                    ->downloadable() // Agar admin bisa download dokumen aslinya
                    ->openable(), // Agar bisa dibuka di tab baru

                DatePicker::make('tgl_kayu_masuk')
                    ->label('Tanggal Kayu Masuk')
                    ->default(now()) // otomatis isi dengan waktu sekarang
                    ->readOnly()    // tidak bisa diubah manual
                    ->required(),

                TextInput::make('seri')
                    ->label('Nomor Seri')
                    ->numeric()
                    ->required()
                    ->dehydrated(true)
                    ->default(function () {
                        $lastSeri = KayuMasuk::max('seri');
                        return $lastSeri ? (($lastSeri >= 1000) ? 1 : $lastSeri + 1) : 1;
                    })
                    ->hint(function () {
                        $lastSeri = KayuMasuk::max('seri');
                        return $lastSeri
                            ? "Seri terakhir di database: {$lastSeri}"
                            : "Belum ada seri sebelumnya (akan dimulai dari 1)";
                    })
                    ->hintColor('info'),


                Select::make('id_supplier_kayus')
                    ->label('Supplier Kayu')
                    ->options(
                        SupplierKayu::query()
                            ->get()
                            ->mapWithKeys(function ($supplier) {
                                return [
                                    $supplier->id => "{$supplier->nama_supplier} - {$supplier->no_telepon}", // sesuaikan kolomnya
                                ];
                            })
                    )
                    ->searchable()
                    ->required()
                    ->placeholder('Pilih Supplier Kayu'),

                Select::make('id_kendaraan_supplier_kayus')
                    ->label('Kendaraan Supplier Kayu')
                    ->options(
                        KendaraanSupplierKayu::query()
                            ->get()
                            ->mapWithKeys(function ($kendaraan) {
                                return [
                                    $kendaraan->id => "{$kendaraan->nopol_kendaraan} - {$kendaraan->jenis_kendaraan} - {$kendaraan->pemilik_kendaraan}", // sesuaikan kolomnya
                                ];
                            })
                    )
                    ->searchable()
                    ->required()
                    ->placeholder('Pilih Kendaraan Supplier'),

                Select::make('id_dokumen_kayus')
                    ->label('Dokumen Kayu')
                    ->options(
                        DokumenKayu::query()
                            ->get()
                            ->mapWithKeys(function ($dokumen) {
                                return [
                                    $dokumen->id => "{$dokumen->nama_legal} - {$dokumen->dokumen_legal} (no {$dokumen->no_dokumen_legal})", // sesuaikan kolomnya
                                ];
                            })
                    )
                    ->searchable()
                    ->required()
                    ->placeholder('Pilih Dokumen Kayu'),
            ]);
    }
}
