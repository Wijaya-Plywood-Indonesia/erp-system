<?php

namespace App\Filament\Resources\OpnameStoks\Pages;

use App\Filament\Resources\OpnameStoks\OpnameStokResource;
use App\Models\BarangSetengahJadiHp;
use App\Models\HppVeneerBasahSummary;
use App\Models\HppVeneerBasahLog;
use App\Models\Ukuran;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class ListOpnameStoks extends CreateRecord
{
    protected static string $resource = OpnameStokResource::class;

    public function getTitle(): string
    {
        return 'Stock Opname Veneer Basah';
    }

    public function getMaxContentWidth(): string
    {
        return 'full';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Sesuaikan Stok Sekarang'),
        ];
    }

    protected function handleRecordCreation(array $data): BarangSetengahJadiHp
    {
        return DB::transaction(function () use ($data) {
            $ukuran = \App\Models\Ukuran::findOrFail($data['id_ukuran']);
            
            // 1. Ambil Summary dengan Lock
            $summary = HppVeneerBasahSummary::where([
                'id_jenis_kayu' => $data['id_jenis_kayu'],
                'panjang' => (float)$ukuran->panjang,
                'lebar'   => (float)$ukuran->lebar,
                'tebal'   => (float)$ukuran->tebal,
                'kw'      => $data['kw'],
            ])->lockForUpdate()->first();

            if (!$summary) {
                Notification::make()->title('Data summary tidak ditemukan')->danger()->send();
                $this->halt();
            }

            $stokSistem = (int) $summary->stok_lembar;
            $stokFisik  = (int) $data['stok_fisik'];
            $selisih    = $stokFisik - $stokSistem; 

            if ($selisih === 0) {
                 Notification::make()->title('Tidak ada perubahan stok')->warning()->send();
                 return new BarangSetengahJadiHp();
            }

            $tipe = $selisih > 0 ? 'masuk' : 'keluar';

            // REVISI: Format Keterangan menggunakan Tanggal
            $tgl = now()->format('d/m/Y');
            $ket = "OPNAME VENEER BASAH TANGGAL {$tgl}";
            if (!empty($data['catatan'])) {
                $ket .= ". CATATAN: " . strtoupper($data['catatan']);
            }

            // 2. Kalkulasi Kubikasi & Nilai
            $volPerLembar   = ($summary->panjang * $summary->lebar * $summary->tebal) / 10000000;
            $kubikasiBaru   = round($stokFisik * $volPerLembar, 6);
            $kubikasiSelisih = round(abs($selisih) * $volPerLembar, 6);
            $nilaiStokBaru  = round($kubikasiBaru * $summary->hpp_average, 2);

            $stokKubikasiBefore = $summary->stok_kubikasi;
            $nilaiStokBefore    = $summary->nilai_stok;

            // 3. Update Summary
            $summary->update([
                'stok_lembar'   => $stokFisik,
                'stok_kubikasi' => $kubikasiBaru,
                'nilai_stok'    => $nilaiStokBaru,
            ]);

            // 4. Simpan Log
            HppVeneerBasahLog::create([
                'id_jenis_kayu'      => $summary->id_jenis_kayu,
                'panjang'            => $summary->panjang,
                'lebar'              => $summary->lebar,
                'tebal'              => $summary->tebal,
                'kw'                 => $summary->kw,
                'tanggal'            => now(),
                'tipe_transaksi'     => $tipe,
                'keterangan'         => $ket, // Menggunakan variabel keterangan hasil revisi
                
                // REVISI NAMA KOLOM: total_lembar
                'total_lembar'       => abs($selisih), 
                'total_kubikasi'     => $kubikasiSelisih,
                
                'stok_lembar_before' => $stokSistem,
                'stok_lembar_after'  => $stokFisik,
                'stok_kubikasi_before' => $stokKubikasiBefore,
                'stok_kubikasi_after'  => $kubikasiBaru,
                'hpp_average'        => $summary->hpp_average,
                'nilai_stok_before'  => $nilaiStokBefore,
                'nilai_stok_after'   => $nilaiStokBaru,
            ]);

            Notification::make()
                ->title('Opname Berhasil')
                ->body("Stok telah disesuaikan menjadi {$stokFisik} lembar.")
                ->success()
                ->send();

            return new BarangSetengahJadiHp();
        });
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}