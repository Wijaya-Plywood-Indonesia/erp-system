<?php

namespace App\Filament\Pages;

use App\Models\NotaKayu;
use App\Services\ProduksiInflowService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;

class PersentaseKayu extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Persentase Kayu';

    protected string $view = 'filament.pages.persentase-kayu';

    // public function mount(): void
    // {
    //     $this->loadData();
    // }

    public array $full_data = [];

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return 'Laporan';
    }
    protected function getViewData(): array
    {
        $service = new ProduksiInflowService();
        
        // Data diambil berdasarkan paginasi yang diproses di Service
        $dataLaporan = $service->getLaporanBatch();

        return [
            'laporan' => $dataLaporan,
        ];
    }

    // public function loadData()
    // {
    //     $service = new ProduksiInflowService();
    //     $hasilLaporan = $service->getLaporanBatch();
    //     dd($hasilLaporan);
    //     $this->full_data = $hasilLaporan;

    //     // Simulasi Gathering Data dari berbagai tabel (Log, Produksi, dan Biaya)
    //     $this->full_data = [
    //         [
    //             'id_transaksi'   => 1,
    //             'tgl_masuk'      => '2026-02-18',
    //             'lahan'          => 'Lahan A - Kalimantan',
    //             'total_batang'   => 45,
    //             'kubikasi_kayu'  => 5.250, // Master Log
    //             'poin'           => 12500,
    //             'kubikasi_veneer'=> 4.100, // Hasil Produksi
    //             'harga_v_ongkos' => 15200000,
    //             'harga_total'    => 16800000,
                
    //             // Relasi Dropdown 1: Detail Kayu Masuk (Log Masuk)
    //             'kayu_masuk_detail' => [
    //                 [
    //                     'tgl'      => '2026-02-18',
    //                     'seri'     => 'LOG-001/A',
    //                     'banyak'   => 20,
    //                     'kubikasi' => 2.500,
    //                     'poin'     => 6000
    //                 ],
    //                 [
    //                     'tgl'      => '2026-02-18',
    //                     'seri'     => 'LOG-002/A',
    //                     'banyak'   => 25,
    //                     'kubikasi' => 2.750,
    //                     'poin'     => 6500
    //                 ],
    //             ],
                
    //             // Relasi Dropdown 2: Detail Kayu Keluar (Proses Produksi)
    //             'kayu_keluar_detail' => [
    //                 [
    //                     'tgl'         => '2026-02-19',
    //                     'mesin'       => 'Rotary 01',
    //                     'jam_kerja'   => '08:00 - 16:00',
    //                     'ukuran'      => '1220 x 2440 x 1.5',
    //                     'banyak'      => 150,
    //                     'kubikasi'    => 2.100,
    //                     'pekerja'     => 'Tim Agus',
    //                     'ongkos'      => 450000,
    //                     'penyusutan'  => 75000
    //                 ],
    //                 [
    //                     'tgl'         => '2026-02-19',
    //                     'mesin'       => 'Rotary 02',
    //                     'jam_kerja'   => '08:00 - 16:00',
    //                     'ukuran'      => '1220 x 2440 x 2.0',
    //                     'banyak'      => 120,
    //                     'kubikasi'    => 2.000,
    //                     'pekerja'     => 'Tim Budi',
    //                     'ongkos'      => 400000,
    //                     'penyusutan'  => 75000
    //                 ],
    //             ]
    //         ],
    //         [
    //             'id_transaksi'   => 2,
    //             'tgl_masuk'      => '2026-02-19',
    //             'lahan'          => 'Lahan B - Sumatera',
    //             'total_batang'   => 30,
    //             'kubikasi_kayu'  => 3.000,
    //             'poin'           => 9000,
    //             'kubikasi_veneer'=> 2.450,
    //             'harga_v_ongkos' => 8900000,
    //             'harga_total'    => 9500000,
    //             'kayu_masuk_detail' => [
    //                 [
    //                     'tgl'      => '2026-02-19',
    //                     'seri'     => 'LOG-055/B',
    //                     'banyak'   => 30,
    //                     'kubikasi' => 3.000,
    //                     'poin'     => 9000
    //                 ],
    //             ],
    //             'kayu_keluar_detail' => [
    //                 [
    //                     'tgl'         => '2026-02-19',
    //                     'mesin'       => 'Dryer 01',
    //                     'jam_kerja'   => '16:00 - 00:00',
    //                     'ukuran'      => '1220 x 2440 x 1.5',
    //                     'banyak'      => 180,
    //                     'kubikasi'    => 2.450,
    //                     'pekerja'     => 'Tim Susi',
    //                     'ongkos'      => 500000,
    //                     'penyusutan'  => 120000
    //                 ],
    //             ]
    //         ]
    //     ];
    // }
}
