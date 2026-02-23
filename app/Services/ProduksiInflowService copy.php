<?php

namespace App\Services;

use App\Models\PenggunaanLahanRotary;
use App\Models\NotaKayu;
use App\Models\HargaKayu;

class ProduksiInflowService
{

    public function getLaporanBatch()
    {
        $sesiLahan = PenggunaanLahanRotary::with([
            'lahan',
            'jenisKayu',
            'detailProduksiPalet.setoranPaletUkuran',
            'produksi_rotary.mesin'
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        $laporan = [];

        foreach ($sesiLahan as $index => $sesi) {
            // A. Penentuan Batas Waktu yang lebih fleksibel
            $batchSebelumnya = $sesiLahan->get($index + 1);

            // Mulai dari akhir batch sebelumnya (jika ada)
            $waktuMulai = $batchSebelumnya ? $batchSebelumnya->updated_at : null;

            // Batas akhir inflow adalah saat sesi ini selesai (updated_at)
            // karena jumlah_batang diisi di akhir, updated_at adalah tanda batch benar-benar tutup.
            // Kita hanya butuh waktu selesai (sebagai batas atas)
            $waktuSelesai = $sesi->updated_at;

            // Panggil fungsi yang baru (hanya butuh 2 parameter)
            $dataMasuk = $this->getInflowByPeriode($sesi->id_lahan, $waktuSelesai);

            // C. Ambil Outflow
            $dataKeluar = $sesi->detailProduksiPalet->map(function ($hasil) use ($sesi) {
                $ukuran = $hasil->setoranPaletUkuran;
                $totalLembar = (int) $hasil->total_lembar;
                $m3 = $ukuran ? ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $totalLembar) / 1000000000 : 0;

                return [
                    'tgl' => $hasil->timestamp_laporan ?? $sesi->created_at,
                    'mesin' => $sesi->produksi_rotary->mesin->nama_mesin ?? '-',
                    'ukuran' => $ukuran ? $ukuran->dimensi : '-',
                    'banyak' => $totalLembar,
                    'kubikasi' => round($m3, 4),
                    'kw' => $hasil->kw,
                ];
            });



            $status = ($sesi->jumlah_batang > 0) ? 'HABIS' : 'PROSES';

            $laporan[] = [
                'batch_info' => [
                    'lahan' => $sesi->lahan->nama_lahan,
                    'kode' => $sesi->lahan->kode_lahan,
                    'jenis_kayu' => $sesi->jenisKayu->nama_kayu,
                    'status' => $status,
                    'tgl_buka_lahan' => $sesi->created_at->format('Y-m-d H:i:s'),
                    'tgl_tutup_lahan' => $sesi->updated_at->format('Y-m-d H:i:s'),
                    'jumlah_batang_akhir' => $sesi->jumlah_batang,
                ],
                'inflow' => $dataMasuk->values(), // values() untuk reset index array
                'outflow' => $dataKeluar,
                'summary' => [
                    'total_masuk_m3' => round($dataMasuk->sum('kubikasi'), 4),
                    'total_keluar_m3' => round($dataKeluar->sum('kubikasi'), 4),
                    'total_poin' => number_format($dataMasuk->sum('poin'), 0, ',', '.'),
                    'rendemen' => $dataMasuk->sum('kubikasi') > 0
                        ? round(($dataKeluar->sum('kubikasi') / $dataMasuk->sum('kubikasi')) * 100, 2) . '%'
                        : '0%'
                ]
            ];
        }

        return $laporan;
    }

    // Ganti property lama dengan array asosiatif
    private $notaTerpakaiPerLahan = []; 

    private function getInflowByPeriode($idLahan, $end)
    {
        // Inisialisasi array untuk lahan tersebut jika belum ada
        if (!isset($this->notaTerpakaiPerLahan[$idLahan])) {
            $this->notaTerpakaiPerLahan[$idLahan] = [];
        }

        $query = NotaKayu::with(['kayuMasuk.detailTurusanKayus'])
            ->where('status', 'like', '%Sudah Diperiksa%')
            ->whereHas('kayuMasuk.detailTurusanKayus', function ($q) use ($idLahan) {
                $q->where('lahan_id', $idLahan); // Filter spesifik lahan ini
            })
            ->where('created_at', '<=', $end)
            // HANYA cek nota yang sudah terpakai DI LAHAN INI SAJA
            ->whereNotIn('id', $this->notaTerpakaiPerLahan[$idLahan]) 
            ->orderBy('created_at', 'desc');

        $notas = $query->get();

        // Simpan ke bucket khusus lahan ini
        foreach ($notas as $n) {
            $this->notaTerpakaiPerLahan[$idLahan][] = $n->id;
        }

        return $notas->map(function ($nota) use ($idLahan) {
            $items = $nota->kayuMasuk->detailTurusanKayus->where('lahan_id', $idLahan);

            return [
                'tanggal' => $nota->created_at->format('Y-m-d H:i:s'), // Sekalian diformat biar enak dilihat
                'seri' => $nota->kayuMasuk->seri ?? '-',
                'kubikasi' => (float) $items->sum('kubikasi'),
                'poin' => (int) $items->sum(fn($i) => $this->calculatePoin($i))
            ];
        });
    }
    private function calculatePoin($item)
    {
        // 1. Panggil fungsi getHargaSatuan
        $harga = $this->getHargaSatuan(
            $item->id_jenis_kayu ?? 1,
            $item->grade ?? 1,
            $item->panjang ?? 130,
            $item->diameter
        );

        // 2. Jalankan rumus: Harga * Kubikasi * 1000
        // Kita gunakan round untuk memastikan presisi angka
        return (int) round(($harga ?? 0) * round($item->kubikasi, 4) * 1000);
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
}


// public function getFinalLaporanBatch($dataKeluar) 
// {
//     $dataMasuk = $this->getInflowPerLahan();
//     $laporan = [];
//     $saldoLahan = []; // Penampung saldo per id_lahan
//     $batchAktif = []; // Penampung data batch yang sedang berjalan per id_lahan

//     // Gabungkan Masuk dan Keluar, urutkan ASC
//     $history = $this->mergeAndSort($dataMasuk, $dataKeluar);

//     foreach ($history as $row) {
//         $idLahan = $row['lahan_id'];

//         // Inisialisasi jika lahan baru muncul
//         if (!isset($saldoLahan[$idLahan])) {
//             $saldoLahan[$idLahan] = 0;
//             $batchAktif[$idLahan] = $this->emptyBatch($row['kode_lahan']);
//         }

//         if ($row['tipe'] == 'MASUK') {
//             $saldoLahan[$idLahan] += $row['kubikasi'];
//             $batchAktif[$idLahan]['data_masuk'][] = $row;
//             $batchAktif[$idLahan]['total_masuk_kubikasi'] += $row['kubikasi'];
//         } else {
//             $saldoLahan[$idLahan] -= $row['kubikasi_keluar'];
//             $batchAktif[$idLahan]['data_keluar'][] = $row;
//         }

//         // Cek apakah Saldo Habis (Tutup Batch)
//         if ($saldoLahan[$idLahan] <= 0.01 && $batchAktif[$idLahan]['total_masuk_kubikasi'] > 0) {
//             $batchAktif[$idLahan]['status'] = 'HABIS';
//             $laporan[] = $batchAktif[$idLahan];

//             // Reset untuk batch berikutnya di lahan yang sama
//             $saldoLahan[$idLahan] = 0;
//             $batchAktif[$idLahan] = $this->emptyBatch($row['kode_lahan']);
//         }
//     }

//     // Ambil sisa batch yang belum habis
//     foreach ($batchAktif as $sisa) {
//         if ($sisa['total_masuk_kubikasi'] > 0) {
//             $sisa['status'] = 'BELUM HABIS';
//             $laporan[] = $sisa;
//         }
//     }

//     // Point 2: Urutkan DESC (Terbaru di atas)
//     return collect($laporan)->sortByDesc(function($item) {
//         return $item['data_masuk'][0]['tanggal'] ?? now();
//     })->values();
// }