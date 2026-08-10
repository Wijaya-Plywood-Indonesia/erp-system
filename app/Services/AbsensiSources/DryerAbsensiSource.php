<?php

namespace App\Services\AbsensiSources;

use App\Models\DetailPegawai;
use Illuminate\Support\Collection;

class DryerAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'dryer';
    }

    public function label(): string
    {
        return 'Press Dryer';
    }

    public function fetch(string $tanggal): Collection
    {
        return DetailPegawai::query()
            ->with(['pegawai', 'produksi'])
            ->whereHas('produksi', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksi?->tanggal_produksi,
                'shift' => strtolower($item->produksi?->shift ?? 'pagi'),
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
