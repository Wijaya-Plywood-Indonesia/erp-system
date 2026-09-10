<?php

namespace App\Services\AbsensiSources;

use App\Models\DetailPegawaiStik;
use Illuminate\Support\Collection;

class StikAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'stik';
    }

    public function label(): string
    {
        return 'Stik';
    }

    public function fetch(string $tanggal): Collection
    {
        return DetailPegawaiStik::query()
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
                'shift' => 'pagi', // tabel produksi_stik tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
