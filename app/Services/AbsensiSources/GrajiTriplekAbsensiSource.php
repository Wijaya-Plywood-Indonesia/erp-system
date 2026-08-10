<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiGrajiTriplek;
use Illuminate\Support\Collection;

class GrajiTriplekAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'graji_triplek';
    }

    public function label(): string
    {
        return 'Graji Triplek';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiGrajiTriplek::query()
            ->with(['pegawaiGrajiTriplek', 'produksiGrajiTriplek'])
            ->whereHas('produksiGrajiTriplek', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawaiGrajiTriplek?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiGrajiTriplek?->tanggal_produksi,
                'shift' => strtolower($item->produksiGrajiTriplek?->shift ?? 'pagi'),
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
