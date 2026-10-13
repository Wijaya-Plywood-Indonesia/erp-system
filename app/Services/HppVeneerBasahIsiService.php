<?php

namespace App\Services;

use App\Models\DetailHasilPaletRotary;
use App\Models\HppVeneerBasahLog;
use App\Models\PenggunaanLahanRotary;

/**
 * HppVeneerBasahIsiService
 *
 * Mengisi komponen HPP (kayu, pekerja, mesin, bahan penolong) pada log MASUK
 * veneer basah dari batch lahannya — rumus sama dengan Persentase Kayu.
 *
 *   - Batch lahan SUDAH selesai  → angka FINAL
 *   - Batch lahan BELUM selesai  → angka SEMENTARA = HPP batch selesai terakhir lahan yang sama
 *                                  (ditandai " [HPP SEMENTARA]" di keterangan),
 *                                  tidak ada batch sebelumnya → dibiarkan (nilai ikut rata-rata)
 *
 * Setelah komponen terisi, nilai & moving average kombinasinya dihitung ulang
 * oleh HppVeneerBasahRecalcService.
 */
class HppVeneerBasahIsiService
{
    public const TANDA_SEMENTARA = ' [HPP SEMENTARA]';

    /** @var array<int, array> cache komponen per id closure */
    private array $cacheKomponen = [];

    /** @var array<string,int> jumlah log yang dilewati per alasan (untuk diagnosa) */
    public array $alasanLewati = [];

    /** @var array<string,int[]> contoh id log per alasan (maks 5) */
    public array $contohLewati = [];

    public function __construct(
        protected HppVeneerBasahBatchService $batch,
        protected HppVeneerBasahRecalcService $recalc,
    ) {}

    /**
     * Dipanggil saat palet diterima gudang: isi HPP + hitung ulang kombinasinya.
     */
    public function prosesLogBaru(HppVeneerBasahLog $log): string
    {
        // baca ulang dari DB: log baru dibuat dengan 'Masuk' (kapital), DB menyimpan enum 'masuk'
        $log = $log->fresh() ?? $log;

        $status = $this->isiLogMasukPalet($log);

        if ($status !== 'lewati') {
            $this->hitungUlangKombinasiLog($log);
        }

        return $status;
    }

    /**
     * Isi komponen HPP satu log masuk palet.
     *
     * @return string 'final' | 'sementara' | 'lewati'
     */
    public function isiLogMasukPalet(HppVeneerBasahLog $log): string
    {
        if (strtolower((string) $log->tipe_transaksi) !== 'masuk'
            || $log->referensi_type !== DetailHasilPaletRotary::class
            || ! $log->referensi_id) {
            return $this->lewati($log, 'bukan log masuk palet');
        }

        $palet = DetailHasilPaletRotary::with('penggunaanLahan')->find($log->referensi_id);

        if (! $palet) {
            return $this->lewati($log, 'palet sudah tidak ada');
        }

        $penggunaan = $palet->penggunaanLahan;

        if (! $penggunaan) {
            return $this->lewati($log, 'palet tanpa baris penggunaan lahan');
        }

        $sementara = false;
        $komponen = null;

        $closure = $this->batch->cariClosure($penggunaan);
        $poinNol = false;
        if ($closure) {
            $k = $this->komponen($closure);
            $komponen = $k['punya_poin'] ? $k : null;
            $poinNol = ! $k['punya_poin'];
        }

        if (! $komponen) {
            $komponen = $this->komponenSementara($penggunaan);
            $sementara = true;
        }

        if (! $komponen) {
            return $this->lewati($log, $poinNol
                ? 'batch selesai tapi poin kayu 0 (tidak ada inflow) & tidak ada batch sebelumnya'
                : 'lahan belum pernah selesai & tidak ada batch sebelumnya');
        }

        $this->terapkan($log, $komponen, $sementara);

        return $sementara ? 'sementara' : 'final';
    }

