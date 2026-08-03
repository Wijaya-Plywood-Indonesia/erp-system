<?php

namespace App\Filament\Pages;

use App\Models\LogLogCore;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class LogLogCorePage extends Page
{
    use HasPageShield;
    protected string $view = 'filament.pages.log-log-core-page';
    protected static bool $shouldRegisterNavigation = true;
    protected static ?string $navigationLabel = 'Log LogCore';
    protected static string|UnitEnum|null $navigationGroup = 'Log';
    protected static ?string $title = 'Log LogCore';
    protected static ?int $navigationSort = 11;

    public string $filterPanjang = '';
    public string $filterJenisKayu = '';
    public string $filterTipeTransaksi = '';
    public string $limitPerJenis = '15';

    // ── Computed: log transaksi (buku besar per jenis+panjang) ──
    public function getLogsProperty()
    {
        $query = LogLogCore::with('jenisKayu');

        if ($this->filterPanjang) {
            $query->where('panjang', $this->filterPanjang);
        }

        if ($this->filterJenisKayu) {
            $query->where('id_jenis_kayu', $this->filterJenisKayu);
        }

        if ($this->filterTipeTransaksi) {
            $query->where('tipe_transaksi', $this->filterTipeTransaksi);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    // Grouping di sini pakai kombinasi jenis+panjang, bukan per lahan
    public function getLogsByKombinasiProperty()
    {
        return $this->logs->groupBy(fn($l) => $l->id_jenis_kayu . '_' . $l->panjang);
    }

    public function getStatistikProperty(): array
    {
        $logs = $this->logs;
        $totalMasuk  = $logs->where('tipe_transaksi', 'masuk');
        $totalKeluar = $logs->where('tipe_transaksi', 'keluar');

        return [
            'total_transaksi'    => $logs->count(),
            'total_masuk'        => $totalMasuk->count(),
            'total_keluar'       => $totalKeluar->count(),
            'total_nilai_masuk'  => $totalMasuk->sum('nilai'),
            'total_nilai_keluar' => $totalKeluar->sum('nilai'),
            'saldo_akhir'        => $totalMasuk->sum('nilai') - $totalKeluar->sum('nilai'),
        ];
    }
}
