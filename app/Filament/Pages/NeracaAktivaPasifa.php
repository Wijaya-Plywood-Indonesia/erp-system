<?php

namespace App\Filament\Pages;

use App\Models\AkunGroup;
use App\Models\JurnalUmum;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use BackedEnum;
use UnitEnum;

class NeracaAktivaPasifa extends Page
{
    protected string $view = 'filament.pages.neraca-aktiva-pasifa';

    protected static ?string $navigationLabel = 'Neraca Aktiva Pasiva';
    protected static ?string $title = 'Neraca Aktiva Pasiva';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';

    // protected static Width|string|null $maxContentWidth = Width::Full;
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
            $this->buildTree($group, $start, $end)
        )->toArray();
    }

    protected function buildTree($group, $start, $end): array
    {
        $total = 0;
        $accounts = [];

        foreach ($group->anakAkuns as $akun) {

            $saldo = $this->calculateAccountSaldo($akun, $start, $end);

            $accounts[] = [
                'kode' => $akun->kode_anak_akun,
                'nama' => $akun->nama_anak_akun,
                'total' => $saldo,
            ];

            $total += $saldo;
        }

        $children = [];

        foreach ($group->children as $child) {
            $childData = $this->buildTree($child, $start, $end);
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

    protected function calculateAccountSaldo($akun, $start, $end): float
    {
        $kodeList = [$akun->kode_anak_akun];

        // Tambahkan semua sub anak akun
        foreach ($akun->subAnakAkuns as $sub) {
            $kodeList[] = $sub->kode_sub_anak_akun;
        }

        $journals = JurnalUmum::whereIn('no_akun', $kodeList)
            ->whereBetween('tgl', [$start, $end])
            ->get();

        $saldo = 0;

        foreach ($journals as $j) {

            if ($j->hit_kbk === 'k') {
                $nominal = ($j->harga ?? 0) * ($j->m3 ?? 0);
            } elseif ($j->hit_kbk === 'b') {
                $nominal = ($j->harga ?? 0) * ($j->banyak ?? 0);
            } else {
                $nominal = $j->harga ?? 0;
            }

            $saldo += $nominal;
        }

        return $saldo;
    }
}