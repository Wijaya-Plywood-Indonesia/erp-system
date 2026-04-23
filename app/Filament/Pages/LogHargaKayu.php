<?php

namespace App\Filament\Pages;

use App\Models\HargaKayuLog;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class LogHargaKayu extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title = 'Log Harga Kayu';
    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.log-harga-kayu';

    public function getLogsProperty(): \Illuminate\Support\Collection
    {
        // Pastikan relasi hargaKayu.jenisKayu sudah ada di model HargaKayuLog
        return HargaKayuLog::with(['hargaKayu.jenisKayu'])
            ->latest() // Mengurutkan dari yang terbaru (berdasarkan id/created_at)
            ->get()
            ->groupBy(fn($record) => $record->created_at->format('Y-m-d'));
    }
}
