<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiJoint;
use Illuminate\Support\Collection;

class JointAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'joint';
    }

    public function label(): string
    {
        return 'Joint';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiJoint::query()
            ->with(['pegawai', 'produksiJoint'])
            ->whereHas('produksiJoint', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiJoint?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_joint tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
