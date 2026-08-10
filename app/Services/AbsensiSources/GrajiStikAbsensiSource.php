<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiGrajiStik;
use Illuminate\Support\Collection;

class GrajiStikAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'graji_stik';
    }

    public function label(): string
    {
        return 'Graji Stik';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiGrajiStik::query()
            ->with(['pegawai', 'grajiStik'])
            ->whereHas('grajiStik', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->grajiStik?->tanggal,
                'shift' => 'pagi', // tabel graji_stiks tidak punya kolom shift
                'jam_masuk' => $item->jam_masuk,
                'jam_pulang' => $item->jam_pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->keterangan,
                'ref_id' => $item->id,
            ]);
    }
}
