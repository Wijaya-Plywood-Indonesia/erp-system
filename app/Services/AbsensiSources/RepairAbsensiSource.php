<?php

namespace App\Services\AbsensiSources;

use App\Models\RencanaPegawai;
use Illuminate\Support\Collection;

class RepairAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'repair';
    }

    public function label(): string
    {
        return 'Repair';
    }

    public function fetch(string $tanggal): Collection
    {
        return RencanaPegawai::query()
            ->with(['pegawai', 'produksiRepair'])
            ->whereHas('produksiRepair', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiRepair?->tanggal,
                'shift' => 'pagi', // tabel produksi_repairs tidak punya kolom shift
                'jam_masuk' => $item->jam_masuk,
                'jam_pulang' => $item->jam_pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->keterangan,
                'ref_id' => $item->id,
            ]);
    }
}
