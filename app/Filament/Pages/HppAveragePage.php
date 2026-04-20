<?php

namespace App\Filament\Pages;

use App\Models\HppAverageLog;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class HppAveragePage extends Page
{
    protected string $view = 'filament.pages.hpp-average-page';
    protected static ?string $navigationLabel = 'Log HPP Kayu';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title = 'Log HPP Kayu (Manual)';
    protected static ?int $navigationSort = 10;

    // ── State ──────────────────────────────────────────────────
    public string $filterPanjang = '';
    public string $filterJenisKayu = '';
    public string $filterLahan = '';

    // Role untuk yang bisa melihat log HPP
    private const ROLE_ALLOWED = ['super_admin', 'admin', 'finance'];

    /**
     * Tentukan apakah halaman ini visible atau tidak
     */
    public static function canAccess(): bool
    {
        // Sembunyikan halaman Log HPP dari user biasa
        // Hanya role tertentu yang bisa melihat
        return Auth::user()?->hasAnyRole(self::ROLE_ALLOWED) ?? false;
    }

    /**
     * Tampilkan peringatan bahwa log HPP hanya untuk transaksi manual
     */
    public function mount(): void
    {
        // Tampilkan notifikasi info bahwa log HPP tidak otomatis dari nota
        Notification::make()
            ->info()
            ->title('Informasi Log HPP')
            ->body('Log HPP hanya mencatat transaksi MANUAL (bukan dari nota kayu otomatis). Stok dari nota kayu langsung masuk ke summary tanpa dicatat di log ini.')
            ->duration(5000)
            ->send();
    }

    // ── Computed: log transaksi ascending (buku besar) ─────────
    public function getLogsProperty()
    {
        return HppAverageLog::with(['jenisKayu', 'lahan'])
            ->whereNull('grade')
            ->when($this->filterLahan, fn($q) => $q->where('id_lahan', $this->filterLahan))
            ->when($this->filterPanjang, fn($q) => $q->where('panjang', $this->filterPanjang))
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
    }

    // ── Tambahan info statistik ─────────────────────────────────
    public function getStatistikProperty(): array
    {
        $logs = $this->logs;

        return [
            'total_transaksi' => $logs->count(),
            'total_masuk' => $logs->where('tipe_transaksi', 'masuk')->count(),
            'total_keluar' => $logs->where('tipe_transaksi', 'keluar')->count(),
            'total_nilai_masuk' => $logs->where('tipe_transaksi', 'masuk')->sum('nilai_stok'),
            'total_nilai_keluar' => $logs->where('tipe_transaksi', 'keluar')->sum('nilai_stok'),
        ];
    }
}
