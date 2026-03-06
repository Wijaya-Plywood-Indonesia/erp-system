<?php

namespace App\Filament\Pages;

use App\Models\AkunGroup;
use App\Models\JurnalUmum;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Notifications\Notification;
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

    public $from_month;
    public $to_month;
    public bool $hideZero = false;

    public array $results = [];

    public function mount(): void
    {
        $now = Carbon::now();

        $this->from_month = $now->copy()->startOfMonth()->format('Y-m');
        $this->to_month = $now->format('Y-m');
    }

    public function generate(): void
    {
        $this->results = [];

        if (!$this->from_month || !$this->to_month) {
            return;
        }

        $start = Carbon::createFromFormat('Y-m', $this->from_month)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $this->to_month)->endOfMonth();

        if ($start->greaterThan($end)) {
            Notification::make()
                ->title('Range bulan tidak valid')
                ->danger()
                ->send();
            return;
        }

        $months = $start->diffInMonths($end) + 1;

        if ($months > 12) {
            Notification::make()
                ->title('Maksimal 12 bulan')
                ->warning()
                ->send();
            return;
        }

        $period = CarbonPeriod::create($start, '1 month', $end);

        foreach ($period as $month) {

            $periodStart = $month->copy()->startOfMonth();
            $periodEnd = $month->copy()->endOfMonth();

            $this->results[] = [
                'label' => $month->format('F Y'),
                'aktiva' => array_filter(
                    $this->buildSide('AKTIVA', $periodStart, $periodEnd)
                ),
                'pasiva' => array_filter(
                    $this->buildSide('PASIVA', $periodStart, $periodEnd)
                ),
            ];
        }
    }

    protected function buildSide(string $side, $start, $end): array
    {
        $groups = AkunGroup::with([
            'anakAkuns.children',
            'anakAkuns.subAnakAkuns',
            'children.children'
        ])
            ->whereNull('parent_id')
            ->where('hidden', false)
            ->where('nama', 'like', "%{$side}%")
            ->orderBy('order')
            ->get();

        $data = [];

        foreach ($groups as $group) {
            $groupData = $this->buildGroupTree($group, $start, $end);

            if ($groupData !== null) {
                $data[] = $groupData;
            }
        }

        return $data;
    }

    protected function buildGroupTree($group, $start, $end): ?array
    {
        $accounts = [];
        $children = [];

        foreach ($group->anakAkuns->whereNull('parent_id') as $akun) {
            $akunData = $this->buildAkunTree($akun, $start, $end);
            if ($akunData !== null)
                $accounts[] = $akunData;
        }

        foreach ($group->children as $child) {
            $childData = $this->buildGroupTree($child, $start, $end);
            if ($childData !== null)
                $children[] = $childData;
        }

        $total = collect($accounts)->sum('total') +
            collect($children)->sum('total');

        if ($this->hideZero && abs($total) < 0.01) {
            return null;
        }

        return [
            'nama' => $group->nama,
            'total' => round($total, 2),
            'accounts' => $accounts,
            'children' => $children,
        ];
    }

    protected function buildAkunTree($akun, $start, $end): ?array
    {
        $children = [];

        $direct = JurnalUmum::whereBetween('tgl', [$start, $end])
            ->where('no_akun', $akun->kode_anak_akun)
            ->get();

        $total = $direct->sum('debit') - $direct->sum('kredit');

        foreach ($akun->subAnakAkuns as $sub) {
            $subJurnal = JurnalUmum::whereBetween('tgl', [$start, $end])
                ->where('no_akun', $sub->kode_sub_anak_akun)
                ->get();

            $total += $subJurnal->sum('debit') - $subJurnal->sum('kredit');
        }

        foreach ($akun->children as $child) {
            $childData = $this->buildAkunTree($child, $start, $end);
            if ($childData !== null)
                $children[] = $childData;
        }

        $total += collect($children)->sum('total');

        if ($this->hideZero && abs($total) < 0.01) {
            return null;
        }

        return [
            'kode' => $akun->kode_anak_akun,
            'nama' => $akun->nama_anak_akun,
            'total' => round($total, 2),
            'children' => $children,
        ];
    }
}