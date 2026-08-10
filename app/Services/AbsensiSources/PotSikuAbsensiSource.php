<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiPotSiku;
use Illuminate\Support\Collection;

class PotSikuAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'pot_siku';
    }

    public function label(): string
    {
        return 'Pot Siku';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiPotSiku::query()
            ->with(['pegawai', 'produksiPotSiku'])
            ->whereHas('produksiPotSiku', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiPotSiku?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_pot_siku tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
