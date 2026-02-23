<?php

namespace App\Services;

use App\Models\DetailHasilPaletRotary;
use App\Models\PenggunaanLahanRotary;
use App\Models\NotaKayu;
use App\Models\HargaKayu;

class ProduksiInflowService
{
    public function getLaporanBatch(): array
    {
        // 1. Ambil data mentah penggunaan lahan secara kronologis
        $allRecords = PenggunaanLahanRotary::with(['lahan', 'jenisKayu', 'produksi_rotary.mesin'])
            ->orderBy('created_at', 'asc')
            ->get();

        $batches = [];
        $grouped = $allRecords->groupBy(fn($item) => $item->id_lahan . '-' . $item->id_jenis_kayu);

        // 2. Tahap Penjahitan Batch
        foreach ($grouped as $records) {
            $tempGroup = [];
            foreach ($records as $record) {
                $tempGroup[] = $record;
                if ($record->jumlah_batang > 0) {
                    $batches[] = $this->stitchBatchWithOutflow($tempGroup);
                    $tempGroup = [];
                }
            }
            if (!empty($tempGroup)) {
                $batches[] = $this->stitchBatchWithOutflow($tempGroup);
            }
        }

        // 3. Tahap Pembagian Inflow & Finalisasi
        $laporanFinal = [];
        $lastTutupPerLahan = [];
        $sortedBatches = collect($batches)->sortBy(fn($b) => $b['tgl_buka_raw']);

        foreach ($sortedBatches as $batch) {
            $idLahan = $batch['id_lahan'];
            $start = $lastTutupPerLahan[$idLahan] ?? null;
            $end = $batch['tgl_buka_raw'];

            // Panggil Inflow (Nota Kayu)
            $dataMasuk = $this->getInflowByWindow($idLahan, $start, $end, $batch['status']);

            $tglInflowPertama = $dataMasuk->min('tanggal');

            // Jika ada inflow, gunakan tgl inflow pertama. Jika tidak, pakai tgl_buka_raw (fallback).
            $tglBukaFix = $tglInflowPertama ?: $batch['info']['tgl_buka_lahan'];

            if ($batch['status'] === 'SELESAI') {
                $lastTutupPerLahan[$idLahan] = $batch['tgl_tutup_raw'];
            }

            // 3. Update info batch dengan tanggal buka yang baru
            $batchInfo = $batch['info'];
            $batchInfo['tgl_buka_lahan'] = $tglBukaFix;

            $laporanFinal[] = [
                'batch_info' => $batchInfo,
                'inflow' => $dataMasuk,
                'outflow' => $batch['outflow_detail'], // Detail Harian + Mesin + Ukuran
                'summary' => [
                    'total_kayu_masuk' => (int) $dataMasuk->sum('banyak'),
                    'total_masuk_m3' => (float) round($dataMasuk->sum('kubikasi'), 4),
                    'total_keluar_m3' => (float) round($batch['grand_total_outflow_m3'], 4),
                    'total_poin' => number_format($dataMasuk->sum('poin'), 0, ',', '.'),
                    'rendemen' => $dataMasuk->sum('kubikasi') > 0
                        ? round(($batch['grand_total_outflow_m3'] / $dataMasuk->sum('kubikasi')) * 100, 2) . '%'
                        : '0%'
                ]
            ];
        }

        return collect($laporanFinal)
            ->sortByDesc(fn($l) => $l['batch_info']['tgl_buka_lahan'])
            ->take(10)
            ->values()
            ->all();
    }

    private function stitchBatchWithOutflow(array $tempGroup): array
    {
        $records = collect($tempGroup);
        $first = $records->first();
        $last = $records->first(fn($i) => $i->jumlah_batang > 0);

        // Ambil SEMUA Detail Palet yang terhubung dengan record-record di batch ini
        // Kita menggunakan penggunaan_lahan_id sebagai filter utama
        $idsPenggunaanLahan = $records->pluck('id')->toArray();

        // Query ke detail palet dengan Eager Loading untuk performa
        $outflowData = DetailHasilPaletRotary::with([
            'produksi.mesin',
            'produksi.detailPegawaiRotary',
            'setoranPaletUkuran'
        ])
            ->whereIn('id_penggunaan_lahan', $idsPenggunaanLahan)
            ->get();

        // Grouping Outflow: Tanggal + Mesin + Ukuran
        $groupedOutflow = $outflowData->map(function ($hasil) {
            $produksi = $hasil->produksi;
            $ukuran = $hasil->setoranPaletUkuran;
            $totalLembar = (int) ($hasil->total_lembar ?? 0);

            $m3 = $ukuran
                ? ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $totalLembar) / 10_000_000
                : 0;

            return [
                'tgl' => $produksi->tgl_produksi,
                'mesin' => $produksi->mesin->nama_mesin ?? 'Unknown',
                'jam_kerja' => "06:00 - 16:00",
                'ukuran' => $ukuran ? $ukuran->dimensi : '-',
                'banyak' => $totalLembar,
                'kubikasi' => $m3,
                'pekerja' => ($produksi->detailPegawaiRotary->count() ?? 0) . " Orang",
            ];
        })->groupBy(function ($item) {
            return $item['tgl'] . $item['mesin'] . $item['ukuran'];
        })->map(function ($group) {
            $f = $group->first();
            return [
                'tgl' => $f['tgl'],
                'mesin' => $f['mesin'],
                'jam_kerja' => $f['jam_kerja'],
                'ukuran' => $f['ukuran'],
                'total_banyak' => $group->sum('banyak'),
                'total_kubikasi' => round($group->sum('kubikasi'), 4),
                'pekerja' => $f['pekerja'],
            ];
        })->values()->toArray();

        return [
            'id_lahan' => $first->id_lahan,
            'tgl_buka_raw' => $first->created_at,
            'tgl_tutup_raw' => $last ? $last->created_at : null,
            'status' => $last ? 'SELESAI' : 'PROSES',
            'grand_total_outflow_m3' => collect($groupedOutflow)->sum('total_kubikasi'),
            'outflow_detail' => $groupedOutflow,
            'info' => [
                'lahan' => $first->lahan->nama_lahan ?? '-',
                'kode' => $first->lahan->kode_lahan ?? '-',
                'jenis_kayu' => $first->jenisKayu->nama_kayu ?? '-',
                'status' => $last ? 'SELESAI' : 'PROSES',
                'tgl_buka_lahan' => $first->created_at->format('Y-m-d H:i:s'),
                'tgl_tutup_lahan' => $last ? $last->created_at->format('Y-m-d H:i:s') : 'MASIH BERJALAN',
                'jumlah_batang_akhir' => $last ? $last->jumlah_batang : 0,
            ],
        ];
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
                'banyak' => $items->sum('kuantitas'),
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