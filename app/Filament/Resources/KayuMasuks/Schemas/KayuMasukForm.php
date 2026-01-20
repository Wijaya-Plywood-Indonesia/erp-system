<?php

namespace App\Filament\Resources\KayuMasuks\Schemas;

use App\Models\DokumenKayu;
use App\Models\KayuMasuk;
use App\Models\KendaraanSupplierKayu;
use App\Models\SupplierKayu;
use Filament\Forms\Components\DatePicker;
use App\Forms\Components\CompressedFileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Carbon\Carbon;           // Import Library Tanggal
use Filament\Schemas\Components\Utilities\Get; // Import Get

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

                CompressedFileUpload::make('upload_dokumen_angkut')
                    ->label('Upload Dokumen Angkut')
                    ->disk('public')
                    ->directory('kayu_masuk/dokumen')
                    ->required()
                    ->visibility('public')
                    ->imageEditor()
                    // Hapus preserveFilenames() karena kita akan membuat nama baru

                    // =========================================================
                    // 🪄 LOGIKA PENAMAAN OTOMATIS
                    // =========================================================
                    ->fileName(function (Get $get) {
                        // 1. Ambil Nilai dari Form
                        $tgl = $get('tgl_kayu_masuk');
                        $seri = $get('seri');
                        $supplierId = $get('id_supplier_kayus');

                        // 2. Format Tanggal (YYYY-MM-DD)
                        // Jika kosong, pakai tanggal hari ini sebagai default
                        $tglFormatted = $tgl ? Carbon::parse($tgl)->format('Y-m-d') : now()->format('Y-m-d');

                        // 3. Cari Nama Supplier
                        $namaSupplier = 'Tanpa-Supplier';
                        if ($supplierId) {
                            $supplier = SupplierKayu::find($supplierId);
                            if ($supplier) {
                                // Ambil nama, sistem di CompressedFileUpload nanti otomatis
                                // akan mengubah spasi menjadi tanda hubung (slug)
                                $namaSupplier = $supplier->nama_supplier;
                            }
                        }

                        // 4. Pastikan Seri Ada
                        $noSeri = $seri ? "Seri-{$seri}" : 'Tanpa-Seri';

                        // 5. Gabungkan Menjadi Nama File
                        // Hasil: 2026-01-19_CV-Maju-Jaya_Seri-105
                        return "{$tglFormatted}_{$namaSupplier}_{$noSeri}";
                    }),

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
