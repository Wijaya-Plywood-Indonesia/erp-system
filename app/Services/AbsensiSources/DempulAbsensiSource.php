<?php

namespace App\Services\AbsensiSources;

use App\Models\ProduksiDempul;
use App\Models\RencanaPegawaiDempul;
use Illuminate\Support\Collection;

class DempulAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'dempul';
    }

    public function label(): string
    {
        return 'Dempul';
    }

    public function fetch(string $tanggal): Collection
    {
        $kolomTanggal = ProduksiDempul::kolomTanggalAktif();

        return RencanaPegawaiDempul::query()
            ->with(['pegawai', 'produksiDempul'])
            ->whereHas('produksiDempul', function ($q) use ($tanggal, $kolomTanggal) {
                $q->whereDate($kolomTanggal, $tanggal);
            })
            ->get()
            ->map(fn ($item) => [
                'sumber' => $this->key(),
                'sumber_label' => $this->label(),
                'id_pegawai' => $item->id_pegawai,
                'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                'tanggal' => $item->produksiDempul?->tanggalDempul,
                'shift' => 'pagi', // tabel produksi_dempuls tidak punya kolom shift
                'jam_masuk' => $item->jam_masuk,
                'jam_pulang' => $item->jam_pulang,
                'izin' => $item->ijin,
                'keterangan' => $item->keterangan,
                'ref_id' => $item->id,
            ]);
    }
}
