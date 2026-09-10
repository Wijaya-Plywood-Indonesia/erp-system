<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiRotary;
use Illuminate\Support\Collection;

class RotaryAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'rotary';
    }

    public function label(): string
    {
        return 'Rotary';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiRotary::query()
            ->with(['pegawai', 'produksi'])
            ->whereHas('produksi', function ($q) use ($tanggal) {
                $q->whereDate('tgl_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksi?->tgl_produksi,
                'shift' => 'pagi', // rotary tidak punya shift, default pagi
                'jam_masuk' => $item->jam_masuk,
                'jam_pulang' => $item->jam_pulang,
                'izin' => $item->izin,
                'keterangan' => $item->keterangan,
                'ref_id' => $item->id,
            ]);
    }
}
