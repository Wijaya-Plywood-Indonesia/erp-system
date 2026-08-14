<?php

namespace App\Services\AbsensiSources;

use App\Models\DetailPegawaiKedi;
use Illuminate\Support\Collection;

class KediAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'kedi';
    }

    public function label(): string
    {
        return 'Kedi';
    }

    public function fetch(string $tanggal): Collection
    {
        return DetailPegawaiKedi::query()
            ->with(['pegawai', 'produksiKedi'])
            ->whereHas('produksiKedi', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get()
            ->map(function ($item) {
                $label = $this->label();
                if (! empty($item->tugas)) {
                    $label .= ': '.$item->tugas;
                }

                return [
                    'sumber' => $this->key(),
                    'sumber_label' => $label,
                    'id_pegawai' => $item->id_pegawai,
                    'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                    'tanggal' => $item->produksiKedi?->tanggal,
                    'shift' => 'pagi', // tabel produksi_kedi tidak punya kolom shift
                    'jam_masuk' => $item->masuk,
                    'jam_pulang' => $item->pulang,
                    'izin' => $item->ijin,
                    'keterangan' => $item->ket,
                    'ref_id' => $item->id,
                ];
            });
    }
}
