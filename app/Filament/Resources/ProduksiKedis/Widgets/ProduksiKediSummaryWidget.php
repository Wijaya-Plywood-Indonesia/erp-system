<?php

namespace App\Filament\Resources\ProduksiKedis\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use App\Models\ProduksiKedi;
use App\Models\DetailBongkarKedi;
use App\Models\DetailMasukKedi; // Tambahkan import model Masuk
use App\Models\DetailPegawaiKedi;

class ProduksiKediSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-kedis.widgets.summary';

    protected int|string|array $columnSpan = 'full';

    public ?ProduksiKedi $record = null;

    public array $summary = [];

    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            // Mendengarkan sinyal update produksi kedi secara real-time
            "echo:production.kedi.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?ProduksiKedi $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        // 1. STATISTIK GLOBAL (TOTAL LEMBAR)
        $totalMasuk = DetailMasukKedi::where('id_produksi_kedi', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        $totalBongkar = DetailBongkarKedi::where('id_produksi_kedi', $produksiId)
            ->sum(DB::raw('CAST(jumlah AS UNSIGNED)'));

        $totalPegawai = DetailPegawaiKedi::where('id_produksi_kedi', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        // 2. QUERY DASAR UNTUK DIMENSI UKURAN (Agar format string seragam)
        $selectUkuranRaw = '
            CONCAT(
                TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
            ) AS ukuran
        ';

        // 3. LOGIKA RINGKASAN MASUK (DETAIL PER UKURAN)
        $summaryMasuk = DetailMasukKedi::query()
            ->where('id_produksi_kedi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_masuk_kedi.id_ukuran')
            ->selectRaw($selectUkuranRaw)
            ->selectRaw('SUM(CAST(detail_masuk_kedi.jumlah AS UNSIGNED)) AS total')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        // 4. LOGIKA RINGKASAN BONGKAR (DETAIL PER UKURAN)
        $summaryBongkar = DetailBongkarKedi::query()
            ->where('id_produksi_kedi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_bongkar_kedi.id_ukuran')
            ->selectRaw($selectUkuranRaw)
            ->selectRaw('SUM(CAST(detail_bongkar_kedi.jumlah AS UNSIGNED)) AS total')
            ->groupBy('ukuran')
            ->orderBy('ukuran')
            ->get();

        // 5. BREAKDOWN MASUK PER KW (Jika ingin ditampilkan mendetail)
        $masukByKw = DetailMasukKedi::query()
            ->where('id_produksi_kedi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_masuk_kedi.id_ukuran')
            ->selectRaw($selectUkuranRaw)
            ->selectRaw('detail_masuk_kedi.kw, SUM(CAST(detail_masuk_kedi.jumlah AS UNSIGNED)) AS total')
            ->groupBy('ukuran', 'detail_masuk_kedi.kw')
            ->get();

        // 6. GLOBAL JENIS KAYU & UKURAN MASUK
        $globalJenisKayuUkuranMasuk = DetailMasukKedi::query()
            ->where('id_produksi_kedi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_masuk_kedi.id_ukuran')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'detail_masuk_kedi.id_jenis_kayu')
            ->selectRaw('
                jenis_kayus.nama_kayu as jenis_kayu,
                ' . $selectUkuranRaw . ',
                detail_masuk_kedi.kw as kw,
                SUM(CAST(detail_masuk_kedi.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('jenis_kayus.nama_kayu', 'ukuran', 'detail_masuk_kedi.kw')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->get();

        // 7. GLOBAL JENIS KAYU & UKURAN BONGKAR
        $globalJenisKayuUkuranBongkar = DetailBongkarKedi::query()
            ->where('id_produksi_kedi', $produksiId)
            ->join('ukurans', 'ukurans.id', '=', 'detail_bongkar_kedi.id_ukuran')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'detail_bongkar_kedi.id_jenis_kayu')
            ->selectRaw('
                jenis_kayus.nama_kayu as jenis_kayu,
                ' . $selectUkuranRaw . ',
                detail_bongkar_kedi.kw as kw,
                SUM(CAST(detail_bongkar_kedi.jumlah AS UNSIGNED)) AS total
            ')
            ->groupBy('jenis_kayus.nama_kayu', 'ukuran', 'detail_bongkar_kedi.kw')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'totalMasuk'     => $totalMasuk,
            'totalBongkar'   => $totalBongkar,
            'totalPegawai'   => $totalPegawai,
            'summaryMasuk'   => $summaryMasuk,
            'summaryBongkar' => $summaryBongkar,
            'masukByKw'      => $masukByKw,
            'globalJenisKayuUkuranMasuk' => $globalJenisKayuUkuranMasuk,
            'globalJenisKayuUkuranBongkar' => $globalJenisKayuUkuranBongkar,
            'selisih'        => $totalMasuk - $totalBongkar, // Kayu yang masih di dalam kedi/proses
        ];
    }
}
