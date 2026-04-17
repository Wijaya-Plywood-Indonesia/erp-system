<?php

namespace App\Filament\Pages;

use App\Models\HargaKayu;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class LogHargaKayu extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';
    protected static string|UnitEnum|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Log Harga Kayu';
    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.log-harga-kayu';

    public function getLogsProperty(): Collection
    {
        // Eloquent Collection secara otomatis kompatibel dengan Illuminate\Support\Collection
        return HargaKayu::with(['jenisKayu', 'updater', 'approver'])
            ->whereNotNull('updated_by') // Hanya ambil data yang pernah diinteraksi petugas
            ->latest('updated_at')      // Urutkan dari yang terbaru
            ->get()
            ->groupBy(fn($record) => $record->updated_at->format('Y-m-d'));
    }
}
