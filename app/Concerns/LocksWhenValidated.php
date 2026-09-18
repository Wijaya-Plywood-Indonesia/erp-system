<?php

namespace App\Concerns;

use App\Services\ProduksiLockService;

/**
 * Dipakai di RelationManager produksi Press Dryer / Kedi / Stik.
 *
 * Cara pakai (di CLASS yang memakai trait ini, BUKAN di trait):
 *   use LocksWhenValidated;
 *   protected string $validasiRelasi = 'validasiPressDryers';
 *   // khusus Kedi, kalau relation manager ini hanya terikat satu tahap:
 *   protected ?string $validasiTipe = 'masuk';
 *
 * PENTING: Trait ini SENGAJA tidak mendeklarasikan property
 * $validasiRelasi / $validasiTipe. PHP melarang typed property di trait
 * punya default value yang beda dengan default di class pemakainya
 * (fatal error "incompatible definition"). Jadi tiap class WAJIB
 * mendeklarasikan sendiri property-nya masing-masing.
 *
 * isReadOnly() = true akan menyembunyikan seluruh tombol
 * Create/Edit/Delete bawaan Filament pada relation manager tsb.
 */
trait LocksWhenValidated
{
    public function isReadOnly(): bool
    {
        $relasi = $this->validasiRelasi ?? 'validasiPressDryers';
        $tipe = $this->validasiTipe ?? null;

        return ProduksiLockService::isLocked(
            $this->getOwnerRecord(),
            $relasi,
            $tipe,
        );
    }

    /**
     * Helper untuk dipakai di ->hidden() / ->visible() pada custom Action
     * yang tidak ikut terpengaruh isReadOnly().
     */
    public function terkunci(): bool
    {
        return $this->isReadOnly();
    }
}