<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class NeracaAktivaPasifa extends Page
{
    protected string $view = 'filament.pages.neraca-aktiva-pasifa';

    protected static ?string $navigationLabel = 'Neraca Aset';
    protected static ?string $title = 'Neraca Aset';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';

    public Collection $aktiva;
    public Collection $pasiva;
    public float $totalAktiva = 0;
    public float $totalPasiva = 0;

    public static function canView(): bool
    {
        return auth()->user()->can('neraca_aktiva_pasifa');
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'neraca-aktiva-pasifa';
    }

    public function mount(): void
    {
        $this->aktiva = $this->generateNeraca(1000, 1999);
        $this->pasiva = $this->generateNeraca(2000, 3999);

        $this->totalAktiva = $this->aktiva->sum('total');
        $this->totalPasiva = $this->pasiva->sum('total');
    }

    /**
     * Generate Neraca berdasarkan range kode akun
     * SUM langsung dari jurnal (source of truth)
     */
    protected function generateNeraca(int $start, int $end): Collection
    {
        return DB::table('jurnal_tigas as j')
            ->join('anak_akuns as a', 'a.kode_anak_akun', '=', 'j.akun_seratus')
            ->whereBetween('j.akun_seratus', [$start, $end])
            ->select(
                'a.kode_anak_akun',
                'a.nama_anak_akun',
                DB::raw('SUM(j.total) as total')
            )
            ->groupBy('a.kode_anak_akun', 'a.nama_anak_akun')
            ->orderBy('a.kode_anak_akun')
            ->get();
    }
}
