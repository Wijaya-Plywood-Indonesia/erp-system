<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiPalet;
use Illuminate\Support\Collection;

class PegawaiPaletAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'pegawai_palet';
    }

    public function label(): string
    {
        return 'Pegawai Palet';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiPalet::query()
            ->with(['pegawai', 'produksiPalet'])
            ->whereHas('produksiPalet', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiPalet?->tanggal?->format('Y-m-d'),
                'shift' => 'pagi', // ProduksiPalet tidak memiliki kolom shift
                'jam_masuk' => $item->jam_masuk,
                'jam_pulang' => $item->jam_pulang,
                'izin' => $item->izin,
                'keterangan' => $item->keterangan,
                'ref_id' => $item->id,
            ]);
    }
}
