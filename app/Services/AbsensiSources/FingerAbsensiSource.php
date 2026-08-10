<?php

namespace App\Services\AbsensiSources;

use App\Models\DetailAbsensi;
use App\Models\Pegawai;
use Illuminate\Support\Collection;

class FingerAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'finger';
    }

    public function label(): string
    {
        return 'Finger Print';
    }

    public function fetch(string $tanggal): Collection
    {
        $items = DetailAbsensi::query()
            ->whereDate('tanggal', $tanggal)
            ->get();

        if ($items->isEmpty()) {
            return collect();
        }

        // Pre-fetch semua pegawai yang dibutuhkan sekaligus, hindari N+1 query
        $kodePegawaiList = $items->pluck('kode_pegawai')->unique()->filter();

        $pegawaiByKode = Pegawai::query()
            ->whereIn('kode_pegawai', $kodePegawaiList)
            ->get()
            ->keyBy('kode_pegawai');

        return $items->map(function ($item) use ($pegawaiByKode) {
            $pegawai = $pegawaiByKode->get($item->kode_pegawai);

            return [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $pegawai?->id,
                'nama_pegawai' => $pegawai?->nama_pegawai ?? "Kode: {$item->kode_pegawai} (tidak ditemukan)",
                'tanggal' => $item->tanggal,
                'shift' => 'pagi', // finger tidak punya info shift, default pagi
                'jam_masuk' => $item->jam_masuk,
                'jam_pulang' => $item->jam_pulang,
                'izin' => null,
                'keterangan' => null,
                'ref_id' => $item->id,
            ];
        });
    }
}
