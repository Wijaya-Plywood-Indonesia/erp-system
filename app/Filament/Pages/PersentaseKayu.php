<?php

namespace App\Filament\Pages;

use App\Models\NotaKayu;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

    public int $bulan;
    public int $tahun;

    public function mount(): void
    {
        $this->bulan = now()->month;
        $this->tahun = now()->year;
        $this->loadData();
    }

    /** 🔑 WAJIB (FILAMENT V4) */
    public function getTableRecordKey($record): string
    {
        return (string) $record->nota_id;
    }

    protected function getTableQuery(): Builder
    {
        return NotaKayu::query()
            ->join('kayu_masuks', 'kayu_masuks.id', '=', 'nota_kayus.id_kayu_masuk')
            ->join('detail_kayu_masuks', 'detail_kayu_masuks.id_kayu_masuk', '=', 'kayu_masuks.id')
            ->join('lahans', 'lahans.id', '=', 'detail_kayu_masuks.id_lahan')

            ->leftJoin('harga_kayus', function ($join) {
                $join->on('harga_kayus.id_jenis_kayu', '=', 'detail_kayu_masuks.id_jenis_kayu')
                    ->on('harga_kayus.grade', '=', 'detail_kayu_masuks.grade')
                    ->on('harga_kayus.panjang', '=', 'detail_kayu_masuks.panjang')
                    ->whereRaw(
                        'detail_kayu_masuks.diameter 
                         BETWEEN harga_kayus.diameter_terkecil 
                         AND harga_kayus.diameter_terbesar'
                    );
            })

            ->whereMonth('kayu_masuks.tgl_kayu_masuk', $this->bulan)
            ->whereYear('kayu_masuks.tgl_kayu_masuk', $this->tahun)

            ->groupBy(
                'nota_kayus.id',
                'kayu_masuks.tgl_kayu_masuk',
                'lahans.kode_lahan'
            )

            ->selectRaw('
                nota_kayus.id as nota_id,
                DATE(kayu_masuks.tgl_kayu_masuk) as tanggal,
                lahans.kode_lahan as lahan,

                SUM(detail_kayu_masuks.jumlah_batang) as total_batang,

                /* ===============================
                   KUBIKASI KAYU (SAMA DENGAN NOTA)
                   =============================== */
                SUM(
                    ROUND(
                        detail_kayu_masuks.panjang *
                        detail_kayu_masuks.diameter *
                        detail_kayu_masuks.diameter *
                        detail_kayu_masuks.jumlah_batang *
                        0.785 / 1000000,
                        4
                    )
                ) as kubikasi_kayu,

                /* ===============================
                   POIN (SAMA DENGAN NOTA)
                   =============================== */
                SUM(
                    ROUND(
                        detail_kayu_masuks.panjang *
                        detail_kayu_masuks.diameter *
                        detail_kayu_masuks.diameter *
                        detail_kayu_masuks.jumlah_batang *
                        0.785 / 1000000,
                        4
                    ) * harga_kayus.harga_beli * 1000
                ) as poin
            ');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('tanggal')
            ->label('Tanggal Masuk')
            ->alignCenter()
            ,

            Tables\Columns\TextColumn::make('lahan')
                ->label('Lahan')
                ->alignCenter()
                ,

            Tables\Columns\TextColumn::make('total_batang')
                ->label('Total Batang')
                ->numeric()
                ->alignCenter()
                ,

            Tables\Columns\TextColumn::make('kubikasi_kayu')
                ->label('Kubikasi Kayu')
                ->formatStateUsing(fn($state) => number_format($state, 4, ',', '.')),
                
            Tables\Columns\TextColumn::make('poin')
            ->label('Poin')
            ->money('IDR', locale: 'id'),

            Tables\Columns\TextColumn::make('kubikasi_veneer')
                ->label('Kubikasi Veneer')
                ->default(0)
                ->formatStateUsing(fn($state) => number_format($state, 4, ',', '.')),
            
            Tables\Columns\TextColumn::make('pesentase_kayu_jadi_veneer')
            ->label('Pesentase')
            ->default(0)
            ->suffix('%')
            ->alignCenter(),

            Tables\Columns\TextColumn::make('harga_veener')
            ->label('Harga Veneer m3')
            ->default(0)
            ->prefix('Rp')
            ->alignEnd()
            ->formatStateUsing(fn($state) => number_format($state, 3, ',', '.')),

            Tables\Columns\TextColumn::make('harga_veener_tambah_ongkos')
            ->label('Harga Veneer + Ongkos')
            ->default(0)
            ->prefix('Rp')
            ->alignEnd()
            ->formatStateUsing(fn($state) => number_format($state, 3, ',', '.')),
            
            Tables\Columns\TextColumn::make('harga_veener_tambah_ongkos_tambah_penyusutan')
            ->label('Harga Veneer + Ongkos + Penyusutan')
            ->default(0)
            ->prefix('Rp')
            ->alignEnd()
            ->formatStateUsing(fn($state) => number_format($state, 3, ',', '.')),

        ];
    }

    public array $full_data = [];

    public function loadData()
    {
        // Simulasi Gathering Data dari berbagai tabel (Log, Produksi, dan Biaya)
        $this->full_data = [
            [
                'id_transaksi'   => 1,
                'tgl_masuk'      => '2026-02-18',
                'lahan'          => 'Lahan A - Kalimantan',
                'total_batang'   => 45,
                'kubikasi_kayu'  => 5.250, // Master Log
                'poin'           => 12500,
                'kubikasi_veneer'=> 4.100, // Hasil Produksi
                'harga_v_ongkos' => 15200000,
                'harga_total'    => 16800000,
                
                // Relasi Dropdown 1: Detail Kayu Masuk (Log Masuk)
                'kayu_masuk_detail' => [
                    [
                        'tgl'      => '2026-02-18',
                        'seri'     => 'LOG-001/A',
                        'banyak'   => 20,
                        'kubikasi' => 2.500,
                        'poin'     => 6000
                    ],
                    [
                        'tgl'      => '2026-02-18',
                        'seri'     => 'LOG-002/A',
                        'banyak'   => 25,
                        'kubikasi' => 2.750,
                        'poin'     => 6500
                    ],
                ],
                
                // Relasi Dropdown 2: Detail Kayu Keluar (Proses Produksi)
                'kayu_keluar_detail' => [
                    [
                        'tgl'         => '2026-02-19',
                        'mesin'       => 'Rotary 01',
                        'jam_kerja'   => '08:00 - 16:00',
                        'ukuran'      => '1220 x 2440 x 1.5',
                        'banyak'      => 150,
                        'kubikasi'    => 2.100,
                        'pekerja'     => 'Tim Agus',
                        'ongkos'      => 450000,
                        'penyusutan'  => 75000
                    ],
                    [
                        'tgl'         => '2026-02-19',
                        'mesin'       => 'Rotary 02',
                        'jam_kerja'   => '08:00 - 16:00',
                        'ukuran'      => '1220 x 2440 x 2.0',
                        'banyak'      => 120,
                        'kubikasi'    => 2.000,
                        'pekerja'     => 'Tim Budi',
                        'ongkos'      => 400000,
                        'penyusutan'  => 75000
                    ],
                ]
            ],
            [
                'id_transaksi'   => 2,
                'tgl_masuk'      => '2026-02-19',
                'lahan'          => 'Lahan B - Sumatera',
                'total_batang'   => 30,
                'kubikasi_kayu'  => 3.000,
                'poin'           => 9000,
                'kubikasi_veneer'=> 2.450,
                'harga_v_ongkos' => 8900000,
                'harga_total'    => 9500000,
                'kayu_masuk_detail' => [
                    [
                        'tgl'      => '2026-02-19',
                        'seri'     => 'LOG-055/B',
                        'banyak'   => 30,
                        'kubikasi' => 3.000,
                        'poin'     => 9000
                    ],
                ],
                'kayu_keluar_detail' => [
                    [
                        'tgl'         => '2026-02-19',
                        'mesin'       => 'Dryer 01',
                        'jam_kerja'   => '16:00 - 00:00',
                        'ukuran'      => '1220 x 2440 x 1.5',
                        'banyak'      => 180,
                        'kubikasi'    => 2.450,
                        'pekerja'     => 'Tim Susi',
                        'ongkos'      => 500000,
                        'penyusutan'  => 120000
                    ],
                ]
            ]
        ];
    }
}
