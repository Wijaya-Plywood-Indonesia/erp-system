<?php

namespace App\Services\AbsensiSources;

use App\Models\DetailPegawaiHp;
use Illuminate\Support\Collection;

class HpAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'hp';
    }

    public function label(): string
    {
        return 'Hotpress';
    }

    public function fetch(string $tanggal): Collection
    {
        return DetailPegawaiHp::query()
            ->with(['pegawaiHp', 'produksiHp'])
            ->whereHas('produksiHp', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(function ($item) {
                $shift = strtolower($item->produksiHp?->shift ?? 'pagi');

                return [
                    'sumber' => $this->key(),
                    'sumber_label' => $this->label().' '.ucfirst($shift),
                    'id_pegawai' => $item->id_pegawai,
                    'nama_pegawai' => $item->pegawaiHp?->nama_pegawai ?? '-',
                    'tanggal' => $item->produksiHp?->tanggal_produksi,
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
