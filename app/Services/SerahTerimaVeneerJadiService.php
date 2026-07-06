<?php

namespace App\Services;

use App\Models\StokVeneerJadi;
use App\Models\HppVeneerJadiLog;
use App\Models\User;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use App\Models\Ukuran;
use Illuminate\Support\Facades\DB;

class SerahTerimaVeneerJadiService
{
    /**
     * Pola nilai `tipe_veneer` yang dianggap "veneer jadi".
     * LIKE agar aman terhadap variasi ('veneer_jadi', 'Veneer Jadi', dst).
     */
    public const TIPE_JADI_LIKE = '%jadi%';

    /**
     * Terima seluruh baris VENEER JADI dari sebuah VeneerMutasi ke
     * Gudang Veneer Jadi (StokVeneerJadi summary + HppVeneerJadiLog).
     *
     * Idempotent: detail yang sudah punya log 'masuk' dilewati, jadi aman
     * kalau tombol tak sengaja ditekan dua kali.
     */
    public function terima(VeneerMutasi $mutasi, int $userId): void
    {
        DB::transaction(function () use ($mutasi, $userId) {
            $details = $mutasi->details()
                ->where('tipe_veneer', 'like', self::TIPE_JADI_LIKE)
                ->get();

            foreach ($details as $detail) {
                $this->terimaSatuDetail($detail, $userId, $mutasi);
            }
        });
    }

    /**
     * Terima SATU baris VeneerMutasiDetail saja.
     *
     * Dipakai oleh antrean gabungan di halaman Gudang Veneer Jadi, di mana
     * user klik "Terima" per baris (bukan per mutasi sekaligus seperti
     * terima() di atas).
     *
     * Idempotent: kalau detail ini sudah punya log 'masuk', dilewati saja
     * (aman kalau tombol tak sengaja ditekan dua kali / race condition).
     *
     * @param  VeneerMutasiDetail  $detail
     * @param  int                $userId
     * @param  VeneerMutasi|null  $mutasi  Opsional. Jika tidak diisi, akan
     *                                     diambil dari relasi $detail->mutasi()
     *                                     — SESUAIKAN NAMA RELASI INI jika di
     *                                     model Anda relasinya bernama lain.
     */
    public function terimaSatuDetail(VeneerMutasiDetail $detail, int $userId, ?VeneerMutasi $mutasi = null): void
    {
        $mutasi ??= $detail->mutasi;

        if (!$mutasi) {
            throw new \Exception('VeneerMutasi induk untuk detail ini tidak ditemukan.');
        }

        DB::transaction(function () use ($mutasi, $detail, $userId) {
            $sudahAda = HppVeneerJadiLog::query()
                ->where('referensi_type', VeneerMutasiDetail::class)
                ->where('referensi_id', $detail->id)
                ->where('tipe_transaksi', 'masuk')
                ->exists();

            if ($sudahAda) {
                return;
            }

            $namaPenerima = User::find($userId)?->name ?? 'Tidak diketahui';

            $this->postMasuk($mutasi, $detail, $userId, $namaPenerima);
        });
    }

    /**
     * Catat satu baris MASUK ke summary StokVeneerJadi + HppVeneerJadiLog.
     * Pola sama seperti updateStokBasah/updateStokJadi: summary teragregasi
     * + lockForUpdate, nilai memakai moving-average yang ada.
     */
    protected function postMasuk(VeneerMutasi $mutasi, VeneerMutasiDetail $detail, int $userId, string $namaPenerima): void
    {
        $ukuran = Ukuran::findOrFail($detail->id_ukuran);

        $summary = StokVeneerJadi::where([
            'id_jenis_kayu' => $detail->id_jenis_kayu,
            'panjang'       => $ukuran->panjang,
            'lebar'         => $ukuran->lebar,
            'tebal'         => $ukuran->tebal,
            'kw_grade'      => $detail->kw,
        ])->lockForUpdate()->first();

        if (!$summary) {
            $summary = StokVeneerJadi::create([
                'id_jenis_kayu'           => $detail->id_jenis_kayu,
                'panjang'                 => $ukuran->panjang,
                'lebar'                   => $ukuran->lebar,
                'tebal'                   => $ukuran->tebal,
                'kw_grade'                => $detail->kw,
                'stok_lembar'             => 0,
                'stok_kubikasi'           => 0,
                'nilai_stok'              => 0,
                'hpp_average'             => 0,
                'hpp_pekerja_last'        => 0,
                'hpp_bahan_penolong_last' => 0,
            ]);
        }

        $stokSistem      = (int) $summary->stok_lembar;
        $kubikasiSistem  = (float) $summary->stok_kubikasi;
        $nilaiStokBefore = (float) $summary->nilai_stok;

        // TODO: hpp_pekerja & hpp_bahan_penolong belum ada sumber input dari Mutasi,
        // sementara 0. Sesuaikan begitu alur biaya proses jadi jelas.
        $hppPekerja       = 0.0;
        $hppBahanPenolong = 0.0;

        $stokFisik     = $stokSistem + $detail->qty;
        $kubikasiFisik = round($kubikasiSistem + $detail->m3, 6);
        $nilaiMasuk    = round($detail->m3 * $summary->hpp_average, 2);
        $nilaiStokBaru = round($nilaiStokBefore + $nilaiMasuk, 2);

        $summary->update([
            'stok_lembar'             => $stokFisik,
            'stok_kubikasi'           => $kubikasiFisik,
            'nilai_stok'              => $nilaiStokBaru,
            'hpp_pekerja_last'        => $hppPekerja,
            'hpp_bahan_penolong_last' => $hppBahanPenolong,
        ]);

        // Keterangan final: No Nota + siapa yang menerima + keterangan asli.
        $bagian = [
            'No Nota: ' . ($mutasi->no_nota ?: '-'),
            'Diterima oleh: ' . $namaPenerima,
        ];
        if (trim((string) $mutasi->keterangan) !== '') {
            $bagian[] = 'Keterangan: ' . trim((string) $mutasi->keterangan);
        }
        $keteranganFinal = strtoupper(implode(' · ', $bagian));

        $log = HppVeneerJadiLog::create([
            'id_jenis_kayu'        => $detail->id_jenis_kayu,
            'panjang'              => $ukuran->panjang,
            'lebar'                => $ukuran->lebar,
            'tebal'                => $ukuran->tebal,
            'kw_grade'             => $detail->kw,
            'tanggal'              => optional($mutasi->tanggal)->toDateString() ?? now()->toDateString(),
            'tipe_transaksi'       => 'masuk',
            'keterangan'           => $keteranganFinal,
            'referensi_type'       => VeneerMutasiDetail::class,
            'referensi_id'         => $detail->id,
            'total_lembar'         => $detail->qty,
            'total_kubikasi'       => $detail->m3,
            'hpp_pekerja'          => $hppPekerja,
            'hpp_bahan_penolong'   => $hppBahanPenolong,
            'hpp_average'          => $summary->hpp_average,
            'nilai_stok'           => $nilaiStokBaru,
            'stok_lembar_before'   => $stokSistem,
            'stok_lembar_after'    => $stokFisik,
            'stok_kubikasi_before' => $kubikasiSistem,
            'stok_kubikasi_after'  => $kubikasiFisik,
            'nilai_stok_before'    => $nilaiStokBefore,
            'nilai_stok_after'     => $nilaiStokBaru,
        ]);

        $summary->update(['id_last_log' => $log->id]);
    }
}
