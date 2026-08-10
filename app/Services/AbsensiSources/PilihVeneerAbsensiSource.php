<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiPilihVeneer;
use Illuminate\Support\Collection;

class PilihVeneerAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'pilih_veneer';
    }

    public function label(): string
    {
        return 'Pilih Veneer';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiPilihVeneer::query()
            ->with(['pegawai', 'produksiPilihVeneer'])
            ->whereHas('produksiPilihVeneer', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiPilihVeneer?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_pilih_veneer tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
