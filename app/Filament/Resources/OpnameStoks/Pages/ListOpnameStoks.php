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
            $ukuran = Ukuran::findOrFail($data['id_ukuran']);

            // 1. Ambil Summary, jika belum ada buat baru
            $summary = HppVeneerBasahSummary::where([
                'id_jenis_kayu' => $data['id_jenis_kayu'],
                'panjang'       => (float) $ukuran->panjang,
                'lebar'         => (float) $ukuran->lebar,
                'tebal'         => (float) $ukuran->tebal,
                'kw'            => $data['kw'],
            ])->lockForUpdate()->first();

            if (!$summary) {
                $summary = HppVeneerBasahSummary::create([
                    'id_jenis_kayu' => $data['id_jenis_kayu'],
                    'panjang'       => (float) $ukuran->panjang,
                    'lebar'         => (float) $ukuran->lebar,
                    'tebal'         => (float) $ukuran->tebal,
                    'kw'            => $data['kw'],
                    'stok_lembar'   => 0,
                    'stok_kubikasi' => 0,
                    'nilai_stok'    => 0,
                    'hpp_average'   => 0,
                ]);
            }

            // 2. Ambil nilai dari input user
            $stokSistem     = (int) $summary->stok_lembar;
            $stokFisik      = (int) $data['stok_fisik'];
            $kubikasiFisik  = (float) $data['kubikasi_fisik'];
            $kubikasiSistem = (float) $summary->stok_kubikasi;

            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            // Stop hanya jika KEDUANYA tidak ada perubahan
            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) {
                Notification::make()->title('Tidak ada perubahan stok')->warning()->send();
                return new BarangSetengahJadiHp();
            }

            // Tipe berdasarkan lembar dulu, jika sama pakai kubikasi
            $tipe = $selisihLembar !== 0
                ? ($selisihLembar > 0 ? 'masuk' : 'keluar')
                : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');

            // 3. Format Keterangan
            $tgl = now()->format('d/m/Y');
            $ket = "OPNAME VENEER BASAH TANGGAL {$tgl}";
            if (!empty($data['catatan'])) {
                $ket .= ". CATATAN: " . strtoupper($data['catatan']);
            }

            // 4. Kalkulasi Kubikasi & Nilai
            $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);
            $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
            $nilaiStokBefore = $summary->nilai_stok;

            // 5. Update Summary
            $summary->update([
                'stok_lembar'   => $stokFisik,
                'stok_kubikasi' => $kubikasiFisik,
                'nilai_stok'    => $nilaiStokBaru,
            ]);

            // 6. Simpan Log
            HppVeneerBasahLog::create([
                'id_jenis_kayu'        => $summary->id_jenis_kayu,
                'panjang'              => $summary->panjang,
                'lebar'                => $summary->lebar,
                'tebal'                => $summary->tebal,
                'kw'                   => $summary->kw,
                'tanggal'              => now(),
                'tipe_transaksi'       => $tipe,
                'keterangan'           => $ket,
                'total_lembar'         => abs($selisihLembar),
                'total_kubikasi'       => $kubikasiSelisih,
                'stok_lembar_before'   => $stokSistem,
                'stok_lembar_after'    => $stokFisik,
                'stok_kubikasi_before' => $kubikasiSistem,
                'stok_kubikasi_after'  => $kubikasiFisik,
                'hpp_average'          => $summary->hpp_average,
                'nilai_stok_before'    => $nilaiStokBefore,
                'nilai_stok_after'     => $nilaiStokBaru,
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