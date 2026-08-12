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
            ->with(['pegawai', 'produksiRepair.detailHasilRepairs.rencanaPegawais', 'produksiRepair.detailHasilRepairs.ukuran'])
            ->whereHas('produksiRepair', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get()
            ->map(function ($item) {
                // Cari apakah pegawai ini terikat di detail hasil repair tertentu untuk mendapatkan ukuran
                $ukuranLabels = [];
                if ($item->produksiRepair && $item->produksiRepair->detailHasilRepairs) {
                    foreach ($item->produksiRepair->detailHasilRepairs as $detail) {
                        // Periksa apakah rencana pegawai ini ada di dalam list rencanaPegawais pivot
                        $hasPegawai = $detail->rencanaPegawais->contains('id', $item->id);
                        if ($hasPegawai && $detail->ukuran) {
                            $ukuranLabels[] = $detail->ukuran->panjang . 'x' . $detail->ukuran->lebar;
                        }
                    }
                }

                $label = $this->label();
                if (!empty($ukuranLabels)) {
                    $label .= ': ' . implode(', ', array_unique($ukuranLabels));
                }

                return [
                    'sumber' => $this->key(),
                    'sumber_label' => $label,
                    'id_pegawai' => $item->id_pegawai,
                    'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                    'tanggal' => $item->produksiRepair?->tanggal,
                    'shift' => 'pagi', // tabel produksi_repairs tidak punya kolom shift
                    'jam_masuk' => $item->jam_masuk,
                    'jam_pulang' => $item->jam_pulang,
                    'izin' => $item->ijin,
                    'keterangan' => $item->keterangan,
                    'ref_id' => $item->id,
                ];
            });
    }
}
