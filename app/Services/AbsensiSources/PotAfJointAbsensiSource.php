<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiPotAfJoint;
use Illuminate\Support\Collection;

class PotAfJointAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'pot_af_joint';
    }

    public function label(): string
    {
        return 'Pot AF Joint';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiPotAfJoint::query()
            ->with(['pegawai', 'produksiPotAfJoint'])
            ->whereHas('produksiPotAfJoint', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiPotAfJoint?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_pot_af_joint tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
