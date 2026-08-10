<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiNyusup;
use Illuminate\Support\Collection;

class NyusupAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'nyusup';
    }

    public function label(): string
    {
        return 'Nyusup';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiNyusup::query()
            ->with(['pegawai', 'produksiNyusup'])
            ->whereHas('produksiNyusup', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiNyusup?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_nyusup tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
