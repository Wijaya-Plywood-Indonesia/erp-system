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
            Tables\Columns\TextColumn::make('tanggal')->label('Tanggal'),

            Tables\Columns\TextColumn::make('lahan')
                ->label('Lahan'),

            Tables\Columns\TextColumn::make('total_batang')
                ->label('Total Batang')
                ->numeric(),

            Tables\Columns\TextColumn::make('kubikasi_kayu')
                ->label('Kubikasi Kayu')
                ->formatStateUsing(fn($state) => number_format($state, 4, ',', '.')),

            Tables\Columns\TextColumn::make('poin')
                ->label('Poin')
                ->money('IDR', locale: 'id'),
        ];
    }
}
