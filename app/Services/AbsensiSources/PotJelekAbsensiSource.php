<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiPotJelek;
use Illuminate\Support\Collection;

class PotJelekAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'pot_jelek';
    }

    public function label(): string
    {
        return 'Pot Jelek';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiPotJelek::query()
            ->with(['pegawai', 'produksiPotJelek'])
            ->whereHas('produksiPotJelek', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiPotJelek?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_pot_jelek tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
