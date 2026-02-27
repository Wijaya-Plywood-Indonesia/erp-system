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
        // SOLUSI 1 & 3: Ambil hanya baris penutup batch (jumlah_batang > 0) dengan Pagination
        // Batasi kolom yang diambil untuk menghemat memori
        $paginatedClosures = PenggunaanLahanRotary::with([
            'lahan:id,nama_lahan,kode_lahan',
            'jenisKayu:id,nama_kayu'
        ])
            ->where('jumlah_batang', '>', 0)
            ->orderBy('created_at', 'desc')
            ->paginate(10); // Menghasilkan link pagination otomatis

        $laporanFinal = [];

        foreach ($paginatedClosures as $closure) {
            // Untuk setiap penutup, kita cari baris-baris "jahitannya" ke belakang
            // Cari baris yang id_lahan & id_jenis_kayu sama, dan waktu <= penutup saat ini
            // namun > penutup sebelumnya (atau ambil semua yang belum punya penutup lain)

            $batchRecords = PenggunaanLahanRotary::where('id_lahan', $closure->id_lahan)
                ->where('id_jenis_kayu', $closure->id_jenis_kayu)
                ->where('created_at', '<=', $closure->created_at)
                ->orderBy('created_at', 'desc')
                ->get();

            // Kita potong (slice) hanya sampai penutup sebelumnya jika ada
            $tempGroup = [];
            foreach ($batchRecords as $record) {
                $tempGroup[] = $record;
                // Jika ketemu baris lain yang punya jumlah_batang > 0 (tapi bukan baris closure itu sendiri)
                if ($record->id !== $closure->id && $record->jumlah_batang > 0) {
                    array_pop($tempGroup); // Buang baris penutup batch lama itu
                    break;
                }
            }

            // Urutkan balik ke ASC untuk proses jahitan
            $tempGroup = array_reverse($tempGroup);
            $batch = $this->stitchBatchWithOutflow($tempGroup);

            // Tentukan Inflow Window
            // Cari penutup terakhir sebelum batch ini untuk lahan yang sama
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
            $total_poin = number_format($dataMasuk->sum('poin'), 2, ',', '.');
            $harga_v_ongkos = (($dataMasuk->sum('poin') + $batch['grand_total_outflow_ongkos_pkj']) / $batch['grand_total_outflow_m3'] ?? 1);
            $harga_v_ongkos_penyusutan = (($dataMasuk->sum('poin') + $batch['grand_total_outflow_ongkos_pkj'] + $batch['grand_total_outflow_penyusutan']) / $batch['grand_total_outflow_m3'] ?? 1);

            $outflowCollection = collect($batch['outflow_detail']);
            $jenis_kayu = $outflowCollection->contains(function ($item) {
                $namaMesin = strtoupper($item['mesin'] ?? '');
                return str_contains($namaMesin, 'SPINDLESS') || str_contains($namaMesin, 'MERANTI');
            });

            $laporanFinal[] = [
                'batch_info' => $batchInfo,
                'inflow' => $dataMasuk,
                'outflow' => $batch['outflow_detail'],
                'summary' => [
                    'jenis_kayu' => $jenis_kayu ? "KAYU 260" : "KAYU 130",
                    'total_kayu_masuk' => (int) $dataMasuk->sum('banyak'),
                    'total_masuk_m3' => (float) number_format($dataMasuk->sum('kubikasi'), 4),
                    'total_keluar_m3' => (float) number_format($batch['grand_total_outflow_m3'], 4),
                    'total_poin' => $total_poin,
                    'rendemen' => $dataMasuk->sum('kubikasi') > 0
                        ? number_format(($batch['grand_total_outflow_m3'] / $dataMasuk->sum('kubikasi')) * 100, 2) . '%'
                        : '0%',
                    'harga_veneer' => (float) ($dataMasuk->sum('poin') / $batch['grand_total_outflow_m3'] ?? 1),
                    'harga_v_ongkos' => $harga_v_ongkos,
                    'harga_vop' => $harga_v_ongkos_penyusutan
                ]
            ];
        }

        // Kembalikan objek paginator agar view bisa merender links()
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

        $ongkosPekerja = HargaPegawai::first()
            ->value('harga') ?? 0;

        $idsPenggunaanLahan = $records->pluck('id')->toArray();


        // SOLUSI 3: Eager Loading dengan pembatasan kolom pada relasi
        $outflowData = DetailHasilPaletRotary::with([
            'produksi:id,tgl_produksi,id_mesin',
            'produksi.mesin:id,nama_mesin,penyusutan',
            'produksi.detailPegawaiRotary:id,id_produksi',
            'setoranPaletUkuran:id,panjang,lebar,tebal'
        ])
            ->whereIn('id_penggunaan_lahan', $idsPenggunaanLahan)
            ->get();

        $produksiIds = $outflowData->pluck('id_produksi')->unique()->toArray();

        $totalOutputHarian = DetailHasilPaletRotary::whereIn('id_produksi', $produksiIds)
            ->with('setoranPaletUkuran')
            ->get()
            ->groupBy('id_produksi')
            ->map(function ($details) {
                return $details->sum(function ($d) {
                    $u = $d->setoranPaletUkuran;
                    return $u ? ($u->panjang * $u->lebar * $u->tebal * $d->total_lembar) / 10_000_000 : 0;
                });
            });

        $groupedOutflow = $outflowData->map(function ($hasil) use ($ongkosPekerja, $totalOutputHarian) {
            $produksi = $hasil->produksi;
            $ukuran = $hasil->setoranPaletUkuran;
            $totalLembar = (int) ($hasil->total_lembar ?? 0);

            // Perbaikan pembagi kubikasi agar akurat (10^9 untuk mm ke m3)
            $m3 = $ukuran ? ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $totalLembar) / 10_000_000 : 0;
            $m3TotalAllLahan = $totalOutputHarian[$hasil->id_produksi];
            $pekerja = ($produksi->detailPegawaiRotary->count() ?? 0);

            $calculatePekerja = $pekerja * ($m3 / $m3TotalAllLahan);
            $calculatePekerja = round($calculatePekerja);
            $penyusutan = $produksi->mesin->penyusutan ?? 0;

            return [
                'tgl' => Carbon::parse($produksi->tgl_produksi)->format('d-m-Y'),
                'mesin' => $produksi->mesin->nama_mesin ?? 'Unknown',
                'jam_kerja' => "06:00 - 16:00",
                'ukuran' => $ukuran ? "{$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}" : '-',
                'banyak' => $totalLembar,
                'kubikasi' => $m3,
                'pekerja' => (string) $calculatePekerja . " Orang",
                'ongkos' => $pekerja * $ongkosPekerja,
                'penyusutan' => $penyusutan
            ];
        })->groupBy(fn($item) => $item['tgl'] . $item['mesin'] . $item['ukuran'])
            ->map(fn($group) => [
                'tgl' => $group[0]['tgl'],
                'mesin' => $group[0]['mesin'],
                'jam_kerja' => $group[0]['jam_kerja'],
                'ukuran' => $group[0]['ukuran'],
                'total_banyak' => $group->sum('banyak'),
                'total_kubikasi' => number_format($group->sum('kubikasi'), 4),
                'pekerja' => $group[0]['pekerja'],
                'ongkos' => $group[0]['ongkos'],
                'penyusutan' => $group[0]['penyusutan']
            ])->values()->toArray();

        return [
            'id_lahan' => $first->id_lahan,
            'tgl_buka_raw' => $first->created_at,
            'status' => $last ? 'SELESAI' : 'PROSES',
            'grand_total_outflow_m3' => collect($groupedOutflow)->sum('total_kubikasi'),
            'grand_total_outflow_ongkos_pkj' => collect($groupedOutflow)->sum('ongkos'),
            'grand_total_outflow_penyusutan' => collect($groupedOutflow)->sum('penyusutan'),
            'outflow_detail' => $groupedOutflow,
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
        // SOLUSI 3: Batasi kolom pada Inflow
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
            return [
                'tanggal' => $nota->created_at->format('d-m-Y'),
                'seri' => $nota->kayuMasuk->seri ?? '-',
                'banyak' => $items->sum('kuantitas'),
                'kubikasi' => (float) $items->sum('kubikasi'),
                'poin' => (int) $items->sum(fn($i) => $this->calculatePoin($i))
            ];
        });
    }

    private function calculatePoin($item)
    {
        $harga = $this->getHargaSatuan($item->id_jenis_kayu ?? 1, $item->grade ?? 0, $item->panjang ?? 0, $item->diameter);
        return (float) (($harga ?? 0) * $item->kubikasi * 1000);
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