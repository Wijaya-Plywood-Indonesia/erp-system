<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiGrajiBalken;
use Illuminate\Support\Collection;

class GrajiBalkenAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'graji_balken';
    }

    public function label(): string
    {
        return 'Graji Balken';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiGrajiBalken::query()
            ->with(['pegawai', 'produksiGrajiBalken'])
            ->whereHas('produksiGrajiBalken', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiGrajiBalken?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_graji_balken tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
