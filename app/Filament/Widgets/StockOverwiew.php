<?php

namespace App\Filament\Widgets;

use App\Models\HargaKayu;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockOverwiew extends StatsOverviewWidget
{
    // Tambahkan baris ini di dalam class
protected static bool $isDiscovered = false;
    protected ?string $pollingInterval = '5s';

protected function getStats(): array
{

    return [
        // Menghitung total volume atau jumlah batang kayu
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
        Stat::make('Total Batang Kayu', HargaKayu::count())
            ->description('Total stok kayu di gudang')
            ->descriptionIcon('heroicon-m-clipboard-document-check')
            ->color('success'),

        // Menghitung rata-rata harga beli kayu
        Stat::make('Rata-rata Harga Beli', 'Rp ' . number_format(HargaKayu::avg('harga_beli'), 0, ',', '.'))
            ->description('Harga rata-rata per log')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary'),

        // Menghitung kayu dengan Grade 1 (Kualitas Terbaik)
        Stat::make('Stok Grade 1', HargaKayu::where('grade', 1)->count())
            ->description('Kayu kualitas premium')
            ->descriptionIcon('heroicon-m-star')
            ->color('warning'),
    ];
}}