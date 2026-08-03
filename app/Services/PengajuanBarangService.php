<?php

namespace App\Services;

use App\Models\PengajuanBarang;
use App\Models\PengajuanBarangDetail;
use Illuminate\Support\Facades\DB;

class PengajuanBarangService
{
    /**
     * Set keputusan approval untuk satu role ('kepala_produksi' | 'admin_barang').
     * Kalau setelah ini kedua-duanya sudah 'disetujui' dan belum pernah diproses,
     * stok Barang Umum otomatis dipotong (untuk semua item di pengajuan ini)
     * dalam transaksi yang sama.
     */
    public function approve(PengajuanBarang $pengajuan, string $role, string $keputusan, int $userId): void
    {
        if (!in_array($role, ['kepala_produksi', 'admin_barang'], true)) {
            throw new \InvalidArgumentException("Role tidak dikenali: {$role}");
        }

        if (!in_array($keputusan, ['disetujui', 'ditolak'], true)) {
            throw new \InvalidArgumentException("Keputusan tidak valid: {$keputusan}");
        }

        DB::transaction(function () use ($pengajuan, $role, $keputusan, $userId) {
            $pengajuan = PengajuanBarang::lockForUpdate()->findOrFail($pengajuan->id);

            if ($role === 'kepala_produksi') {
                if ($pengajuan->status_kepala_produksi !== 'menunggu') {
                    throw new \RuntimeException('Pengajuan ini sudah pernah diputuskan oleh Kepala Produksi.');
                }
                $pengajuan->update([
                    'status_kepala_produksi' => $keputusan,
                    'disetujui_kepala_oleh'  => $userId,
                    'disetujui_kepala_at'    => now(),
                ]);
            } else {
                if ($pengajuan->status_admin_barang !== 'menunggu') {
                    throw new \RuntimeException('Pengajuan ini sudah pernah diputuskan oleh Admin Barang.');
                }
                $pengajuan->update([
                    'status_admin_barang'  => $keputusan,
                    'disetujui_admin_oleh' => $userId,
                    'disetujui_admin_at'   => now(),
                ]);
            }

            $pengajuan->refresh();

            if ($pengajuan->sudahDisetujuiKeduanya() && !$pengajuan->sudahDiproses()) {
                $this->prosesPotongStok($pengajuan);
            }
        });
    }

    protected function prosesPotongStok(PengajuanBarang $pengajuan): void
    {
        $items = $pengajuan->items()->with('barangUmum.stok')->get();

        if ($items->isEmpty()) {
            throw new \RuntimeException('Pengajuan ini tidak punya item barang.');
        }

        // Cek stok cukup (diakumulasi dulu per barang, jaga-jaga ada barang yang sama di beberapa baris)
        $kebutuhan = $items->groupBy('id_barang_umum')->map(fn($rows) => $rows->sum('jumlah'));

        $kurang = [];
        foreach ($kebutuhan as $idBarang => $qtyButuh) {
            $barang      = $items->firstWhere('id_barang_umum', $idBarang)->barangUmum;
            $stokSaatIni = (float) ($barang->stok?->stok_qty ?? 0);

            if ($stokSaatIni < $qtyButuh) {
                $kurang[] = "{$barang->nama_barang} (butuh {$qtyButuh}, tersedia {$stokSaatIni} {$barang->satuan})";
            }
        }

        if (!empty($kurang)) {
            // Approval tetap tersimpan, tapi stok belum dipotong (diproses_at masih null)
            // supaya bisa dicoba proses ulang manual setelah stok cukup.
            throw new \RuntimeException(
                'Stok tidak cukup untuk: ' . implode('; ', $kurang)
                . '. Persetujuan tetap tersimpan, silakan proses ulang setelah stok tersedia.'
            );
        }

        $keterangan = "Pengajuan barang - lokasi: {$pengajuan->lokasi_penggunaan}"
            . " | Diajukan oleh: " . ($pengajuan->pengaju?->name ?? '-')
            . " | Disetujui Kepala Produksi: " . ($pengajuan->kepalaProduksi?->name ?? '-')
            . " | Disetujui Admin Barang: " . ($pengajuan->adminBarang?->name ?? '-');

        foreach ($items as $item) {
            app(BarangUmumInventoryService::class)->catatTransaksi(
                idBarangUmum: $item->id_barang_umum,
                tipeTransaksi: 'keluar',
                qty: (float) $item->jumlah,
                tanggal: now(),
                keterangan: $keterangan,
                referensiType: PengajuanBarangDetail::class,
                referensiId: $item->id,
            );
        }

        $pengajuan->update(['diproses_at' => now()]);
    }

    /**
     * Coba proses ulang potong stok secara manual (mis. dulu gagal karena stok kurang,
     * sekarang stok sudah tersedia lagi).
     */
    public function cobaProsesUlang(PengajuanBarang $pengajuan): void
    {
        DB::transaction(function () use ($pengajuan) {
            $pengajuan = PengajuanBarang::lockForUpdate()->findOrFail($pengajuan->id);

            if (!$pengajuan->sudahDisetujuiKeduanya()) {
                throw new \RuntimeException('Pengajuan ini belum disetujui oleh kedua pihak.');
            }

            if ($pengajuan->sudahDiproses()) {
                throw new \RuntimeException('Pengajuan ini sudah diproses sebelumnya.');
            }

            $this->prosesPotongStok($pengajuan);
        });
    }
}