    /**
     * Dipanggil saat "Selesaikan Lahan": finalkan semua log palet milik batch ini
     * (termasuk yang tadinya sementara) lalu hitung ulang tiap kombinasinya.
     */
    public function finalisasiBatchLahan(PenggunaanLahanRotary $closure): array
    {
        if ((int) $closure->jumlah_batang <= 0) {
            return ['status' => 'bukan_closure', 'logs' => 0];
        }

        unset($this->cacheKomponen[$closure->id]);
        $k = $this->komponen($closure);

        if (! $k['punya_poin']) {
            return ['status' => 'poin_nol', 'logs' => 0];
        }

        $paletIds = DetailHasilPaletRotary::whereIn('id_penggunaan_lahan', $k['ids_penggunaan_lahan'])->pluck('id');

        $logs = HppVeneerBasahLog::where('referensi_type', DetailHasilPaletRotary::class)
            ->whereIn('referensi_id', $paletIds)
            ->where('tipe_transaksi', 'masuk')
            ->get();

        foreach ($logs as $log) {
            $this->terapkan($log, $k, false);
        }

        $kombinasi = $logs->unique(fn ($l) => $this->kunciKombinasi($l));
        foreach ($kombinasi as $log) {
            $this->hitungUlangKombinasiLog($log);
        }

        return [
            'status' => 'final',
            'logs' => $logs->count(),
            'kombinasi' => $kombinasi->count(),
            'hpp_total' => $k['hpp_total'],
        ];
    }

    private function lewati(HppVeneerBasahLog $log, string $alasan): string
    {
        $this->alasanLewati[$alasan] = ($this->alasanLewati[$alasan] ?? 0) + 1;

        if (count($this->contohLewati[$alasan] ?? []) < 5) {
            $this->contohLewati[$alasan][] = $log->id;
        }

        return 'lewati';
    }

    public function hitungUlangKombinasiLog(HppVeneerBasahLog $log): array
    {
        return $this->recalc->hitungUlangKombinasi(
            (int) $log->id_jenis_kayu,
            $log->getRawOriginal('panjang'),
            $log->getRawOriginal('lebar'),
            $log->getRawOriginal('tebal'),
            $log->kw
        );
    }

    public function kunciKombinasi(HppVeneerBasahLog $log): string
    {
        return implode('|', [
            $log->id_jenis_kayu,
            (float) $log->getRawOriginal('panjang'),
            (float) $log->getRawOriginal('lebar'),
            (float) $log->getRawOriginal('tebal'),
            $log->kw ?? '',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function komponen(PenggunaanLahanRotary $closure): array
    {
        return $this->cacheKomponen[$closure->id]
            ??= $this->batch->komponenUntukClosure($closure);
    }

    /** HPP batch selesai terakhir pada lahan yang sama (sebelum baris ini). */
    private function komponenSementara(PenggunaanLahanRotary $penggunaan): ?array
    {
        $sebelumnya = PenggunaanLahanRotary::query()
            ->where('id_lahan', $penggunaan->id_lahan)
            ->where('jumlah_batang', '>', 0)
            ->where('created_at', '<', $penggunaan->created_at)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $sebelumnya) {
            return null;
        }

        $k = $this->komponen($sebelumnya);

        return $k['punya_poin'] ? $k : null;
    }

    private function terapkan(HppVeneerBasahLog $log, array $k, bool $sementara): void
    {
        $log->update([
            'hpp_kayu' => $k['hpp_kayu'],
            'hpp_pekerja' => $k['hpp_pekerja'],
            'hpp_mesin' => $k['hpp_mesin'],
            'hpp_bahan_penolong' => $k['hpp_bahan_penolong'],
            'keterangan' => $this->tandai($log->keterangan, $sementara),
        ]);
    }

    private function tandai(?string $keterangan, bool $sementara): ?string
    {
        $dasar = str_replace(self::TANDA_SEMENTARA, '', (string) $keterangan);

        if (! $sementara) {
            return $dasar === '' ? $keterangan : $dasar;
        }

        // kolom keterangan varchar(255)
        $dasar = mb_substr($dasar, 0, 255 - mb_strlen(self::TANDA_SEMENTARA));

        return $dasar.self::TANDA_SEMENTARA;
    }
}