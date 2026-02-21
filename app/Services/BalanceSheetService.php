<?php

namespace App\Services;

use App\Models\AkunGroup;
use App\Models\AnakAkun;
use App\Models\JurnalUmum;
use App\Models\SubAnakAkun;
use DateTime;

class BalanceSheetService
{
    /**
     * Ambil tree neraca lengkap (Aktiva, Pasiva, dst)
     */
    public function getNeraca(DateTime|string|null $start = null, DateTime|string|null $end = null): array
    {
        $root = AkunGroup::where('nama', 'Neraca')->first();
        if (!$root)
            return [];

        return $this->mapGroupRecursive($root, $start, $end);
    }

    /**
     * Rekursif ambil group + saldo dari akun + anak group
     */
    private function mapGroupRecursive(AkunGroup $group, $start, $end): array
    {
        // Ambil akun-akun yang dimiliki group ini (array kode akun)
        $akunList = collect($group->akun ?? [])
            ->map(fn($kode) => $this->mapAkunWithSaldo($kode, $start, $end))
            ->filter(); // hilangkan null

        // Rekursif child groups
        $children = $group->children->map(
            fn($child) =>
            $this->mapGroupRecursive($child, $start, $end)
        );

        // Hitung total group = total akun + total anak group
        $groupTotal = $akunList->sum('saldo')
            + $children->sum(fn($c) => $c['total']);

        return [
            'group' => $group,
            'accounts' => $akunList,
            'children' => $children,
            'total' => $groupTotal,
        ];
    }

    /**
     * Ambil saldo akun berdasarkan kode akun
     */
    private function mapAkunWithSaldo($kodeAkun, $start, $end): ?array
    {
        // Ambil SubAnakAkun berdasarkan kode
        $akun = SubAnakAkun::where('kode_sub_anak_akun', $kodeAkun)->first();
        if (!$akun)
            return null;

        // Ambil jurnal
        $query = JurnalUmum::where('no_akun', $kodeAkun);

        if ($start)
            $query->whereDate('tgl', '>=', $start);
        if ($end)
            $query->whereDate('tgl', '<=', $end);

        $journals = $query->get();

        $debit = $journals->sum('debit');
        $kredit = $journals->sum('kredit');
        $saldo = $debit - $kredit;

        return [
            'kode' => $kodeAkun,
            'nama' => $akun->nama_sub_anak_akun ?? '-',
            'debit' => $debit,
            'kredit' => $kredit,
            'saldo' => $saldo,
        ];
    }
}