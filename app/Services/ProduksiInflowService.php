<?php

namespace App\Services;

use App\Models\NotaKayu;
use App\Models\HargaKayu;
use Illuminate\Support\Collection;

class ProduksiInflowService
{
    public function getInflowPerLahan(): Collection
    {
        // 1. Ambil semua detail yang sudah divalidasi
        $notalist = NotaKayu::with(['kayuMasuk.detailTurusanKayus.lahan', 'kayuMasuk.penggunaanSupplier'])
            ->where('status', 'like', '%Sudah Diperiksa%')
            ->get();

        $allDetails = collect();

        foreach ($notalist as $nota) {
            // Kita grouping detail di dalam nota berdasarkan ID Lahan
            $groupedByLahan = $nota->kayuMasuk->detailTurusanKayus->groupBy('id_lahan');

            foreach ($groupedByLahan as $lahanId => $items) {
                $totalBatang = $items->sum('kuantitas');
                $totalKubikasi = round($items->sum('kubikasi'), 4);
                
                // Kalkulasi Poin khusus untuk lahan ini saja di dalam nota tersebut
                $poinLahan = $items->sum(function ($item) {
                    $harga = $this->getHargaSatuan(
                        $item->id_jenis_kayu ?? 1,
                        $item->grade ?? 1,
                        $item->panjang ?? 130,
                        $item->diameter
                    );
                    return round(($harga ?? 0) * round($item->kubikasi, 4) * 1000);
                });

                $allDetails->push([
                    'lahan_id'     => $items->first()->lahan->id,
                    'kode_lahan'   => $items->first()->lahan->kode_lahan ?? 'N/A',
                    'nota_id'      => $nota->id,
                    'tanggal'      => $nota->created_at,
                    'seri'         => $nota->kayuMasuk->seri ?? '-',
                    'total_batang' => $totalBatang,
                    'kubikasi'     => $totalKubikasi,
                    'poin'         => (int) $poinLahan,
                    'supplier'     => $nota->kayuMasuk->penggunaanSupplier?->nama_supplier ?? '-',
                ]);
            }
        }

        // 2. Kembalikan data yang sudah terpecah per lahan, urutkan tanggal ASC untuk proses Saldo
        return $allDetails->sortBy('tanggal');
    }

    private function getHargaSatuan($idJenisKayu, $grade, $panjang, $diameter)
    {
        return HargaKayu::where('id_jenis_kayu', $idJenisKayu)
            ->where('grade', $grade)
            ->where('panjang', $panjang)
            ->where('diameter_terkecil', '<=', $diameter)
            ->where('diameter_terbesar', '>=', $diameter)
            ->orderBy('diameter_terkecil', 'desc')
            ->value('harga_beli') ?? 0;
    }

    public function getFinalLaporanBatch($dataKeluar) 
    {
        $dataMasuk = $this->getInflowPerLahan();
        $laporan = [];
        $saldoLahan = []; // Penampung saldo per id_lahan
        $batchAktif = []; // Penampung data batch yang sedang berjalan per id_lahan

        // Gabungkan Masuk dan Keluar, urutkan ASC
        $history = $this->mergeAndSort($dataMasuk, $dataKeluar);

        foreach ($history as $row) {
            $idLahan = $row['lahan_id'];
            
            // Inisialisasi jika lahan baru muncul
            if (!isset($saldoLahan[$idLahan])) {
                $saldoLahan[$idLahan] = 0;
                $batchAktif[$idLahan] = $this->emptyBatch($row['kode_lahan']);
            }

            if ($row['tipe'] == 'MASUK') {
                $saldoLahan[$idLahan] += $row['kubikasi'];
                $batchAktif[$idLahan]['data_masuk'][] = $row;
                $batchAktif[$idLahan]['total_masuk_kubikasi'] += $row['kubikasi'];
            } else {
                $saldoLahan[$idLahan] -= $row['kubikasi_keluar'];
                $batchAktif[$idLahan]['data_keluar'][] = $row;
            }

            // Cek apakah Saldo Habis (Tutup Batch)
            if ($saldoLahan[$idLahan] <= 0.01 && $batchAktif[$idLahan]['total_masuk_kubikasi'] > 0) {
                $batchAktif[$idLahan]['status'] = 'HABIS';
                $laporan[] = $batchAktif[$idLahan];
                
                // Reset untuk batch berikutnya di lahan yang sama
                $saldoLahan[$idLahan] = 0;
                $batchAktif[$idLahan] = $this->emptyBatch($row['kode_lahan']);
            }
        }

        // Ambil sisa batch yang belum habis
        foreach ($batchAktif as $sisa) {
            if ($sisa['total_masuk_kubikasi'] > 0) {
                $sisa['status'] = 'BELUM HABIS';
                $laporan[] = $sisa;
            }
        }

        // Point 2: Urutkan DESC (Terbaru di atas)
        return collect($laporan)->sortByDesc(function($item) {
            return $item['data_masuk'][0]['tanggal'] ?? now();
        })->values();
    }
}

