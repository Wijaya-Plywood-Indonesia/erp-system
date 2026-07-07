<?php

namespace App\Filament\Resources\OpnameStoks\Pages;

use App\Filament\Resources\OpnameStoks\OpnameStokResource;
use App\Models\BarangSetengahJadiHp;
use App\Models\HppVeneerBasahSummary;
use App\Models\HppVeneerBasahLog;
use App\Models\StokVeneerJadi;
use App\Models\HppVeneerJadiLog;
use App\Models\StokVeneerKering;
use App\Models\StokPlatformMth;
use App\Models\HppPlatformMthLog;
use App\Models\StokTriplekMth;
use App\Models\HppTriplekMthLog;
use App\Models\Ukuran;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class ListOpnameStoks extends CreateRecord
{
    protected static string $resource = OpnameStokResource::class;

    public function getTitle(): string
    {
        return 'Stock Opname Veneer';
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
        return match ($data['jenis_stok']) {
            'veneer_basah'   => $this->opnameVeneerBasah($data),
            'veneer_jadi'    => $this->opnameVeneerJadi($data),
            'veneer_kering'  => $this->opnameVeneerKering($data),
            'platform_mth'   => $this->opnamePlatformMth($data),
            'triplek_mth'    => $this->opnameTriplekMth($data),
            default          => throw new \InvalidArgumentException('Jenis stok tidak dikenali.'),
        };
    }

    // ────────────────────────────────────────────────────────────
    // VENEER BASAH
    // ────────────────────────────────────────────────────────────
    protected function opnameVeneerBasah(array $data): BarangSetengahJadiHp
    {
        return DB::transaction(function () use ($data) {
            $ukuran = Ukuran::findOrFail($data['id_ukuran']);

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

            $stokSistem     = (int) $summary->stok_lembar;
            $stokFisik      = (int) $data['stok_fisik'];
            $kubikasiFisik  = (float) $data['kubikasi_fisik'];
            $kubikasiSistem = (float) $summary->stok_kubikasi;

            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) {
                Notification::make()->title('Tidak ada perubahan stok')->warning()->send();
                return new BarangSetengahJadiHp();
            }

            $tipe = $selisihLembar !== 0
                ? ($selisihLembar > 0 ? 'masuk' : 'keluar')
                : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');

            $tgl = now()->format('d/m/Y');
            $ket = "OPNAME VENEER BASAH TANGGAL {$tgl}";
            if (!empty($data['catatan'])) {
                $ket .= ". CATATAN: " . strtoupper($data['catatan']);
            }

            $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);
            $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
            $nilaiStokBefore = $summary->nilai_stok;

            $summary->update([
                'stok_lembar'   => $stokFisik,
                'stok_kubikasi' => $kubikasiFisik,
                'nilai_stok'    => $nilaiStokBaru,
            ]);

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
                ->body("Stok veneer basah disesuaikan menjadi {$stokFisik} lembar.")
                ->success()
                ->send();

            return new BarangSetengahJadiHp();
        });
    }

    // ────────────────────────────────────────────────────────────
    // VENEER JADI
    // ────────────────────────────────────────────────────────────
    protected function opnameVeneerJadi(array $data): BarangSetengahJadiHp
    {
        return DB::transaction(function () use ($data) {
            $ukuran = Ukuran::findOrFail($data['id_ukuran']);

            $summary = StokVeneerJadi::where([
                'id_jenis_kayu' => $data['id_jenis_kayu'],
                'panjang'       => (float) $ukuran->panjang,
                'lebar'         => (float) $ukuran->lebar,
                'tebal'         => (float) $ukuran->tebal,
                'kw_grade'      => $data['kw'],
            ])->lockForUpdate()->first();

            if (!$summary) {
                $summary = StokVeneerJadi::create([
                    'id_jenis_kayu' => $data['id_jenis_kayu'],
                    'panjang'       => (float) $ukuran->panjang,
                    'lebar'         => (float) $ukuran->lebar,
                    'tebal'         => (float) $ukuran->tebal,
                    'kw_grade'      => $data['kw'],
                    'stok_lembar'   => 0,
                    'stok_kubikasi' => 0,
                    'nilai_stok'    => 0,
                    'hpp_average'   => 0,
                ]);
            }

            $stokSistem     = (int) $summary->stok_lembar;
            $stokFisik      = (int) $data['stok_fisik'];
            $kubikasiFisik  = (float) $data['kubikasi_fisik'];
            $kubikasiSistem = (float) $summary->stok_kubikasi;

            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) {
                Notification::make()->title('Tidak ada perubahan stok')->warning()->send();
                return new BarangSetengahJadiHp();
            }

            $tipe = $selisihLembar !== 0
                ? ($selisihLembar > 0 ? 'masuk' : 'keluar')
                : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');

            $tgl = now()->format('d/m/Y');
            $ket = "OPNAME VENEER JADI TANGGAL {$tgl}";
            if (!empty($data['catatan'])) {
                $ket .= ". CATATAN: " . strtoupper($data['catatan']);
            }

            $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);
            $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
            $nilaiStokBefore = $summary->nilai_stok;

            $summary->update([
                'stok_lembar'   => $stokFisik,
                'stok_kubikasi' => $kubikasiFisik,
                'nilai_stok'    => $nilaiStokBaru,
            ]);

            $log = HppVeneerJadiLog::create([
                'id_jenis_kayu'        => $summary->id_jenis_kayu,
                'panjang'              => $summary->panjang,
                'lebar'                => $summary->lebar,
                'tebal'                => $summary->tebal,
                'kw_grade'             => $summary->kw_grade,
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
                'nilai_stok'           => $nilaiStokBaru,
                'nilai_stok_before'    => $nilaiStokBefore,
                'nilai_stok_after'     => $nilaiStokBaru,
            ]);

            $summary->update(['id_last_log' => $log->id]);

            Notification::make()
                ->title('Opname Berhasil')
                ->body("Stok veneer jadi disesuaikan menjadi {$stokFisik} lembar.")
                ->success()
                ->send();

            return new BarangSetengahJadiHp();
        });
    }

    // ────────────────────────────────────────────────────────────
    // VENEER KERING
    // ────────────────────────────────────────────────────────────
    protected function opnameVeneerKering(array $data): BarangSetengahJadiHp
    {
        return DB::transaction(function () use ($data) {
            $idUkuran    = (int) $data['id_ukuran'];
            $idJenisKayu = (int) $data['id_jenis_kayu'];
            $kw          = (string) $data['kw'];

            $stokLembarSistem = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);
            $snapshot         = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);

            $stokFisik      = (int) $data['stok_fisik'];
            $kubikasiFisik  = (float) $data['kubikasi_fisik'];
            $kubikasiSistem = (float) $snapshot['stok_m3'];
            $hppAverage     = (float) $snapshot['hpp_average'];

            $selisihLembar   = $stokFisik - $stokLembarSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) {
                Notification::make()->title('Tidak ada perubahan stok')->warning()->send();
                return new BarangSetengahJadiHp();
            }

            $tipe = $selisihLembar !== 0
                ? ($selisihLembar > 0 ? 'masuk' : 'keluar')
                : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');

            $tgl = now()->format('d/m/Y');
            $ket = "OPNAME VENEER KERING TANGGAL {$tgl}";
            if (!empty($data['catatan'])) {
                $ket .= ". CATATAN: " . strtoupper($data['catatan']);
            }

            $nilaiStokSebelum = (float) $snapshot['nilai_stok'];
            $nilaiStokSesudah = round($kubikasiFisik * $hppAverage, 2);

            StokVeneerKering::create([
                'id_ukuran'           => $idUkuran,
                'id_jenis_kayu'       => $idJenisKayu,
                'kw'                  => $kw,
                'jenis_transaksi'     => $tipe,
                'tanggal_transaksi'   => now(),
                'qty'                 => abs($selisihLembar),
                'm3'                  => round(abs($kubikasiFisik - $kubikasiSistem), 6),
                'stok_lembar_sebelum' => $stokLembarSistem,
                'stok_lembar_sesudah' => $stokFisik,
                'hpp_kering_per_m3'   => $hppAverage,
                'nilai_transaksi'     => round(abs($nilaiStokSesudah - $nilaiStokSebelum), 2),
                'stok_m3_sebelum'     => $kubikasiSistem,
                'nilai_stok_sebelum'  => $nilaiStokSebelum,
                'stok_m3_sesudah'     => $kubikasiFisik,
                'nilai_stok_sesudah'  => $nilaiStokSesudah,
                'hpp_average'         => $hppAverage,
                'keterangan'          => $ket,
            ]);

            Notification::make()
                ->title('Opname Berhasil')
                ->body("Stok veneer kering disesuaikan menjadi {$stokFisik} lembar.")
                ->success()
                ->send();

            return new BarangSetengahJadiHp();
        });
    }

    // ────────────────────────────────────────────────────────────
    // PLATFORM MTH
    // ────────────────────────────────────────────────────────────
    protected function opnamePlatformMth(array $data): BarangSetengahJadiHp
    {
        return DB::transaction(function () use ($data) {
            $ukuran = Ukuran::findOrFail($data['id_ukuran']);

            $summary = StokPlatformMth::where([
                'id_jenis_kayu' => $data['id_jenis_kayu'],
                'panjang'       => (float) $ukuran->panjang,
                'lebar'         => (float) $ukuran->lebar,
                'tebal'         => (float) $ukuran->tebal,
                'kw_grade'      => $data['kw'],
            ])->lockForUpdate()->first();

            if (!$summary) {
                $summary = StokPlatformMth::create([
                    'id_jenis_kayu' => $data['id_jenis_kayu'],
                    'panjang'       => (float) $ukuran->panjang,
                    'lebar'         => (float) $ukuran->lebar,
                    'tebal'         => (float) $ukuran->tebal,
                    'kw_grade'      => $data['kw'],
                    'stok_lembar'   => 0,
                    'stok_kubikasi' => 0,
                    'nilai_stok'    => 0,
                    'hpp_average'   => 0,
                ]);
            }

            $stokSistem     = (int) $summary->stok_lembar;
            $stokFisik      = (int) $data['stok_fisik'];
            $kubikasiFisik  = (float) $data['kubikasi_fisik'];
            $kubikasiSistem = (float) $summary->stok_kubikasi;

            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) {
                Notification::make()->title('Tidak ada perubahan stok')->warning()->send();
                return new BarangSetengahJadiHp();
            }

            $tipe = $selisihLembar !== 0
                ? ($selisihLembar > 0 ? 'masuk' : 'keluar')
                : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');

            $tgl = now()->format('d/m/Y');
            $ket = "OPNAME PLATFORM MTH TANGGAL {$tgl}";
            if (!empty($data['catatan'])) {
                $ket .= ". CATATAN: " . strtoupper($data['catatan']);
            }

            $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);
            $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
            $nilaiStokBefore = $summary->nilai_stok;

            $summary->update([
                'stok_lembar'   => $stokFisik,
                'stok_kubikasi' => $kubikasiFisik,
                'nilai_stok'    => $nilaiStokBaru,
            ]);

            $log = HppPlatformMthLog::create([
                'id_jenis_kayu'        => $summary->id_jenis_kayu,
                'panjang'              => $summary->panjang,
                'lebar'                => $summary->lebar,
                'tebal'                => $summary->tebal,
                'kw_grade'             => $summary->kw_grade,
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
                'nilai_stok'           => $nilaiStokBaru,
                'nilai_stok_before'    => $nilaiStokBefore,
                'nilai_stok_after'     => $nilaiStokBaru,
            ]);

            $summary->update(['id_last_log' => $log->id]);

            Notification::make()
                ->title('Opname Berhasil')
                ->body("Stok platform MTH disesuaikan menjadi {$stokFisik} lembar.")
                ->success()
                ->send();

            return new BarangSetengahJadiHp();
        });
    }

    // ────────────────────────────────────────────────────────────
    // TRIPLEK MTH
    // ────────────────────────────────────────────────────────────
    protected function opnameTriplekMth(array $data): BarangSetengahJadiHp
    {
        return DB::transaction(function () use ($data) {
            $ukuran = Ukuran::findOrFail($data['id_ukuran']);

            $summary = StokTriplekMth::where([
                'id_jenis_kayu' => $data['id_jenis_kayu'],
                'panjang'       => (float) $ukuran->panjang,
                'lebar'         => (float) $ukuran->lebar,
                'tebal'         => (float) $ukuran->tebal,
                'kw_grade'      => $data['kw'],
            ])->lockForUpdate()->first();

            if (!$summary) {
                $summary = StokTriplekMth::create([
                    'id_jenis_kayu' => $data['id_jenis_kayu'],
                    'panjang'       => (float) $ukuran->panjang,
                    'lebar'         => (float) $ukuran->lebar,
                    'tebal'         => (float) $ukuran->tebal,
                    'kw_grade'      => $data['kw'],
                    'stok_lembar'   => 0,
                    'stok_kubikasi' => 0,
                    'nilai_stok'    => 0,
                    'hpp_average'   => 0,
                ]);
            }

            $stokSistem     = (int) $summary->stok_lembar;
            $stokFisik      = (int) $data['stok_fisik'];
            $kubikasiFisik  = (float) $data['kubikasi_fisik'];
            $kubikasiSistem = (float) $summary->stok_kubikasi;

            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) {
                Notification::make()->title('Tidak ada perubahan stok')->warning()->send();
                return new BarangSetengahJadiHp();
            }

            $tipe = $selisihLembar !== 0
                ? ($selisihLembar > 0 ? 'masuk' : 'keluar')
                : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');

            $tgl = now()->format('d/m/Y');
            $ket = "OPNAME TRIPLEK MTH TANGGAL {$tgl}";
            if (!empty($data['catatan'])) {
                $ket .= ". CATATAN: " . strtoupper($data['catatan']);
            }

            $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);
            $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
            $nilaiStokBefore = $summary->nilai_stok;

            $summary->update([
                'stok_lembar'   => $stokFisik,
                'stok_kubikasi' => $kubikasiFisik,
                'nilai_stok'    => $nilaiStokBaru,
            ]);

            $log = HppTriplekMthLog::create([
                'id_jenis_kayu'        => $summary->id_jenis_kayu,
                'panjang'              => $summary->panjang,
                'lebar'                => $summary->lebar,
                'tebal'                => $summary->tebal,
                'kw_grade'             => $summary->kw_grade,
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
                'nilai_stok'           => $nilaiStokBaru,
                'nilai_stok_before'    => $nilaiStokBefore,
                'nilai_stok_after'     => $nilaiStokBaru,
            ]);

            $summary->update(['id_last_log' => $log->id]);

            Notification::make()
                ->title('Opname Berhasil')
                ->body("Stok triplek MTH disesuaikan menjadi {$stokFisik} lembar.")
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