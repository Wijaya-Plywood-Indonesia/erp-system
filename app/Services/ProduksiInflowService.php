<?php

namespace App\Services;

use App\Models\DetailHasilPaletRotary;
use App\Models\HargaPegawai;
use App\Models\PenggunaanLahanRotary;
use App\Models\NotaKayu;
use App\Models\HargaKayu;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ProduksiInflowService
{
    public function getLaporanBatch()
    {
        // Ambil baris penutup batch dengan Pagination
        $paginatedClosures = PenggunaanLahanRotary::with([
            'lahan:id,nama_lahan,kode_lahan',
            'jenisKayu:id,nama_kayu'
        ])
            ->where('jumlah_batang', '>', 0)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $laporanFinal = [];

        foreach ($paginatedClosures as $closure) {
            $batchRecords = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('id_jenis_kayu', $closure->id_jenis_kayu)
                ->where('created_at', '<=', $closure->created_at)
                ->orderBy('created_at', 'desc')
                ->get();

            $tempGroup = [];
            foreach ($batchRecords as $record) {
                $tempGroup[] = $record;
                if ($record->id !== $closure->id && $record->jumlah_batang > 0) {
                    array_pop($tempGroup);
                    break;
                }
            }

            $tempGroup = array_reverse($tempGroup);
            $batch = $this->stitchBatchWithOutflow($tempGroup);

            // Tentukan Inflow Window
            $lastClosure = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('created_at', '<', $batch['tgl_buka_raw'])
                ->where('jumlah_batang', '>', 0)
                ->orderBy('created_at', 'desc')
                ->first();

            $start = $lastClosure ? $lastClosure->created_at : null;
            $end = $batch['tgl_buka_raw'];

            $dataMasuk = $this->getInflowByWindow($closure->id_lahan, $start, $end, $batch['status']);

            $tglInflowPertama = $dataMasuk->min('tanggal');
            $tglBukaFix = $tglInflowPertama ?: $batch['info']['tgl_buka_lahan'];

            $batchInfo = $batch['info'];
            $batchInfo['tgl_buka_lahan'] = $tglBukaFix;

            // --- PERBAIKAN DIVISION BY ZERO & NULL CHECK ---
            $sumPoin = (float) $dataMasuk->sum('poin');
            $sumInM3 = (float) $dataMasuk->sum('kubikasi');
            $sumOutM3 = (float) $batch['grand_total_outflow_m3'];
            $sumOutOngkos = (float) $batch['grand_total_outflow_ongkos_pkj'];
            $sumOutPenyusutan = (float) $batch['grand_total_outflow_penyusutan'];

            // Logika Harga: Jika Outflow M3 adalah 0, maka hasil adalah 0 untuk menghindari Error
            $harga_v_ongkos = $sumOutM3 > 0 ? (($sumPoin + $sumOutOngkos) / $sumOutM3) : 0;
            $harga_v_ongkos_penyusutan = $sumOutM3 > 0 ? (($sumPoin + $sumOutOngkos + $sumOutPenyusutan) / $sumOutM3) : 0;
            $harga_veneer = $sumOutM3 > 0 ? ($sumPoin / $sumOutM3) : 0;

            $laporanFinal[] = [
                'batch_info' => $batchInfo,
                'inflow' => $dataMasuk,
                'outflow' => $batch['outflow_detail'],
                'summary' => [
                    'total_kayu_masuk' => (int) $dataMasuk->sum('banyak'),
                    'total_masuk_m3' => (float) number_format($sumInM3, 4, '.', ''),
                    'total_keluar_m3' => (float) number_format($sumOutM3, 4, '.', ''),
                    'total_poin' => number_format($sumPoin, 2, ',', '.'),
                    'rendemen' => $sumInM3 > 0
                        ? number_format(($sumOutM3 / $sumInM3) * 100, 2) . '%'
                        : '0%',
                    'harga_veneer' => (float) $harga_veneer,
                    'harga_v_ongkos' => (float) $harga_v_ongkos,
                    'harga_vop' => (float) $harga_v_ongkos_penyusutan
                ]
            ];
        }

        return new LengthAwarePaginator(
            $laporanFinal,
            $paginatedClosures->total(),
            $paginatedClosures->perPage(),
            $paginatedClosures->currentPage(),
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function stitchBatchWithOutflow(array $tempGroup): array
    {
        $records = collect($tempGroup);
        $first = $records->first();
        $last = $records->first(fn($i) => $i->jumlah_batang > 0);

        // Perbaikan Null Check pada HargaPegawai
        $ongkosPekerja = HargaPegawai::value('harga') ?? 0;

        $idsPenggunaanLahan = $records->pluck('id')->toArray();

        $outflowData = DetailHasilPaletRotary::with([
            'produksi:id,tgl_produksi,id_mesin',
            'produksi.mesin:id,nama_mesin,penyusutan',
            'produksi.detailPegawaiRotary:id,id_produksi',
            'setoranPaletUkuran:id,panjang,lebar,tebal'
        ])
            ->whereIn('id_penggunaan_lahan', $idsPenggunaanLahan)
            ->get();

        $groupedOutflow = $outflowData->map(function ($hasil) use ($ongkosPekerja) {
            $produksi = $hasil->produksi;
            $ukuran = $hasil->setoranPaletUkuran;
            $totalLembar = (int) ($hasil->total_lembar ?? 0);

            $m3 = $ukuran ? ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $totalLembar) / 10000000 : 0;
            $pekerja = $produksi ? $produksi->detailPegawaiRotary->count() : 0;
            $penyusutan = ($produksi && $produksi->mesin) ? $produksi->mesin->penyusutan : 0;

            return [
                'tgl' => $produksi ? Carbon::parse($produksi->tgl_produksi)->format('d-m-Y') : '-',
                'mesin' => ($produksi && $produksi->mesin) ? $produksi->mesin->nama_mesin : 'Unknown',
                'jam_kerja' => "06:00 - 16:00",
                'ukuran' => $ukuran ? "{$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}" : '-',
                'banyak' => $totalLembar,
                'kubikasi' => $m3,
                'pekerja' => $pekerja . " Orang",
                'ongkos' => $pekerja * $ongkosPekerja,
                'penyusutan' => (float) $penyusutan
            ];
        })->groupBy(fn($item) => $item['tgl'] . $item['mesin'] . $item['ukuran'])
            ->map(fn($group) => [
                'tgl' => $group[0]['tgl'],
                'mesin' => $group[0]['mesin'],
                'jam_kerja' => $group[0]['jam_kerja'],
                'ukuran' => $group[0]['ukuran'],
                'total_banyak' => $group->sum('banyak'),
                'total_kubikasi' => (float) $group->sum('kubikasi'),
                'pekerja' => $group[0]['pekerja'],
                'ongkos' => $group->sum('ongkos'),
                'penyusutan' => $group->sum('penyusutan')
            ])->values();

        return [
            'id_lahan' => $first->id_lahan,
            'tgl_buka_raw' => $first->created_at,
            'status' => $last ? 'SELESAI' : 'PROSES',
            'grand_total_outflow_m3' => (float) $groupedOutflow->sum('total_kubikasi'),
            'grand_total_outflow_ongkos_pkj' => (float) $groupedOutflow->sum('ongkos'),
            'grand_total_outflow_penyusutan' => (float) $groupedOutflow->sum('penyusutan'),
            'outflow_detail' => $groupedOutflow->toArray(),
            'info' => [
                'lahan' => $first->lahan->nama_lahan ?? '-',
                'kode' => $first->lahan->kode_lahan ?? '-',
                'jenis_kayu' => $first->jenisKayu->nama_kayu ?? '-',
                'kode_kayu' => $first->jenisKayu->kode_kayu ?? '-',
                'status' => $last ? 'SELESAI' : 'PROSES',
                'tgl_buka_lahan' => $first->created_at->format('Y-m-d H:i:s'),
                'tgl_tutup_lahan' => $last ? $last->created_at->format('Y-m-d H:i:s') : 'MASIH BERJALAN',
                'jumlah_batang_akhir' => $last ? $last->jumlah_batang : 0,
            ],
        ];
    }

    private function getInflowByWindow($idLahan, $start, $end, $statusBatch)
    {
        $query = NotaKayu::select('id', 'created_at', 'id_kayu_masuk', 'status')
            ->with([
                'kayuMasuk:id,seri',
                'kayuMasuk.detailTurusanKayus' => fn($q) => $q->where('lahan_id', $idLahan)
            ])
            ->where('status', 'like', '%Sudah Diperiksa%')
            ->whereHas('kayuMasuk.detailTurusanKayus', fn($q) => $q->where('lahan_id', $idLahan));

        $batasAtas = ($statusBatch === 'PROSES') ? now() : $end;
        $query->where('created_at', '<=', $batasAtas);
        if ($start) {
            $query->where('created_at', '>', $start);
        }

        return $query->get()->map(function ($nota) use ($idLahan) {
            $items = $nota->kayuMasuk->detailTurusanKayus;
            $kubikasiTotal = (float) $items->sum('kubikasi');
            return [
                'tanggal' => $nota->created_at->format('d-m-Y'),
                'seri' => $nota->kayuMasuk->seri ?? '-',
                'banyak' => $items->sum('kuantitas'),
                'kubikasi' => $kubikasiTotal,
                'poin' => (float) $items->sum(fn($i) => $this->calculatePoin($i))
            ];
        });
    }

    private function calculatePoin($item)
    {
        $harga = $this->getHargaSatuan($item->id_jenis_kayu ?? 1, $item->grade ?? 0, $item->panjang ?? 0, $item->diameter);
        return (float) (($harga ?? 0) * ($item->kubikasi ?? 0) * 1000);
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
