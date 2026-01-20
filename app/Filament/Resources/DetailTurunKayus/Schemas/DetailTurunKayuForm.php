<?php

namespace App\Filament\Resources\DetailTurunKayus\Schemas;

use App\Models\Pegawai;
use App\Models\KayuMasuk;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Log;
use App\Services\WatermarkService;
use Filament\Schemas\Components\Utilities\Get; // Import Get
use App\Forms\Components\CompressedFileUpload;
use Carbon\Carbon;

class DetailTurunKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('id_kayu_masuk')
                    ->label('Kayu Masuk')
                    ->options(
                        KayuMasuk::query()
                            ->with(['penggunaanSupplier', 'penggunaanKendaraanSupplier'])
                            ->get()
                            ->mapWithKeys(function ($kayu) {
                                $supplier = $kayu->penggunaanSupplier?->nama_supplier ?? '—';
                                $nopol = $kayu->penggunaanKendaraanSupplier?->nopol_kendaraan ?? '—';
                                $jenis = $kayu->penggunaanKendaraanSupplier?->jenis_kendaraan ?? '—';
                                $seri = $kayu->seri ?? '—';

                                return [
                                    $kayu->id => "$supplier | $nopol ($jenis) | Seri: $seri"
                                ];
                            })
                            ->toArray()
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Pilih kayu masuk'),

                // STATUS
                Select::make('status')
                    ->label('Status')
                    ->options(function (callable $get) {
                        $kayuMasukId = $get('id_kayu_masuk');

                        if (!$kayuMasukId) {
                            return [
                                'menunggu' => 'Menunggu',
                                'selesai' => 'Selesai',
                            ];
                        }

                        $kayuMasuk = KayuMasuk::with('penggunaanKendaraanSupplier')
                            ->find($kayuMasukId);

                        $jenis = $kayuMasuk?->penggunaanKendaraanSupplier?->jenis_kendaraan;

                        // Jika kendaraan Fuso → dua status
                        if ($jenis === 'Fuso') {
                            return [
                                'menunggu' => 'Menunggu',
                                'selesai' => 'Selesai',
                            ];
                        }

                        // Selain Fuso → hanya selesai
                        return [
                            'selesai' => 'Selesai',
                        ];
                    })
                    ->reactive()
                    ->native(false)
                    ->required(),



                TextInput::make('nama_supir')
                    ->label('Nama Supir')
                    ->required(),

                TextInput::make('jumlah_kayu')
                    ->label('Jumlah Kayu')
                    ->required()
                    ->numeric(),

                CompressedFileUpload::make('foto')
                    ->label('Foto Bukti')
                    ->imageEditor()
                    ->disk('public')
                    ->directory('turun-kayu/foto-bukti')
                    ->visibility('public')
                    ->required()
                    ->fileName(function (Get $get) {
                        // 1. Ambil Nama Supir
                        $namaSupir = $get('nama_supir') ?: 'Tanpa-Nama';

                        // 2. Ambil Data dari Kayu Masuk (Seri & Tanggal)
                        $kayuMasukId = $get('id_kayu_masuk');

                        // Default value
                        $noSeri = 'Tanpa-Seri';
                        $tanggalTurun = now()->format('Y-m-d'); // Default hari ini jika data tidak ketemu

                        if ($kayuMasukId) {
                            $kayu = KayuMasuk::find($kayuMasukId);
                            if ($kayu) {
                                // Ambil Seri
                                $noSeri = "Seri-{$kayu->seri}";

                                // Ambil Tanggal Kayu Masuk (Produksi)
                                if (!empty($kayu->tgl_kayu_masuk)) {
                                    $tanggalTurun = Carbon::parse($kayu->tgl_kayu_masuk)->format('Y-m-d');
                                }
                            }
                        }

                        // 3. Gabungkan: "2026-01-19_Supir-Budi_Seri-105.webp"
                        return "{$tanggalTurun}_Supir-{$namaSupir}_{$noSeri}";
                    }),
            ]);
    }
}
