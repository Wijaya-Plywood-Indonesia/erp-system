<?php

namespace App\Filament\Pages;

use App\Models\AkunGroup;
use App\Models\JurnalUmum;
use App\Models\AnakAkun;
use Carbon\CarbonPeriod;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class NeracaAktivaPasifa extends Page
{
    protected string $view = 'filament.pages.neraca-aktiva-pasifa';
    protected Width|string|null $maxContentWidth = Width::Full;

    public int $bulan_mulai;
    public int $tahun_mulai;
    public int $bulan_akhir;
    public int $tahun_akhir;

    public array $result = [];

    public function debug()
    {
        dd($this->result);
    }
    private function loadChildren()
    {
        return AnakAkun::select('id', 'parent')
            ->whereNotNull('parent')
            ->get()
            ->groupBy('parent')
            ->map(function ($items) {
                return $items->pluck('id')->toArray();
            })
            ->toArray();
    }

    public function mount(): void
    {
        $now = now();

        $this->bulan_mulai = $now->month;
        $this->tahun_mulai = $now->year;

        $this->bulan_akhir = $now->month;
        $this->tahun_akhir = $now->year;

        $this->loadData();
    }

    public function loadData(): void
    {
        $start = now()->setDate($this->tahun_mulai, $this->bulan_mulai, 1)->startOfMonth();
        $end = now()->setDate($this->tahun_akhir, $this->bulan_akhir, 1)->endOfMonth();

        $period = CarbonPeriod::create($start, '1 month', $end);

        if (iterator_count($period) > 12) {
            $this->addError('periode', 'Maksimal 12 bulan!');
            return;
        }

        $this->result = [];

        foreach ($period as $bulan) {
            $this->result[] = [
                'label' => $bulan->translatedFormat('F Y'),
                'groups' => $this->generateMonthReport(
                    $bulan->copy()->startOfMonth(),
                    $bulan->copy()->endOfMonth()
                ),
            ];
        }
    }


    /* ===========================================================
     * GENERATE MONTH REPORT
     * ===========================================================
     */
    private function generateMonthReport($start, $end): array
    {
        $rows = $this->loadTransactions($start, $end);
        $groupTrees = $this->loadGroupTree();
        $output = $this->prepareGroupStructure($groupTrees);

        $this->injectAllAccounts($output, $groupTrees);
        $this->applyTransactions($output, $rows);
        $childrenMap = $this->loadChildren();
        $this->attachChildren($output, $childrenMap);
        return $output;
    }


    /* ===========================================================
     * LOAD TRANSACTIONS
     * ===========================================================
     */
    private function loadTransactions($start, $end)
    {
        return JurnalUmum::with(['subAkun.anakAkun.akunGroups.parent'])
            ->whereBetween('tgl', [$start, $end])
            ->get();
    }

    private function attachChildren(array &$output, array $childrenMap)
    {
        foreach ($output as $groupKey => &$group) {
            foreach ($group['sub'] as $subKey => &$sub) {
                foreach ($sub['akun'] as $akunId => &$akun) {

                    if (isset($childrenMap[$akunId])) {
                        $akun['children'] = [];

                        foreach ($childrenMap[$akunId] as $childId) {
                            $akun['children'][$childId] = [
                                'kode' => AnakAkun::find($childId)->kode_anak_akun,
                                'nama' => AnakAkun::find($childId)->nama_anak_akun,
                                'saldo' => 0,
                            ];
                        }
                    }
                }
            }
        }
    }
    /* ===========================================================
     * LOAD GROUP TREE (multilevel)
     * ===========================================================
     */
    private function loadGroupTree(): array
    {
        $groups = AkunGroup::orderBy('order')->get();

        $byId = $groups->keyBy('id')->toArray();
        $tree = [];

        foreach ($byId as &$g) {
            $pid = $g['parent_id'];

            if ($pid && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$g;
            } else {
                $tree[] = &$g;
            }
        }
        unset($g);

        return $tree;
    }


    /* ===========================================================
     * PREPARE GROUP STRUCTURE
     * ===========================================================
     */
    private function prepareGroupStructure(array $groupTrees): array
    {
        $output = [];

        foreach ($groupTrees as $group) {
            $output['G' . $group['id']] = [
                'nama' => $group['nama'],
                'sub' => [],     // subgroups
            ];

            if (!empty($group['children'])) {
                foreach ($group['children'] as $sub) {
                    $output['G' . $group['id']]['sub']['SG' . $sub['id']] = [
                        'nama' => $sub['nama'],
                        'akun' => []
                    ];
                }
            }
        }

        // jika akun tidak punya group/sub group
        $output['UNGROUPED'] = [
            'nama' => 'Tidak Masuk Group',
            'sub' => [
                'SG0' => [
                    'nama' => 'Tidak Ada Kategori',
                    'akun' => []
                ]
            ]
        ];

        return $output;
    }


    /* ===========================================================
     * INSERT ALL ACCOUNTS INTO SUB GROUPS
     * ===========================================================
     */
    private function injectAllAccounts(array &$output, array $groupTrees): void
    {
        $accounts = AnakAkun::with('akunGroups.parent')
            ->orderBy('kode_anak_akun')
            ->get();

        foreach ($accounts as $akun) {

            $groupId = optional($akun->akunGroups->first())->parent_id;
            $subId = optional($akun->akunGroups->first())->id;

            if ($groupId && $subId) {
                $groupKey = 'G' . $groupId;
                $subKey = 'SG' . $subId;
            } else {
                $groupKey = 'UNGROUPED';
                $subKey = 'SG0';
            }

            $output[$groupKey]['sub'][$subKey]['akun'][$akun->id] = [
                'kode' => $akun->kode_anak_akun,
                'nama' => $akun->nama_anak_akun,
                'saldo' => 0,
            ];
        }
    }


    /* ===========================================================
     * APPLY TRANSACTIONS
     * ===========================================================
     */
    private function applyTransactions(array &$output, $rows): void
    {
        foreach ($rows as $row) {

            $anak = $row->subAkun->anakAkun ?? null;
            if (!$anak)
                continue;

            $groupId = optional($anak->akunGroups->first())->parent_id;
            $subId = optional($anak->akunGroups->first())->id;

            if ($groupId && $subId) {
                $groupKey = 'G' . $groupId;
                $subKey = 'SG' . $subId;
            } else {
                $groupKey = 'UNGROUPED';
                $subKey = 'SG0';
            }

            if (isset($output[$groupKey]['sub'][$subKey]['akun'][$anak->id])) {
                $output[$groupKey]['sub'][$subKey]['akun'][$anak->id]['saldo'] +=
                    ($row->debit - $row->kredit);
            }
        }
    }
}