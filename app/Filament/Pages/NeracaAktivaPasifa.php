<?php

namespace App\Filament\Pages;

use App\Models\AkunGroup;
use App\Models\JurnalUmum;
use Carbon\Carbon;
use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class NeracaAktivaPasifa extends Page
{
    protected string $view = 'filament.pages.neraca-aktiva-pasifa';

    protected static ?string $navigationLabel = 'Neraca Aktiva Pasiva';
    protected static ?string $title = 'Neraca Aktiva Pasiva';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';

    public $start_date;
    public $end_date;
    public $repeat = 1;

    public array $results = [];

    public function mount(): void
    {
        $today = Carbon::today();

        $this->start_date = $today->copy()->startOfMonth()->format('Y-m-d');
        $this->end_date = $today->format('Y-m-d');

        $this->applyFilter();
    }

    public function applyFilter(): void
    {
        $this->results = [];

        $start = Carbon::parse($this->start_date)->startOfMonth();
        $repeat = min($this->repeat, 12);

        for ($i = 0; $i < $repeat; $i++) {

            $periodStart = $start->copy()->addMonths($i)->startOfMonth();
            $periodEnd = $start->copy()->addMonths($i)->endOfMonth();

            if ($i === 0) {
                $periodEnd = Carbon::parse($this->end_date);
            }

            $this->results[] = [
                'label' => $periodStart->format('F Y'),
                'aktiva' => $this->buildSide('AKTIVA', $periodStart, $periodEnd),
                'pasiva' => $this->buildSide('PASIVA', $periodStart, $periodEnd),
            ];
        }
    }

    protected function buildSide(string $side, $start, $end): array
    {
        $groups = AkunGroup::with([
            'anakAkuns.childrenRecursive',
            'anakAkuns.subAnakAkuns',
            'children.childrenRecursive'
        ])
            ->whereNull('parent_id')
            ->where('hidden', false)
            ->where('nama', 'like', "%{$side}%")
            ->orderBy('order')
            ->get();

        return $groups->map(
            fn($group) =>
            $this->buildGroupTree($group, $start, $end)
        )->toArray();
    }

    protected function buildGroupTree($group, $start, $end): array
    {
        $total = 0;
        $accounts = [];

        foreach ($group->anakAkuns->whereNull('parent_id') as $akun) {

            $akunData = $this->buildAkunTree($akun, $start, $end);

            $total += $akunData['total'];
            $accounts[] = $akunData;
        }

        $children = [];

        foreach ($group->children as $child) {
            $childData = $this->buildGroupTree($child, $start, $end);
            $total += $childData['total'];
            $children[] = $childData;
        }

        return [
            'nama' => $group->nama,
            'total' => $total,
            'accounts' => $accounts,
            'children' => $children,
        ];
    }

    protected function buildAkunTree($akun, $start, $end): array
    {
        $total = $this->calculateRecursiveSaldo($akun, $start, $end);

        $children = [];

        foreach ($akun->children as $child) {
            $children[] = $this->buildAkunTree($child, $start, $end);
        }

        return [
            'kode' => $akun->kode_anak_akun,
            'nama' => $akun->nama_anak_akun,
            'total' => $total,
            'children' => $children,
        ];
    }

    protected function calculateRecursiveSaldo($akun, $start, $end): float
    {
        $kodeList = [];
        $this->collectAllKode($akun, $kodeList);

        $journals = JurnalUmum::whereBetween('tgl', [$start, $end])
            ->whereIn('no_akun', $kodeList)
            ->get();

        $totalDebit = $journals->sum('debit');
        $totalKredit = $journals->sum('kredit');

        return $totalDebit - $totalKredit;
    }

    protected function collectAllKode($akun, &$kodeList): void
    {
        $kodeList[] = $akun->kode_anak_akun;

        foreach ($akun->subAnakAkuns as $sub) {
            $kodeList[] = $sub->kode_sub_anak_akun;
        }

        foreach ($akun->children as $child) {
            $this->collectAllKode($child, $kodeList);
        }
    }
}