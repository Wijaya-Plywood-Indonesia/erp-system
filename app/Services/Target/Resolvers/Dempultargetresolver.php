<?php

namespace App\Services\Target\Resolvers;

use App\Models\Mesin;
use App\Models\Target;

/**
 * Resolver target Dempul (nama lama di data: "Malik Platform").
 *
 * Cuma ada SATU target flat, tidak dibedakan per ukuran/tebal/jenis
 * kayu/grade. Dicari lewat mesin bernama "DEMPUL".
 */
class DempulTargetResolver
{
    public function resolve(): ?Target
    {
        $idMesin = $this->idMesinDempul();
        if (! $idMesin) {
            return null;
        }

        return Target::query()
            ->where('id_mesin', $idMesin)
            ->orderByDesc('id')
            ->first();
    }

    private function idMesinDempul(): ?int
    {
        $id = Mesin::whereRaw('UPPER(nama_mesin) = ?', ['DEMPUL'])->value('id');

        return $id ? (int) $id : null;
    }
}