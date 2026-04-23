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
    protected static ?string $title = 'Log Histori HPP Kayu';
    protected static ?int $navigationSort = 10;

    // ── State ──────────────────────────────────────────────────
    public string $filterPanjang = '';
    public string $filterJenisKayu = '';
    public string $filterLahan = '';
    public string $filterTipeTransaksi = ''; // ✅ Tambahan filter tipe transaksi

    // Role untuk yang bisa melihat log HPP
    private const ROLE_ALLOWED = ['super_admin', 'admin', 'finance', 'manager'];

    /**
     * Tentukan apakah halaman ini visible atau tidak
     */
    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole(self::ROLE_ALLOWED) ?? false;
    }

    /**
     * Tampilkan informasi tentang log HPP
     */
    public function mount(): void
    {
        // ✅ Update notifikasi karena sekarang log HPP mencatat SEMUA transaksi
        Notification::make()
            ->info()
            ->title('📊 Informasi Log HPP Kayu')
            ->body('Log siap Digunakan')
            ->duration(8000)
            ->send();
    }

    // ── Computed: log transaksi (buku besar) ─────────
    public function getLogsProperty()
    {
        $query = HppAverageLog::with(['jenisKayu', 'lahan'])
            ->whereNull('grade');

        // Filter lahan
        if ($this->filterLahan) {
            $query->where('id_lahan', $this->filterLahan);
        }

        // Filter panjang
        if ($this->filterPanjang) {
            $query->where('panjang', $this->filterPanjang);
        }

        // Filter jenis kayu
        if ($this->filterJenisKayu) {
            $query->where('id_jenis_kayu', $this->filterJenisKayu);
        }

        // ✅ Filter tipe transaksi (baru)
        if ($this->filterTipeTransaksi) {
            $query->where('tipe_transaksi', $this->filterTipeTransaksi);
        }

        return $query->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
    }

    // ── Statistik lengkap ─────────────────────────────────
    public function getStatistikProperty(): array
    {
        $logs = $this->logs;

        // Hitung total berdasarkan tipe
        $totalMasuk = $logs->where('tipe_transaksi', 'masuk');
        $totalKeluar = $logs->where('tipe_transaksi', 'keluar');

        // ✅ Pisahkan berdasarkan sumber transaksi
        $dariNota = $logs->where('referensi_type', 'App\\Models\\NotaKayu');
        $dariManual = $logs->where('referensi_type', '!=', 'App\\Models\\NotaKayu')->where('referensi_type', '!=', null);
        $tanpaReferensi = $logs->whereNull('referensi_type');

        return [
            'total_transaksi' => $logs->count(),
            'total_masuk' => $totalMasuk->count(),
            'total_keluar' => $totalKeluar->count(),

            'total_nilai_masuk' => $totalMasuk->sum('nilai_stok'),
            'total_nilai_keluar' => $totalKeluar->sum('nilai_stok'),
            'saldo_akhir' => $totalMasuk->sum('nilai_stok') - $totalKeluar->sum('nilai_stok'),

            // ✅ Statistik sumber transaksi
            'sumber_transaksi' => [
                'dari_nota' => $dariNota->count(),
                'dari_manual' => $dariManual->count(),
                'tanpa_referensi' => $tanpaReferensi->count(),
            ],

            // ✅ Statistik per bulan
            'per_bulan' => $logs->groupBy(function ($log) {
                return $log->tanggal->format('Y-m');
            })->map(function ($group) {
                return [
                    'jumlah' => $group->count(),
                    'nilai_masuk' => $group->where('tipe_transaksi', 'masuk')->sum('nilai_stok'),
                    'nilai_keluar' => $group->where('tipe_transaksi', 'keluar')->sum('nilai_stok'),
                ];
            }),
        ];
    }
}
