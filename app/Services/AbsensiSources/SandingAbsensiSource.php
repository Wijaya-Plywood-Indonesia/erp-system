<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiSanding;
use Illuminate\Support\Collection;

class SandingAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'sanding';
    }

    public function label(): string
    {
        return 'Sanding';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiSanding::query()
            ->with(['pegawai', 'produksiSanding'])
            ->whereHas('produksiSanding', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get()
            ->map(function ($item) {
                $shift = strtolower($item->produksiSanding?->shift ?? 'pagi');

                return [
                    'sumber' => $this->key(),
                    'sumber_label' => $this->label().' '.ucfirst($shift),
                    'id_pegawai' => $item->id_pegawai,
                    'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                    'tanggal' => $item->produksiSanding?->tanggal,
                    'shift' => $shift,
                    'jam_masuk' => $item->masuk,
                    'jam_pulang' => $item->pulang,
                    'izin' => $item->ijin,
                    'keterangan' => $item->ket,
                    'ref_id' => $item->id,
                ];
            });
    }
}
