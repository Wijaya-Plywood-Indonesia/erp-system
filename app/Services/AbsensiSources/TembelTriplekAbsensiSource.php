<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiTembeltriplek;
use Illuminate\Support\Collection;

class TembelTriplekAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'tembel_triplek';
    }

    public function label(): string
    {
        return 'Tembel Triplek';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiTembeltriplek::query()
            ->with(['pegawai', 'produksiTembeltriplek'])
            ->whereHas('produksiTembeltriplek', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiTembeltriplek?->tanggal,
                'shift' => 'pagi', // tabel produksi_tembel_triplek tidak punya kolom shift
                'jam_masuk' => $item->jam_masuk,
                'jam_pulang' => $item->jam_pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->keterangan,
                'ref_id' => $item->id,
            ]);
    }
}
