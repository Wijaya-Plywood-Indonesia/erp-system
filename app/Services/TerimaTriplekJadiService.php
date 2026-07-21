<?php

namespace App\Services;

use App\Models\HppTriplekJadiLog;
use App\Models\StokTriplekJadi;
use App\Models\TriplekJadiMutasiKeluar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Menangani konfirmasi penerimaan barang keluaran Gudang Triplek Jadi
 * di tujuan mana pun (Produksi Nyusup / Gudang Satu / Produksi Sanding).
 *
 * $serahTerima boleh SerahTerimaGudangSatu ATAU SerahTerimaHp — syaratnya
 * cuma satu: punya kolom id_triplek_mutasi_keluar yang terisi. Record ini
 * juga dipakai sebagai referensi morph saat menambah stok tujuan.
 *
 * DIPANGGIL DI DALAM TRANSAKSI (oleh relation manager) — jangan buka
 * transaksi sendiri di sini.
 *
 * Efek:
 *  1. Potong stok triplek jadi + tulis HppTriplekJadiLog tipe 'keluar'
 *     (baru dipotong SEKARANG, saat dikonfirmasi — bukan saat dikirim).
 *  2. Tandai mutasi keluar sebagai diterima.
 *  3. Jika diterima di Gudang Satu: tambah stok plywood siap jual via
 *     StokGudangSatuService. Tujuan produksi (Nyusup/Sanding) tidak
 *     menambah stok apa pun di sini.
 */
class TerimaTriplekJadiService
{
    public function konfirmasi(Model $serahTerima, bool $tambahStokGudangSatu): void
    {
        $idMutasi = $serahTerima->id_triplek_mutasi_keluar;

        if (! $idMutasi) {
            throw new \RuntimeException('Record serah terima ini tidak tertaut ke mutasi keluar Triplek Jadi.');
        }

        $mutasi = TriplekJadiMutasiKeluar::with('jenisKayu')
            ->lockForUpdate()
            ->findOrFail($idMutasi);

        if ($mutasi->status === TriplekJadiMutasiKeluar::STATUS_DITERIMA) {
            throw new \RuntimeException('Mutasi keluar ini sudah pernah dikonfirmasi diterima.');
        }

        $qty      = (int) $mutasi->stok_lembar;
        $kubikasi = (float) $mutasi->stok_kubikasi;
        $userName = Auth::user()?->name ?? 'System';

        // ── 1. Potong stok Triplek Jadi ──
        $stok = StokTriplekJadi::where('id_jenis_kayu', $mutasi->id_jenis_kayu)
            ->where('panjang', $mutasi->panjang)
            ->where('lebar', $mutasi->lebar)
            ->where('tebal', $mutasi->tebal)
            ->where('kw_grade', $mutasi->kw_grade)
            ->lockForUpdate()
            ->first();

        if (! $stok) {
            throw new \RuntimeException('Baris stok Triplek Jadi tidak ditemukan untuk spesifikasi ini.');
        }

        if ($qty > (int) $stok->stok_lembar) {
            throw new \RuntimeException(
                'Stok Triplek Jadi tidak mencukupi saat konfirmasi. Tersedia: ' . $stok->stok_lembar . ' lembar.'
            );
        }

        $before = [
            'lembar'   => (int) $stok->stok_lembar,
            'kubikasi' => (float) $stok->stok_kubikasi,
            'nilai'    => (float) $stok->nilai_stok,
        ];

        // Nilai keluar mengikuti HPP average berjalan.
        $nilaiKeluar = round($qty * (float) $stok->hpp_average, 2);

        $after = [
            'lembar'   => $before['lembar'] - $qty,
            'kubikasi' => round($before['kubikasi'] - $kubikasi, 6),
            'nilai'    => round($before['nilai'] - $nilaiKeluar, 2),
        ];

        $log = HppTriplekJadiLog::create([
            'id_jenis_kayu'        => $mutasi->id_jenis_kayu,
            'panjang'              => $mutasi->panjang,
            'lebar'                => $mutasi->lebar,
            'tebal'                => $mutasi->tebal,
            'kw_grade'             => $mutasi->kw_grade,
            'tanggal'              => now(),
            'tipe_transaksi'       => 'keluar',
            'referensi_type'       => TriplekJadiMutasiKeluar::class,
            'referensi_id'         => $mutasi->id,
            'total_lembar'         => $qty,
            'total_kubikasi'       => $kubikasi,
            'hpp_pekerja'          => 0,
            'hpp_bahan_penolong'   => 0,
            'hpp_average'          => (float) $stok->hpp_average,
            'nilai_stok'           => $nilaiKeluar,
            'stok_lembar_before'   => $before['lembar'],
            'stok_kubikasi_before' => $before['kubikasi'],
            'nilai_stok_before'    => $before['nilai'],
            'stok_lembar_after'    => $after['lembar'],
            'stok_kubikasi_after'  => $after['kubikasi'],
            'nilai_stok_after'     => $after['nilai'],
            'keterangan'           => sprintf(
                'Keluar ke %s (Mutasi #%d) | Dikonfirmasi: %s',
                $mutasi->tujuan,
                $mutasi->id,
                $userName
            ),
        ]);

        $stok->update([
            'stok_lembar'   => $after['lembar'],
            'stok_kubikasi' => $after['kubikasi'],
            'nilai_stok'    => $after['nilai'],
            'id_last_log'   => $log->id,
        ]);

        // ── 2. Tandai mutasi keluar diterima ──
        $mutasi->update([
            'status'          => TriplekJadiMutasiKeluar::STATUS_DITERIMA,
            'dikonfirmasi_by' => Auth::id(),
            'dikonfirmasi_at' => now(),
        ]);

        // ── 3. Tambah stok Gudang Satu (hanya konteks Gudang Satu) ──
        if ($tambahStokGudangSatu) {
            app(StokGudangSatuService::class)->tambah(
                idJenisKayu: $mutasi->id_jenis_kayu,
                panjang: $mutasi->panjang,
                lebar: $mutasi->lebar,
                tebal: $mutasi->tebal,
                kwGrade: $mutasi->kw_grade,
                lembar: (float) $qty,
                kubikasi: $kubikasi,
                keterangan: 'Terima barang dari Gudang Triplek Jadi (Mutasi Keluar #' . $mutasi->id . ')',
                referensi: $serahTerima,
            );
        }
    }
}