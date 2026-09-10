<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiPilihPlywood;
use Illuminate\Support\Collection;

class PilihPlywoodAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'pilih_plywood';
    }

    public function label(): string
    {
        return 'Pilih Plywood';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiPilihPlywood::query()
            ->with(['pegawai', 'produksiPilihPlywood'])
            ->whereHas('produksiPilihPlywood', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiPilihPlywood?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_pilih_plywood tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
