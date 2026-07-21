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
use App\Models\StokPlywoodSiapJual;
use App\Models\HppPlywoodSiapJualLog;
use App\Models\StokPlatformJadi;
use App\Models\HppPlatformJadiLog;
use App\Models\StokTriplekJadi;
use App\Models\HppTriplekJadiLog;
use App\Models\StokGudangSatu;
use App\Models\GudangSatuLog;
use App\Models\Ukuran;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class ListOpnameStoks extends CreateRecord
{
    protected static string $resource = OpnameStokResource::class;

    public function getTitle(): string
    {
        return 'Stock Opname';
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
        $jenisStok = $data['jenis_stok'];
        $items     = $data['items'] ?? [];

        // Filter hanya baris yang stok_fisik-nya diisi
        $itemsDiisi = array_filter($items, fn($item) => isset($item['stok_fisik']) && $item['stok_fisik'] !== null && $item['stok_fisik'] !== '');

        if (empty($itemsDiisi)) {
            Notification::make()->title('Tidak ada data yang diisi')->warning()->send();
            return new BarangSetengahJadiHp();
        }

        $berhasil = 0;
        $dilewati = 0;

        foreach ($itemsDiisi as $item) {
            // Gabungkan data item dengan jenis_stok agar method opname bisa pakai
            $itemData = array_merge($item, ['jenis_stok' => $jenisStok]);

            $result = match ($jenisStok) {
                'veneer_basah'  => $this->opnameVeneerBasah($itemData),
                'veneer_jadi'   => $this->opnameVeneerJadi($itemData),
                'veneer_kering' => $this->opnameVeneerKering($itemData),
                'platform_mth'  => $this->opnamePlatformMth($itemData),
                'triplek_mth'   => $this->opnameTriplekMth($itemData),
                'plywood'       => $this->opnamePlywood($itemData),
                'platform_jadi' => $this->opnamePlatformJadi($itemData),
                'triplek_jadi'  => $this->opnameTriplekJadi($itemData),
                'gudang_satu'   => $this->opnameGudangSatu($itemData),
                default         => null,
            };

            if ($result === true) $berhasil++;
            else $dilewati++;
        }

        Notification::make()
            ->title('Opname Selesai')
            ->body("{$berhasil} barang berhasil disesuaikan" . ($dilewati > 0 ? ", {$dilewati} dilewati (tidak ada perubahan)." : "."))
            ->success()
            ->send();

        return new BarangSetengahJadiHp();
    }

    // ────────────────────────────────────────────────────────────
    // Helper: buat keterangan
    // ────────────────────────────────────────────────────────────
    private function buatKeterangan(string $label, array $data): string
    {
        $tgl      = now()->format('d/m/Y');
        $namaUser = auth()->user()?->name ?? 'SISTEM';
        $ket      = "{$label} TANGGAL {$tgl} OLEH {$namaUser}";
        if (!empty($data['catatan'])) {
            $ket .= ". CATATAN: " . strtoupper($data['catatan']);
        }
        return $ket;
    }

    // ────────────────────────────────────────────────────────────
    // VENEER BASAH
    // ────────────────────────────────────────────────────────────
    protected function opnameVeneerBasah(array $data): bool
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
                    'stok_lembar'   => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0,
                ]);
            }

            $stokSistem      = (int) $summary->stok_lembar;
            $stokFisik       = (int) $data['stok_fisik'];
            $kubikasiFisik   = (float) $data['kubikasi_fisik'];
            $kubikasiSistem  = (float) $summary->stok_kubikasi;
            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

            $tipe = $selisihLembar !== 0 ? ($selisihLembar > 0 ? 'masuk' : 'keluar') : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');
            $ket  = $this->buatKeterangan('OPNAME VENEER BASAH', $data);

            $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);
            $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
            $nilaiStokBefore = $summary->nilai_stok;

            $summary->update(['stok_lembar' => $stokFisik, 'stok_kubikasi' => $kubikasiFisik, 'nilai_stok' => $nilaiStokBaru]);

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

            return true;
        });
    }

    // ────────────────────────────────────────────────────────────
    // VENEER JADI
    // ────────────────────────────────────────────────────────────
    protected function opnameVeneerJadi(array $data): bool
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
                    'stok_lembar'   => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0,
                ]);
            }

            $stokSistem      = (int) $summary->stok_lembar;
            $stokFisik       = (int) $data['stok_fisik'];
            $kubikasiFisik   = (float) $data['kubikasi_fisik'];
            $kubikasiSistem  = (float) $summary->stok_kubikasi;
            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

            $tipe = $selisihLembar !== 0 ? ($selisihLembar > 0 ? 'masuk' : 'keluar') : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');
            $ket  = $this->buatKeterangan('OPNAME VENEER JADI', $data);

            $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);
            $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
            $nilaiStokBefore = $summary->nilai_stok;

            $summary->update(['stok_lembar' => $stokFisik, 'stok_kubikasi' => $kubikasiFisik, 'nilai_stok' => $nilaiStokBaru]);

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

            return true;
        });
    }

    // ────────────────────────────────────────────────────────────
    // VENEER KERING
    // ────────────────────────────────────────────────────────────
    protected function opnameVeneerKering(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            $idUkuran    = (int) $data['id_ukuran'];
            $idJenisKayu = (int) $data['id_jenis_kayu'];
            $kw          = (string) $data['kw'];

            $stokLembarSistem = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);
            $snapshot         = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);

            $stokFisik       = (int) $data['stok_fisik'];
            $kubikasiFisik   = (float) $data['kubikasi_fisik'];
            $kubikasiSistem  = (float) $snapshot['stok_m3'];
            $hppAverage      = (float) $snapshot['hpp_average'];
            $selisihLembar   = $stokFisik - $stokLembarSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

            $tipe = $selisihLembar !== 0 ? ($selisihLembar > 0 ? 'masuk' : 'keluar') : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');
            $ket  = $this->buatKeterangan('OPNAME VENEER KERING', $data);

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

            return true;
        });
    }

    // ────────────────────────────────────────────────────────────
    // HELPER: pola umum summary & log
    // ────────────────────────────────────────────────────────────
    private function opnameDenganSummary(
        array $data,
        object $summary,
        string $labelKeterangan,
        string $logClass,
        array $logExtra = [],
        string $idField = 'id_jenis_kayu',
    ): bool {
        $stokSistem      = (int) $summary->stok_lembar;
        $stokFisik       = (int) $data['stok_fisik'];
        $kubikasiFisik   = (float) $data['kubikasi_fisik'];
        $kubikasiSistem  = (float) $summary->stok_kubikasi;
        $selisihLembar   = $stokFisik - $stokSistem;
        $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

        if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

        $tipe = $selisihLembar !== 0 ? ($selisihLembar > 0 ? 'masuk' : 'keluar') : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');
        $ket  = $this->buatKeterangan($labelKeterangan, $data);

        $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);
        $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
        $nilaiStokBefore = $summary->nilai_stok;

        $summary->update(['stok_lembar' => $stokFisik, 'stok_kubikasi' => $kubikasiFisik, 'nilai_stok' => $nilaiStokBaru]);

        $log = $logClass::create(array_merge([
            $idField               => $summary->{$idField},
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
        ], $logExtra));

        $summary->update(['id_last_log' => $log->id]);
        return true;
    }

    // ────────────────────────────────────────────────────────────
    // PLATFORM MTH
    // ────────────────────────────────────────────────────────────
    protected function opnamePlatformMth(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            $ukuran  = Ukuran::findOrFail($data['id_ukuran']);
            $summary = StokPlatformMth::where(['id_jenis_kayu' => $data['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokPlatformMth::create(['id_jenis_kayu' => $data['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);
            return $this->opnameDenganSummary($data, $summary, 'OPNAME PLATFORM MTH', HppPlatformMthLog::class);
        });
    }

    // ────────────────────────────────────────────────────────────
    // TRIPLEK MTH
    // ────────────────────────────────────────────────────────────
    protected function opnameTriplekMth(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            $ukuran  = Ukuran::findOrFail($data['id_ukuran']);
            $summary = StokTriplekMth::where(['id_jenis_kayu' => $data['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokTriplekMth::create(['id_jenis_kayu' => $data['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);
            return $this->opnameDenganSummary($data, $summary, 'OPNAME TRIPLEK MTH', HppTriplekMthLog::class);
        });
    }

    // ────────────────────────────────────────────────────────────
    // PLYWOOD SIAP JUAL
    // ────────────────────────────────────────────────────────────
    protected function opnamePlywood(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            $ukuran = Ukuran::findOrFail($data['id_ukuran']);
            $summary = StokPlywoodSiapJual::where(['id_jenis_kayu' => $data['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokPlywoodSiapJual::create(['id_jenis_kayu' => $data['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0]);

            $stokSistem      = (int) $summary->stok_lembar;
            $stokFisik       = (int) $data['stok_fisik'];
            $kubikasiFisik   = (float) $data['kubikasi_fisik'];
            $kubikasiSistem  = (float) $summary->stok_kubikasi;
            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

            $tipe = $selisihLembar !== 0 ? ($selisihLembar > 0 ? 'masuk' : 'keluar') : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');
            $ket  = $this->buatKeterangan('OPNAME PLYWOOD SIAP JUAL', $data);
            $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);

            $summary->update(['stok_lembar' => $stokFisik, 'stok_kubikasi' => $kubikasiFisik]);

            $log = HppPlywoodSiapJualLog::create([
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
            ]);
            $summary->update(['id_last_log' => $log->id]);

            return true;
        });
    }

    // ────────────────────────────────────────────────────────────
    // PLATFORM JADI
    // ────────────────────────────────────────────────────────────
    protected function opnamePlatformJadi(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            $ukuran  = Ukuran::findOrFail($data['id_ukuran']);
            $summary = StokPlatformJadi::where(['id_jenis_barang' => $data['id_jenis_barang'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokPlatformJadi::create(['id_jenis_barang' => $data['id_jenis_barang'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);
            return $this->opnameDenganSummary($data, $summary, 'OPNAME PLATFORM JADI', HppPlatformJadiLog::class, [], 'id_jenis_barang');
        });
    }

    // ────────────────────────────────────────────────────────────
    // TRIPLEK JADI
    // ────────────────────────────────────────────────────────────
    protected function opnameTriplekJadi(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            $ukuran  = Ukuran::findOrFail($data['id_ukuran']);
            $summary = StokTriplekJadi::where(['id_jenis_kayu' => $data['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokTriplekJadi::create(['id_jenis_kayu' => $data['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);
            return $this->opnameDenganSummary($data, $summary, 'OPNAME TRIPLEK JADI', HppTriplekJadiLog::class);
        });
    }

    // ────────────────────────────────────────────────────────────
    // GUDANG SATU
    // ────────────────────────────────────────────────────────────
    protected function opnameGudangSatu(array $data): bool
    {
        return DB::transaction(function () use ($data) {
            $ukuran  = Ukuran::findOrFail($data['id_ukuran']);
            $summary = StokGudangSatu::where(['id_jenis_kayu' => $data['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokGudangSatu::create(['id_jenis_kayu' => $data['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $data['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);
            return $this->opnameDenganSummary($data, $summary, 'OPNAME GUDANG SATU', GudangSatuLog::class);
        });
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}