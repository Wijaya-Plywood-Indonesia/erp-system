<?php

namespace App\Services;

use App\Models\PenggunaanLahanRotary;
use App\Models\NotaKayu;
use App\Models\HargaKayu;

class ProduksiInflowService
{

public function getLaporanBatch()
    {
        // 1. Ambil data mentah, urutkan ASC agar kronologi jahitannya benar
        $allRecords = PenggunaanLahanRotary::with(['lahan', 'jenisKayu'])
            ->orderBy('created_at', 'asc')
            ->get();

        $laporan = [];
        
        // 2. Kelompokkan berdasarkan Lahan dan Jenis Kayu
        $grouped = $allRecords->groupBy(function ($item) {
            return $item->id_lahan . '-' . $item->id_jenis_kayu;
        });

        foreach ($grouped as $records) {
            $tempGroup = [];
            
            foreach ($records as $record) {
                $tempGroup[] = $record;

                // JIKA ditemukan jumlah_batang > 0, berarti SATU BATCH FINISH
                if ($record->jumlah_batang > 0) {
                    $laporan[] = $this->formatBatchDataSimple($tempGroup);
                    $tempGroup = []; // Reset untuk batch selanjutnya di lahan yang sama
                }
            }

            // Jika masih ada sisa di tempGroup (berarti batch terakhir masih PROSES)
            if (!empty($tempGroup)) {
                $laporan[] = $this->formatBatchDataSimple($tempGroup);
            }
        }

        // 3. Urutkan dari yang terbaru (tgl buka desc) dan LIMIT 10 DATA
        return collect($laporan)
            // ->sortByDesc(fn($item) => $item['batch_info']['tgl_buka_lahan'])
            ->sortBy(fn($item) => $item['batch_info']['tgl_buka_lahan'])
            ->take(10) 
            ->values()
            ->all();
    }

    /**
     * Versi Simple: Tanpa Inflow & Outflow untuk Testing Cepat
     */
    private function formatBatchDataSimple($records)
    {
        $records = collect($records);
        $firstRecord = $records->first();
        // Cari record penutup dalam rombongan ini
        $lastRecord = $records->first(fn($item) => $item->jumlah_batang > 0);
        
        $status = $lastRecord ? 'SELESAI' : 'PROSES';

        return [
            'batch_info' => [
                'lahan' => $firstRecord->lahan->nama_lahan ?? 'N/A',
                'kode' => $firstRecord->lahan->kode_lahan ?? 'N/A',
                'jenis_kayu' => $firstRecord->jenisKayu->nama_kayu ?? 'N/A',
                'status' => $status,
                'tgl_buka_lahan' => $firstRecord->created_at->format('Y-m-d H:i:s'),
                'tgl_tutup_lahan' => $lastRecord ? $lastRecord->updated_at->format('Y-m-d H:i:s') : 'MASIH BERJALAN',
                'jumlah_batang_akhir' => $lastRecord ? $lastRecord->jumlah_batang : 0,
                'debug_count_row' => $records->count(), // Untuk melihat berapa row yang "dijahit"
            ],
            'inflow' => 'OFF (Testing Mode)',
            'outflow' => 'OFF (Testing Mode)',
        ];
    }
    // public function getLaporanBatch()
    // {
    //     // 1. Ambil data, urutkan dari yang terlama (asc) agar kronologi 'jahitan' record benar
    //     $allRecords = PenggunaanLahanRotary::with([
    //         'lahan',
    //         'jenisKayu',
    //         'detailProduksiPalet.setoranPaletUkuran',
    //         'produksi_rotary.mesin'
    //     ])
    //         ->orderBy('created_at', 'asc')
    //         ->get();

    //     $laporan = [];

    //     // 2. Kelompokkan berdasarkan Lahan dan Jenis Kayu
    //     $grouped = $allRecords->groupBy(function ($item) {
    //         return $item->id_lahan . '-' . $item->id_jenis_kayu;
    //     });

    //     foreach ($grouped as $records) {
    //         $tempGroup = [];

    //         foreach ($records as $record) {
    //             $tempGroup[] = $record;

    //             // JIKA ditemukan jumlah_batang > 0, berarti SATU BATCH SELESAI
    //             // Kita bungkus tempGroup ini jadi satu laporan, lalu reset untuk batch berikutnya
    //             if ($record->jumlah_batang > 0) {
    //                 $laporan[] = $this->formatBatchData($tempGroup);
    //                 $tempGroup = []; // Reset penampung untuk batch selanjutnya di lahan yang sama
    //             }
    //         }

    //         // Jika setelah loop selesai masih ada sisa di tempGroup (berarti batch masih PROSES)
    //         if (!empty($tempGroup)) {
    //             $laporan[] = $this->formatBatchData($tempGroup);
    //         }
    //     }

    //     // 3. Urutkan dari yang terbaru (tgl buka desc) dan LIMIT 10 DATA
    //     return collect($laporan)
    //         ->sortByDesc(fn($item) => $item['batch_info']['tgl_buka_lahan'])
    //         ->take(10)
    //         ->values()
    //         ->all();
    // }

    /**
     * Fungsi pembantu untuk memproses kumpulan record menjadi satu format batch
     */
    private function formatBatchData($records)
    {
        $records = collect($records);
        $firstRecord = $records->first();
        $lastRecord = $records->first(fn($item) => $item->jumlah_batang > 0);

        $tglBuka = $firstRecord->created_at;
        $tglTutup = $lastRecord ? $lastRecord->created_at : now();
        $status = $lastRecord ? 'SELESAI' : 'PROSES';

        $dataMasuk = $this->getInflowByPeriode($firstRecord->id_lahan, $tglTutup);

        // Gabungkan semua outflow dari semua record dalam batch ini
        $dataKeluar = $records->flatMap->detailProduksiPalet->map(function ($hasil) use ($firstRecord) {
            $ukuran = $hasil->setoranPaletUkuran;
            $totalLembar = (int) $hasil->total_lembar;
            $m3 = $ukuran ? ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $totalLembar) / 1000000000 : 0;

            return [
                'tgl' => $hasil->timestamp_laporan ?? $hasil->created_at,
                'mesin' => $firstRecord->produksi_rotary->mesin->nama_mesin ?? '-',
                'ukuran' => $ukuran ? $ukuran->dimensi : '-',
                'banyak' => $totalLembar,
                'kubikasi' => round($m3, 4),
            ];
        });

        return [
            'batch_info' => [
                'lahan' => $firstRecord->lahan->nama_lahan,
                'kode' => $firstRecord->lahan->kode_lahan,
                'jenis_kayu' => $firstRecord->jenisKayu->nama_kayu,
                'status' => $status,
                'tgl_buka_lahan' => $tglBuka->format('Y-m-d H:i:s'),
                'tgl_tutup_lahan' => $lastRecord ? $tglTutup->format('Y-m-d H:i:s') : 'MASIH BERJALAN',
                'jumlah_batang_akhir' => $lastRecord ? $lastRecord->jumlah_batang : 0,
            ],
            'inflow' => $dataMasuk->values(),
            'outflow' => $dataKeluar,
            'summary' => [
                'total_masuk_m3' => round($dataMasuk->sum('kubikasi'), 4),
                'total_keluar_m3' => round($dataKeluar->sum('kubikasi'), 4),
                'rendemen' => $dataMasuk->sum('kubikasi') > 0
                    ? round(($dataKeluar->sum('kubikasi') / $dataMasuk->sum('kubikasi')) * 100, 2) . '%'
                    : '0%'
            ]
        ];
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