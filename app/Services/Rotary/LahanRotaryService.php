<?php

namespace App\Services;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use App\Models\KayuMasuk;
use App\Models\KayuPecahRotary;
use App\Models\PenggunaanLahanRotary;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LahanRotaryService
{
    /**
     * Ambil data statistik lahan untuk modal modal form
     */
    public function getInfoModal(PenggunaanLahanRotary $record): array
    {
        $idLahan     = $record->id_lahan;
        $idJenisKayu = $record->id_jenis_kayu;

        $stokAktif = (int) HppAverageSummarie::where('id_lahan', $idLahan)
            ->where('id_jenis_kayu', $idJenisKayu)
            ->sum('stok_batang');

        $kayuPecahTotal = KayuPecahRotary::whereHas('penggunaanLahan', function ($q) use ($idLahan) {
            $q->where('id_lahan', $idLahan)->where('hpp_average', 0);
        })->count();

        return [
            'stok_aktif'      => $stokAktif,
            'kayu_pecah'      => $kayuPecahTotal,
            'hasil_real'      => max(0, $stokAktif - $kayuPecahTotal), // Hasil Real = Stok - Pecah
        ];
    }

    /**
     * Eksekusi transaksi penyelesaian lahan
     */
    public function selesaikanLahan(PenggunaanLahanRotary $record, array $data): void
    {
        $idLahan     = $record->id_lahan;
        $idJenisKayu = $record->id_jenis_kayu;

        DB::transaction(function () use ($data, $record, $idLahan, $idJenisKayu) {
            $tglProduksi     = $record->produksi_rotary?->tgl_produksi ?? now();
            $catatanUser     = $data['keterangan'] ?? '-';
            $userLogin      = Auth::user()?->name ?? 'System';
            $tglProduksiFmt = Carbon::parse($tglProduksi)->translatedFormat('d F Y');

            // 1. Ambil HPP Terakhir & Stok Asli (Hasil Kupasan / Nilai Stok)
            $hppTerakhir = HppAverageSummarie::where('id_lahan', $idLahan)
                ->where('id_jenis_kayu', $idJenisKayu)
                ->where('stok_batang', '>', 0)
                ->orderByDesc('id')
                ->value('hpp_average') ?? 0;

            $hasilKupasanStok = (int) HppAverageSummarie::where('id_lahan', $idLahan)
                ->where('id_jenis_kayu', $idJenisKayu)
                ->sum('stok_batang');

            $totalKayuPecah = KayuPecahRotary::whereHas('penggunaanLahan', function ($q) use ($idLahan) {
                $q->where('id_lahan', $idLahan)->where('hpp_average', 0);
            })->count();

            // Hitung Hasil Real otomatis: Hasil Kupasan - Kayu Pecah (atau gunakan input user jika ada)
            $hasilReal = isset($data['hasil_sebenarnya'])
                ? (int) $data['hasil_sebenarnya']
                : max(0, $hasilKupasanStok - $totalKayuPecah);

            // 2. Susun Keterangan Log Sesuai Format Baru
            $keteranganLengkap = sprintf(
                'SELESAI LAHAN | LAHAN: %s - %s | TGL PROD: %s | HASIL KUPASAN: %d | KAYU PECAH: %d | HASIL REAL: %d | DISELESAIKAN OLEH: %s | CATATAN: %s',
                $record->lahan->kode_lahan ?? 'N/A',
                $record->lahan->nama_lahan ?? 'N/A',
                $tglProduksiFmt,
                $hasilKupasanStok,
                $totalKayuPecah,
                $hasilReal,
                $userLogin,
                $catatanUser
            );

            // 3. Process Summaries & HppLog
            $summariesBerstok = HppAverageSummarie::where('id_lahan', $idLahan)
                ->where('id_jenis_kayu', $idJenisKayu)
                ->where('stok_batang', '>', 0)
                ->get();

            foreach ($summariesBerstok as $item) {
                $batangKeluar   = (int)   $item->stok_batang;
                $kubikasiKeluar = (float) $item->stok_kubikasi;
                $nilaiKeluar    = (float) $item->nilai_stok;

                $log = HppAverageLog::create([
                    'id_lahan'             => $idLahan,
                    'id_jenis_kayu'        => $idJenisKayu,
                    'panjang'              => $item->panjang,
                    'tanggal'              => $tglProduksi,
                    'tipe_transaksi'       => 'keluar',
                    'keterangan'           => $keteranganLengkap,
                    'referensi_type'       => PenggunaanLahanRotary::class,
                    'referensi_id'         => $record->id,
                    'total_batang'         => $batangKeluar,
                    'total_kubikasi'       => round($kubikasiKeluar, 4),
                    'harga'                => (float) $item->hpp_average,
                    'nilai_stok'           => $nilaiKeluar,
                    'stok_batang_before'   => $batangKeluar,
                    'stok_kubikasi_before' => round($kubikasiKeluar, 4),
                    'nilai_stok_before'    => $nilaiKeluar,
                    'stok_batang_after'    => 0,
                    'stok_kubikasi_after'  => 0,
                    'nilai_stok_after'     => 0,
                    'hpp_average'          => 0,
                ]);

                $item->update([
                    'stok_batang'   => 0,
                    'stok_kubikasi' => 0,
                    'nilai_stok'    => 0,
                    'hpp_average'   => 0,
                    'id_last_log'   => $log->id,
                ]);

                $qtyMasukStok = max(0, $batangKeluar - $totalKayuPecah);

                app(LogCoreStokService::class)->tambahStok(
                    idJenisKayu: $idJenisKayu,
                    panjang: $item->panjang,
                    qty: $qtyMasukStok,
                    hargaSatuan: 0,
                    referensi: $record,
                    keterangan: $keteranganLengkap,
                    tanggal: $tglProduksi,
                );
            }

            // 4. Update Main Record
            $record->update([
                'jumlah_batang' => $hasilReal,
                'hpp_average'   => $hppTerakhir,
            ]);

            // 5. Reset Table Terkait
            $this->resetTempatKayu($idLahan);
            $this->resetPivotSerahTerima($idLahan);
        });
    }

    private function resetTempatKayu(int $idLahan): void
    {
        $updated = DB::table('tempat_kayus')
            ->where('id_lahan', $idLahan)
            ->update([
                'jumlah_batang'   => 0,
                'status'          => 'belum serah',
                'diserahkan_oleh' => null,
                'diterima_oleh'   => null,
                'updated_at'      => now(),
            ]);

        if ($updated === 0) {
            $kayuMasuk = KayuMasuk::whereHas('detailTurusanKayus', fn($q) => $q->where('lahan_id', $idLahan))->first();
            if ($kayuMasuk) {
                DB::table('tempat_kayus')->insert([
                    'id_lahan'      => $idLahan,
                    'id_kayu_masuk' => $kayuMasuk->id,
                    'jumlah_batang' => 0,
                    'status'        => 'belum serah',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    private function resetPivotSerahTerima(int $idLahan): void
    {
        DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
            ->where('id_lahan', $idLahan)
            ->where('tipe', 'lahan_rotary')
            ->update([
                'jumlah_batang'   => 0,
                'kubikasi'        => 0,
                'status'          => 'Lahan Siap',
                'diserahkan_oleh' => null,
                'diterima_oleh'   => null,
                'updated_at'      => now(),
            ]);
    }
}
