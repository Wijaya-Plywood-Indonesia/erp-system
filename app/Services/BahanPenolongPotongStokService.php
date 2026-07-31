<?php

namespace App\Services;

use App\Models\BahanPenolongValidasi;
use App\Models\BarangUmum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BahanPenolongPotongStokService
{
    /**
     * Validasi & potong stok barang umum berdasarkan pemakaian bahan penolong
     * di sebuah produksi (Hp, Dempul, Repair, Rotary, Tembel Triplek, dst — model apapun).
     *
     * @param  Model  $produksi      record produksi (mis. ProduksiHp, ProduksiDempul)
     * @param  string $relasiBahan   nama method relasi hasMany di $produksi yang berisi
     *                               record bahan penolong (baik yang punya kolom 'nama_bahan'
     *                               string langsung, maupun yang punya relasi 'bahanPenolong'
     *                               ke BahanPenolongProduksi)
     * @param  int|null    $userId        id user yang memvalidasi
     * @param  string|null $namaValidator nama user yang memvalidasi (buat keterangan log)
     *
     * @throws \RuntimeException jika sudah divalidasi, data kosong, nama tidak match, atau stok kurang
     */
    public function validasiDanPotongStok(Model $produksi, string $relasiBahan, ?int $userId = null, ?string $namaValidator = null): void
    {
        $produksiType = get_class($produksi);
        $produksiId   = $produksi->id;

        if (BahanPenolongValidasi::sudahDivalidasi($produksiType, $produksiId)) {
            throw new \RuntimeException('Bahan penolong produksi ini sudah pernah divalidasi sebelumnya.');
        }

        // Eager load relasi 'bahanPenolong' kalau model-nya punya (pola FK, mis. Repair/Rotary)
        $relation     = $produksi->{$relasiBahan}();
        $relatedModel = $relation->getRelated();
        $query        = method_exists($relatedModel, 'bahanPenolong') ? $relation->with('bahanPenolong') : $relation;
        $bahanRecords = $query->get();

        if ($bahanRecords->isEmpty()) {
            throw new \RuntimeException('Tidak ada data bahan penolong untuk divalidasi.');
        }

        // 0. Tentukan nama bahan tiap baris (mendukung 2 pola: kolom nama_bahan / relasi bahanPenolong)
        $namaPerRow = $bahanRecords->map(fn($row) => $this->namaBahan($row));

        // 1. Cocokkan semua nama bahan ke Master Barang Umum (nama harus persis sama)
        $namaUnik  = $namaPerRow->unique();
        $barangMap = BarangUmum::with('stok')->whereIn('nama_barang', $namaUnik)->get()->keyBy('nama_barang');

        $tidakDitemukan = $namaUnik->diff($barangMap->keys());
        if ($tidakDitemukan->isNotEmpty()) {
            throw new \RuntimeException(
                'Barang berikut belum terdaftar di Master Barang Umum (nama harus persis sama): '
                . $tidakDitemukan->implode(', ')
            );
        }

        // 2. Cek stok cukup (jumlah diakumulasi dulu, karena bisa ada beberapa baris nama sama)
        $kebutuhan = $bahanRecords->groupBy(fn($row) => $this->namaBahan($row))
            ->map(fn($rows) => $rows->sum('jumlah'));

        $kurang = [];
        foreach ($kebutuhan as $nama => $qtyButuh) {
            $barang      = $barangMap[$nama];
            $stokSaatIni = (float) ($barang->stok?->stok_qty ?? 0);

            if ($stokSaatIni < $qtyButuh) {
                $kurang[] = "{$nama} (butuh {$qtyButuh}, tersedia {$stokSaatIni} {$barang->satuan})";
            }
        }

        if (!empty($kurang)) {
            throw new \RuntimeException('Stok tidak cukup untuk: ' . implode('; ', $kurang));
        }

        // 3. Semua aman -> potong stok tiap baris + kunci validasi, dalam satu transaksi
        DB::transaction(function () use ($bahanRecords, $barangMap, $produksi, $produksiType, $produksiId, $userId, $namaValidator) {
            $tanggalProduksi = $produksi->tanggal_produksi ?? $produksi->tanggal ?? now();
            $tanggalValidasi = now();

            $tanggalLabel = \Carbon\Carbon::parse($tanggalProduksi)->format('d/m/Y');
            $ket = 'Pemakaian bahan penolong - ' . $this->labelProduksi($produksiType) . " tanggal: {$tanggalLabel}";
            if ($namaValidator) {
                $ket .= " | Divalidasi oleh: {$namaValidator}";
            }

            foreach ($bahanRecords as $row) {
                $nama   = $this->namaBahan($row);
                $barang = $barangMap[$nama];

                app(BarangUmumInventoryService::class)->catatTransaksi(
                    idBarangUmum: $barang->id,
                    tipeTransaksi: 'keluar',
                    qty: (float) $row->jumlah,
                    tanggal: $tanggalValidasi,
                    keterangan: $ket,
                    referensiType: get_class($row),
                    referensiId: $row->id,
                );
            }

            BahanPenolongValidasi::create([
                'produksi_type'   => $produksiType,
                'produksi_id'     => $produksiId,
                'divalidasi_at'   => now(),
                'divalidasi_oleh' => $userId,
            ]);
        });
    }

    /**
     * Ubah nama class produksi jadi label yang enak dibaca.
     * "App\Models\ProduksiHp" -> "Produksi Hotpress"
     * "App\Models\ProduksiTembelTriplek" -> "Produksi Tembel Triplek"
     */
    protected function labelProduksi(string $produksiType): string
    {
        $base   = class_basename($produksiType);
        $spaced = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $base));

        $overrides = [
            'Produksi Hp' => 'Produksi Hotpress',
        ];

        return $overrides[$spaced] ?? $spaced;
    }

    /**
     * Ambil nama bahan dari satu baris record.
     * Mendukung 2 pola:
     * - kolom 'nama_bahan' string langsung (mis. BahanPenolongHp, BahanDempul)
     * - relasi 'bahanPenolong' (FK ke BahanPenolongProduksi, mis. BahanPenolongRepair, BahanPenolongRotary)
     */
    protected function namaBahan(Model $row): string
    {
        if (!empty($row->nama_bahan)) {
            return $row->nama_bahan;
        }

        if (method_exists($row, 'bahanPenolong') && $row->bahanPenolong) {
            return $row->bahanPenolong->nama_bahan_penolong;
        }

        throw new \RuntimeException("Tidak dapat menentukan nama bahan untuk record #{$row->id}.");
    }
}