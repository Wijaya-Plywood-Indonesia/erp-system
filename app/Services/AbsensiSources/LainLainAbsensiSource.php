<?php

namespace App\Services\AbsensiSources;

use App\Models\LainLain;
use Illuminate\Support\Collection;

class LainLainAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'lain_lain';
    }

    public function label(): string
    {
        return 'Lain-lain';
    }

    public function fetch(string $tanggal): Collection
    {
        return LainLain::query()
            ->with(['pegawai', 'detailLainLain'])
            ->whereHas('detailLainLain', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get()
            ->map(function ($item) {
                $label = $this->label();
                if (!empty($item->hasil)) {
                    $label .= ': ' . $item->hasil;
                }

                return [
                    'sumber' => $this->key(),
                    'sumber_label' => $label,
                    'id_pegawai' => $item->id_pegawai,
                    'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                    'tanggal' => $item->detailLainLain?->tanggal,
                    'shift' => 'pagi', // tabel detail_lain_lains tidak punya kolom shift
                    'jam_masuk' => $item->masuk,
                    'jam_pulang' => $item->pulang,
                    'izin' => $item->ijin,
                    'keterangan' => $item->ket,
                    'ref_id' => $item->id,
                ];
            });
    }
}
