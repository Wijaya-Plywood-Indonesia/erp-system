<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiTerimaGudangSatu;
use Illuminate\Support\Collection;

class TerimaGudangSatuAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'terima_gudang_satu';
    }

    public function label(): string
    {
        return 'Terima Gudang Satu';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiTerimaGudangSatu::query()
            ->with(['pegawai', 'produksiTerimaGudangSatu'])
            ->whereHas('produksiTerimaGudangSatu', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_produksi', $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiTerimaGudangSatu?->tanggal_produksi,
                'shift' => 'pagi', // tabel produksi_terima_gudang_satu tidak punya kolom shift
                'jam_masuk' => $item->masuk,
                'jam_pulang' => $item->pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->ket,
                'ref_id' => $item->id,
            ]);
    }
}
