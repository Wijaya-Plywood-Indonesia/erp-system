<?php

namespace App\Services;

use App\Models\PenggunaanLahanRotary;
use App\Models\NotaKayu;
use App\Models\HargaKayu;

class ProduksiInflowService
{
    public function getLaporanBatch(): array
    {
        // Ambil data mentah
        $records = PenggunaanLahanRotary::with([
            'lahan',
            'jenisKayu',
            'detailProduksiPalet.setoranPaletUkuran',
            'produksi_rotary.mesin'
        ])
            ->orderBy('created_at', 'asc')
            ->get();

        // 1. Kelompokkan record menjadi Batch-Batch mentah
        $rawBatches = $this->groupRecordsIntoBatches($records);

        $laporanFinal = [];
        $lastTutupPerLahan = [];

        // 2. Proses pembagian Inflow berdasarkan urutan kronologis
        foreach ($rawBatches as $batch) {
            $idLahan = $batch['id_lahan'];
            $start = $lastTutupPerLahan[$idLahan] ?? null;
            $end = $batch['tgl_buka_raw'];

            // Panggil Inflow
            $dataMasuk = $this->getInflowByWindow($idLahan, $start, $end, $batch['status']);

            // Simpan tgl_tutup untuk referensi batch selanjutnya jika status SELESAI
            if ($batch['status'] === 'SELESAI') {
                $lastTutupPerLahan[$idLahan] = $batch['tgl_tutup_raw'];
            }

            $laporanFinal[] = [
                'batch_info' => $batch['info'],
                'inflow' =>
                    // "OFF",
                    $dataMasuk,
                'summary' =>
                    // "OFF", 
                    // /*
                    [
                        'total_masuk_m3' => (float) round($dataMasuk->sum('kubikasi'), 4),
                        'total_keluar_m3' => (float) round($batch['total_keluar_m3'], 4),
                        'total_poin' => number_format($dataMasuk->sum('poin'), 0, ',', '.'),
                        'rendemen' => $dataMasuk->sum('kubikasi') > 0
                            ? round(($batch['total_keluar_m3'] / $dataMasuk->sum('kubikasi')) * 100, 2) . '%'
                            : '0%'
                    ],
                // */
                'outflow' =>
                    // "OFF" 
                    $batch['outflow_list']
            ];
        }

        // 3. Urutkan DESC untuk tampilan (10 data terbaru)
        return collect($laporanFinal)
            ->sortByDesc(fn($l) => $l['batch_info']['tgl_buka_lahan'])
            ->take(10)
            ->values()
            ->all();
    }

    private function groupRecordsIntoBatches($allRecords): array
    {
        $batches = [];
        $grouped = $allRecords->groupBy(fn($item) => $item->id_lahan . '-' . $item->id_jenis_kayu);

        foreach ($grouped as $records) {
            $tempGroup = [];
            foreach ($records as $record) {
                $tempGroup[] = $record;
                if ($record->jumlah_batang > 0) {
                    $batches[] = $this->stitchBatch($tempGroup);
                    $tempGroup = [];
                }
            }
            if (!empty($tempGroup)) {
                $batches[] = $this->stitchBatch($tempGroup);
            }
        }

        // Pastikan hasil akhirnya urut secara waktu (ASC) untuk pembagian inflow
        return collect($batches)->sortBy('tgl_buka_raw')->values()->all();
    }

    private function stitchBatch(array $tempGroup): array
    {
        $records = collect($tempGroup);
        /** @var \App\Models\PenggunaanLahanRotary $first */
        $first = $records->first();
        /** @var \App\Models\PenggunaanLahanRotary|null $last */
        $last = $records->first(fn($i) => $i->jumlah_batang > 0);

        $outflowData = $records->flatMap(fn($r) => $r->detailProduksiPalet);

        $totalKeluar = $outflowData->sum(function ($hasil) {
            $u = $hasil->setoranPaletUkuran;
            return $u ? ($u->panjang * $u->lebar * $u->tebal * $hasil->total_lembar) / 1000000000 : 0;
        });

        return [
            'id_lahan' => $first->id_lahan,
            'tgl_buka_raw' => $first->created_at,
            'tgl_tutup_raw' => $last ? $last->created_at : null,
            'status' => $last ? 'SELESAI' : 'PROSES',
            'total_keluar_m3' => (float) $totalKeluar,
            'info' => [
                'lahan' => $first->lahan->nama_lahan ?? '-',
                'kode' => $first->lahan->kode_lahan ?? '-',
                'jenis_kayu' => $first->jenisKayu->nama_kayu ?? '-',
                'status' => $last ? 'SELESAI' : 'PROSES',
                'tgl_buka_lahan' => $first->created_at->format('Y-m-d H:i:s'),
                'tgl_tutup_lahan' => $last ? $last->created_at->format('Y-m-d H:i:s') : 'MASIH BERJALAN',
                'jumlah_batang_akhir' => $last ? $last->jumlah_batang : 0,
            ],
            'outflow_list' => $outflowData->map(fn($h) => [
                'tgl' => $h->timestamp_laporan ?? $h->created_at,
                'banyak' => $h->total_lembar,
                'kw' => $h->kw
            ])->toArray()
        ];
    }

    // private $notaTerpakaiPerLahan = []; 

    private function getInflowByWindow($idLahan, $start, $end, $statusBatch)
    {
        $query = NotaKayu::with(['kayuMasuk.detailTurusanKayus'])
            ->where('status', 'like', '%Sudah Diperiksa%')
            ->whereHas('kayuMasuk.detailTurusanKayus', function ($q) use ($idLahan) {
                $q->where('lahan_id', $idLahan); // Filter spesifik lahan ini
            });

        $batasAtas = ($statusBatch === 'PROSES') ? now() : $end;

        $query->where('created_at', '<=', $batasAtas);

        if ($start) {
            $query->where('created_at', '>', $start);
        }

        return $query->get()->map(function ($nota) use ($idLahan) {
            $items = $nota->kayuMasuk->detailTurusanKayus->where('lahan_id', $idLahan);
            return [
                'tanggal' => $nota->created_at->format('Y-m-d H:i:s'),
                'seri' => $nota->kayuMasuk->seri ?? '-',
                'kubikasi' => (float) $items->sum('kubikasi'),
                'poin' => (int) $items->sum(fn($i) => $this->calculatePoin($i))
            ];
        });
    }

    private function calculatePoin($item)
    {
        $harga = $this->getHargaSatuan($item->id_jenis_kayu ?? 1, $item->grade ?? 1, $item->panjang ?? 130, $item->diameter);
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