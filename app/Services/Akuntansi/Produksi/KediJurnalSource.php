<?php

namespace App\Services\Akuntansi\Produksi;

use App\Exports\Sheets\JurnalKediSheetV2;
use App\Models\ProduksiKedi;
use Carbon\Carbon;

/**
 * Jurnal produksi Kedi.
 *
 * Memakai JurnalKediSheetV2 yang sama dengan tombol "Export ke Excel" di
 * Laporan Kedi, sehingga akun, harga, dan angkanya identik dengan sheet
 * "jurnal produksi v2".
 *
 * Satu jurnal per tanggal (semua sesi Kedi hari itu digabung, sama seperti
 * sheet Excel-nya): KEDI/{Ymd}.
 *
 * CATATAN: pembangunan data di muatDataKedi() meniru
 * App\Filament\Pages\LaporanKedi::loadAllData() (query + pengelompokan
 * detail), hanya bagian yang dibaca JurnalKediSheetV2. Kalau logika itu di
 * halaman Laporan Kedi diubah, ubah di sini juga.
 */
class KediJurnalSource implements JurnalProduksiSource
{
    public function key(): string
    {
        return 'kedi';
    }

    public function label(): string
    {
        return 'Produksi Kedi';
    }

    public function build(string $tanggal): array
    {
        $tgl = Carbon::parse($tanggal)->format('Y-m-d');

        $dataKedi = $this->muatDataKedi($tgl);

        if (empty($dataKedi)) {
            return [];
        }

        $sheetRows = (new JurnalKediSheetV2($dataKedi))->array();
        $noDokumen = 'KEDI/'.str_replace('-', '', $tgl);

        return JurnalSheetRows::toJurnals($sheetRows, $tgl, fn () => $noDokumen);
    }

    /**
     * @return array<int, array>  struktur sama dengan LaporanKedi::$dataKedi
     */
    private function muatDataKedi(string $tgl): array
    {
        $produksiList = ProduksiKedi::with([
            'mesin',
            'detailMasukKedi.ukuran',
            'detailMasukKedi.jenisKayu',
            'detailBongkarKedi.ukuran',
            'detailBongkarKedi.jenisKayu',
            'detailPegawaiKedi.pegawai',
        ])
            ->where(function ($q) use ($tgl) {
                $q->whereDate('tanggal_actual_bongkar', $tgl)
                    ->orWhere(function ($sub) use ($tgl) {
                        $sub->whereNull('tanggal_actual_bongkar')
                            ->whereDate('tanggal_bongkar', $tgl);
                    });
            })
            ->orderByRaw('COALESCE(tanggal_actual_bongkar, tanggal_bongkar)')
            ->get();

        $data = [];

        foreach ($produksiList as $produksi) {
            $detailMasuk = $produksi->detailMasukKedi
                ->groupBy(fn ($d) => $d->id_ukuran.'-'.$d->id_jenis_kayu.'-'.$d->kw)
                ->map(fn ($group) => [
                    'no_palet' => $group->pluck('no_palet')->unique()->implode(', '),
                    'mesin' => $produksi->mesin?->nama_mesin ?? '-',
                    'ukuran' => $group->first()->ukuran?->dimensi ?? '-',
                    'jenis_kayu' => $group->first()->jenisKayu?->nama_kayu ?? '-',
                    'kw' => $group->first()->kw,
                    'jumlah' => $group->sum('jumlah'),
                    'rencana_bongkar' => $produksi->rencana_bongkar
                        ? Carbon::parse($produksi->rencana_bongkar)->format('d/m/Y')
                        : '-',
                ])->values()->toArray();

            $detailBongkar = $produksi->detailBongkarKedi
                ->groupBy(fn ($d) => $d->id_ukuran.'-'.$d->id_jenis_kayu.'-'.$d->kw)
                ->map(fn ($group) => [
                    'no_palet' => $group->pluck('no_palet')->unique()->implode(', '),
                    'mesin' => $produksi->mesin?->nama_mesin ?? '-',
                    'ukuran' => $group->first()->ukuran?->dimensi ?? '-',
                    'jenis_kayu' => $group->first()->jenisKayu?->nama_kayu ?? '-',
                    'kw' => $group->first()->kw,
                    'jumlah' => $group->sum('jumlah'),
                ])->values()->toArray();

            $tglKeluar = $produksi->tanggal_actual_bongkar ?? $produksi->tanggal_bongkar;

            $data[] = [
                'id' => $produksi->id,
                'tanggal_masuk' => $produksi->tanggal ? Carbon::parse($produksi->tanggal)->format('d/m/Y') : '-',
                'tanggal_keluar' => $tglKeluar ? Carbon::parse($tglKeluar)->format('d/m/Y') : '-',
                'tanggal_actual_bongkar' => $tglKeluar ? Carbon::parse($tglKeluar)->format('d/m/Y') : null,
                'status' => $produksi->status,
                'detail_masuk' => $detailMasuk,
                'detail_bongkar' => $detailBongkar,
                'total_pekerja' => $produksi->detailPegawaiKedi->count(),
            ];
        }

        return $data;
    }
}