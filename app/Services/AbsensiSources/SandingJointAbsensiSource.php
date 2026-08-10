<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiSandingJoint;
use Illuminate\Support\Collection;

class SandingJointAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'sanding_joint';
    }

    public function label(): string
    {
        return 'Sanding Joint';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiSandingJoint::query()
            ->with(['pegawai', 'produksiSandingJoint'])
            ->whereHas('produksiSandingJoint', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiSandingJoint?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_sanding_joint tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
