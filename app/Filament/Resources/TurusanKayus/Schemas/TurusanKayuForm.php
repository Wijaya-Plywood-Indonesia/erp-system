<?php

namespace App\Filament\Resources\TurusanKayus\Schemas;

use App\Models\DokumenKayu;
use App\Models\KayuMasuk;
use App\Models\KendaraanSupplierKayu;
use App\Models\SupplierKayu;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TurusanKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tgl_kayu_masuk')
                    ->label('Tanggal Kayu Masuk')
                    ->default(now()) // otomatis isi dengan waktu sekarang
                    ->readOnly()
                    ->disabled()    // tidak bisa diubah manual
                    ->required(),

                TextInput::make('seri')
                    ->label('Nomor Seri')
                    ->numeric()
                    ->required()
                    ->readOnly()
                    ->disabled()
                    ->default(function () {
                        // Ambil nilai seri terbesar dari database
                        $lastSeri = KayuMasuk::max('seri');

                        // Jika belum ada data, mulai dari 1
                        if (!$lastSeri) {
                            $nextSeri = 1;
                        } else {
                            // Jika terakhir 1000, kembali ke 1
                            $nextSeri = ($lastSeri >= 1000) ? 1 : $lastSeri + 1;
                        }

                        return $nextSeri;
                    })
                    ->hint(function () {
                        // Tampilkan hint di bawah input
                        $lastSeri = KayuMasuk::max('seri');
                        return $lastSeri
                            ? "Seri terakhir di database: {$lastSeri}"
                            : "Belum ada seri sebelumnya (akan dimulai dari 1)";
                    })
                    ->hintColor('info'),

                Select::make('id_kendaraan_supplier_kayus')
                    ->label('Kendaraan Supplier Kayu')
                    ->options(
                        KendaraanSupplierKayu::query()
                            ->get()
                            ->mapWithKeys(function ($kendaraan) {
                                return [
                                    $kendaraan->id => "{$kendaraan->nopol_kendaraan} - {$kendaraan->jenis_kendaraan}", // sesuaikan kolomnya
                                ];
                            })
                    )
                    ->searchable()
                    ->disabled()
                    ->required()
                    ->placeholder('Pilih Kendaraan Supplier'),

            ]);
    }
}
