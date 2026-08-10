<?php

namespace App\Services\AbsensiSources;

use App\Models\pegawai_guellotine;
use Illuminate\Support\Collection;

class GuellotineAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'guellotine';
    }

    public function label(): string
    {
        return 'Guellotine';
    }

    public function fetch(string $tanggal): Collection
    {
        return pegawai_guellotine::query()
            ->with(['pegawai', 'produksiGuellotine'])
            ->whereHas('produksiGuellotine', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiGuellotine?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_guellotine tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
