<?php

namespace App\Services\Target\Resolvers;

use App\Models\Mesin;
use App\Models\Target;

/**
 * Resolver target Pilih Plywood.
 *
 * Target diambil dari mesin "PILIH DAN TEMBEL", dengan 2 kategori flat
 * (tidak lagi per tebal):
 *   - "Pilih 5s"    : barang tebal 5mm DAN jenis kayu Sengon -> grade 'PILIH5S'
 *   - "Pilih Mebel" : barang lainnya (tanpa grade / default)
 */
class PilihPlywoodTargetResolver
{
    /**
     * @param  float|string|null  $tebal  tebal barang (mm)
     * @param  string|null  $jenisKayu  nama jenis kayu barang (mis. "Sengon", "Meranti")
     */
    public function resolve(float|string|null $tebal, ?string $jenisKayu = null): ?Target
    {
        $idMesin = $this->idMesinPilihDanTembel();
        if (! $idMesin) {
            return null;
        }

        $is5sSengon = $this->is5Sengon($tebal, $jenisKayu);

        $query = Target::query()->where('id_mesin', $idMesin);

        if ($is5sSengon) {
            $query->whereRaw('LOWER(grade) = ?', ['pilih5s']);
        } else {
            $query->where(fn ($q) => $q->whereNull('grade')->orWhere('grade', ''));
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Barang dianggap "5s" kalau tebal-nya 5mm DAN jenis kayunya Sengon
     * (dicek dengan "mengandung kata", bukan sama persis & tidak case-sensitive).
     */
    private function is5Sengon(float|string|null $tebal, ?string $jenisKayu): bool
    {
        if ($tebal === null || $tebal === '') {
            return false;
        }

        $tebalCocok = abs(round((float) $tebal, 2) - 5.00) < 0.01;
        $jenisCocok = $jenisKayu !== null && str_contains(strtolower(trim($jenisKayu)), 'sengon');

        return $tebalCocok && $jenisCocok;
    }

    private function idMesinPilihDanTembel(): ?int
    {
        $id = Mesin::whereRaw('UPPER(nama_mesin) = ?', ['PILIH DAN TEMBEL'])->value('id');

        return $id ? (int) $id : null;
    }
}