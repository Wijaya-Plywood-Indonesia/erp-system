<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiTurunKayu;
use Illuminate\Support\Collection;

class TurunKayuAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'turun_kayu';
    }

    public function label(): string
    {
        return 'Turun Kayu';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiTurunKayu::query()
            ->with(['pegawai', 'turunKayu'])
            ->whereHas('turunKayu', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->turunKayu?->tanggal,
                'shift' => 'pagi', // tabel turun_kayus tidak punya kolom shift
                'jam_masuk' => $item->jam_masuk,
                'jam_pulang' => $item->jam_pulang,
                'izin' => $item->izin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
