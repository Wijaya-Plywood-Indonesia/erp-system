<?php

namespace App\Filament\Resources\NotaKayus\Schemas;

use App\Models\DetailKayuMasuk;
use App\Models\DetailTurunKayu;
use App\Models\DetailTurusanKayu;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class NotaKayuInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1) // <- PENTING: sekarang semua section berada di 1 kolom besar
            ->components([
                // Alert Callout jika data fisik belum cocok
                Callout::make('status_kesesuaian')
                    ->visible(function ($record) {
                        if (! $record->kayuMasuk) {
                            return true;
                        }

                        $total1 = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $total2 = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $totalTurun = (int) DetailTurunKayu::where('id_kayu_masuk', $record->kayuMasuk->id)->sum('jumlah_kayu');

                        $batangSama = ($total1['total_batang'] == $total2['total_batang']) && ($total1['total_batang'] == $totalTurun);
                        $kubikasiSama = abs($total1['total_kubikasi'] - $total2['total_kubikasi']) < 0.0001;

                        return ! ($batangSama && $kubikasiSama);
                    })
                    ->danger()
                    ->heading('Perhatian: Data Fisik Kayu Belum Cocok / Ada Selisih!')
                    ->description(function ($record) {
                        if (! $record->kayuMasuk) {
                            return 'Data Kayu Masuk tidak ditemukan.';
                        }

                        $total1 = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $total2 = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $totalTurun = (int) DetailTurunKayu::where('id_kayu_masuk', $record->kayuMasuk->id)->sum('jumlah_kayu');

                        $pesan = [];
                        if ($total2['total_batang'] != $totalTurun || $total1['total_batang'] != $totalTurun) {
                            $pesan[] = "Jumlah batang tidak sama: Turun Kayu ({$totalTurun} btg) vs Turusan 1 ({$total2['total_batang']} btg) vs Turusan 2 ({$total1['total_batang']} btg).";
                        }
                        if (abs($total1['total_kubikasi'] - $total2['total_kubikasi']) >= 0.0001) {
                            $k1 = number_format($total2['total_kubikasi'], 4);
                            $k2 = number_format($total1['total_kubikasi'], 4);
                            $pesan[] = "Total kubikasi Turusan 1 ({$k1} m³) berbeda dengan Turusan 2 ({$k2} m³).";
                        }

                        return implode(' ', $pesan) ?: 'Terdapat ketidakcocokan antara Turun Kayu, Turusan 1, atau Turusan 2.';
                    }),

                // Card Rekap Perbandingan Turun Kayu, Turusan 1, dan Turusan 2
                Section::make('Verifikasi Fisik & Turusan Kayu')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('rekap_turun_kayu')
                                    ->label('1. Turun Kayu')
                                    ->getStateUsing(function ($record) {
                                        if (! $record->kayuMasuk) {
                                            return '0 Batang';
                                        }
                                        $btg = (int) DetailTurunKayu::where('id_kayu_masuk', $record->kayuMasuk->id)->sum('jumlah_kayu');
                                        return number_format($btg) . ' Batang';
                                    })
                                    ->badge()
                                    ->color(function ($record) {
                                        if (! $record->kayuMasuk) {
                                            return 'danger';
                                        }
                                        $total1 = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                                        $total2 = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                                        $btgTurun = (int) DetailTurunKayu::where('id_kayu_masuk', $record->kayuMasuk->id)->sum('jumlah_kayu');

                                        return ($btgTurun > 0 && $btgTurun == $total2['total_batang'] && $btgTurun == $total1['total_batang'])
                                            ? 'success'
                                            : 'danger';
                                    }),

                                TextEntry::make('rekap_turusan_1')
                                    ->label('2. Turusan 1 (Detail Kayu Masuk)')
                                    ->getStateUsing(function ($record) {
                                        if (! $record->kayuMasuk) {
                                            return "0 Batang\n0.0000 m³";
                                        }
                                        $total = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                                        $btg = number_format($total['total_batang']);
                                        $kub = number_format($total['total_kubikasi'], 4);
                                        return "{$btg} Batang\n{$kub} m³";
                                    })
                                    ->badge()
                                    ->formatStateUsing(fn(string $state) => str_replace("\n", '<br>', e($state)))
                                    ->html()
                                    ->color(function ($record) {
                                        if (! $record->kayuMasuk) {
                                            return 'danger';
                                        }
                                        $total1 = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                                        $total2 = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                                        $btgTurun = (int) DetailTurunKayu::where('id_kayu_masuk', $record->kayuMasuk->id)->sum('jumlah_kayu');

                                        $sama = ($total2['total_batang'] == $btgTurun)
                                            && ($total2['total_batang'] == $total1['total_batang'])
                                            && (abs($total2['total_kubikasi'] - $total1['total_kubikasi']) < 0.0001);

                                        return $sama ? 'success' : 'danger';
                                    }),

                                TextEntry::make('rekap_turusan_2')
                                    ->label('3. Turusan 2 (Detail Turusan Kayu)')
                                    ->getStateUsing(function ($record) {
                                        if (! $record->kayuMasuk) {
                                            return "0 Batang\n0.0000 m³";
                                        }
                                        $total = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                                        $btg = number_format($total['total_batang']);
                                        $kub = number_format($total['total_kubikasi'], 4);
                                        return "{$btg} Batang\n{$kub} m³";
                                    })
                                    ->badge()
                                    ->formatStateUsing(fn(string $state) => str_replace("\n", '<br>', e($state)))
                                    ->html()
                                    ->color(function ($record) {
                                        if (! $record->kayuMasuk) {
                                            return 'danger';
                                        }
                                        $total1 = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                                        $total2 = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                                        $btgTurun = (int) DetailTurunKayu::where('id_kayu_masuk', $record->kayuMasuk->id)->sum('jumlah_kayu');

                                        $sama = ($total1['total_batang'] == $btgTurun)
                                            && ($total1['total_batang'] == $total2['total_batang'])
                                            && (abs($total1['total_kubikasi'] - $total2['total_kubikasi']) < 0.0001);

                                        return $sama ? 'success' : 'danger';
                                    }),
                            ]),
                    ]),

                Section::make('Informasi Nota')
                    ->schema([

                        // --- Sub Section: Kayu ---
                        Section::make('Informasi Kayu')
                            ->schema([
                                TextEntry::make('kayu_seri')
                                    ->label('Seri Kayu')
                                    ->getStateUsing(
                                        fn($record) => $record->kayuMasuk->seri ?? '-'
                                    ),

                            ])
                            ->columns(1),

                        // --- Sub Section: Supplier ---
                        Section::make('Informasi Supplier')
                            ->schema([
                                TextEntry::make('supplier')
                                    ->label('Supplier')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->kayuMasuk->penggunaanSupplier->nama_supplier
                                        ?? '-'
                                    ),

                                TextEntry::make('no_telepon')
                                    ->label('Telepon Supplier')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->kayuMasuk->penggunaanSupplier->no_telepon
                                        ?? '-'
                                    ),
                            ])
                            ->columns(2),

                        // --- Sub Section: Kendaraan ---
                        Section::make('Informasi Kendaraan')
                            ->schema([
                                TextEntry::make('jenis_kendaraan')
                                    ->label('Jenis Kendaraan')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->kayuMasuk->penggunaanKendaraanSupplier->jenis_kendaraan
                                        ?? '-'
                                    ),

                                TextEntry::make('nopol')
                                    ->label('Nomor Polisi')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->kayuMasuk->penggunaanKendaraanSupplier->nopol_kendaraan
                                        ?? '-'
                                    ),

                                TextEntry::make('pemilik_kendaraan')
                                    ->label('Pemilik Kendaraan')
                                    ->getStateUsing(
                                        fn($record) =>
                                        $record->kayuMasuk->penggunaanKendaraanSupplier->pemilik_kendaraan
                                        ?? '-'
                                    ),
                            ])
                            ->columns(3),

                        // --- Sub Section: Nota ---
                        Section::make('Detail Nota')
                            ->schema([
                                TextEntry::make('no_nota')
                                    ->label('Nomor Nota'),
                            ])
                            ->columns(columns: 1),
                    ])
                    ->columns(1),

                Section::make('Stakeholder')
                    ->schema([
                        TextEntry::make('penanggung_jawab')
                            ->label('Penanggung Jawab'),

                        TextEntry::make('penerima')
                            ->label('Penerima'),

                        TextEntry::make('satpam')
                            ->label('Satpam'),
                    ])
                    ->columns(3),
            ]);

    }
}
