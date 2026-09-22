<?php

namespace App\Services\AbsensiSources;

use App\Models\PegawaiRotary;
use Illuminate\Support\Collection;

class RotaryAbsensiSource implements AbsensiSourceInterface
{
    public function key(): string
    {
        return 'rotary';
    }

    public function label(): string
    {
        return 'Rotary';
    }

    public function fetch(string $tanggal): Collection
    {
        return PegawaiRotary::query()
            ->with(['pegawai', 'produksi.mesin'])
            ->whereHas('produksi', function ($q) use ($tanggal) {
                $q->whereDate('tgl_produksi', $tanggal);
            })
            ->get()
            ->map(function ($item) {
                $namaMesin = $item->produksi?->mesin?->nama_mesin;

                // Label = "Rotary" + nama mesin (misal "Rotary Spindless").
                // Kalau mesin tidak ketemu (relasi kosong), fallback ke
                // label dasar "Rotary" saja — supaya filter dropdown &
                // str_starts_with() di blade/NewAbsensi.php tetap aman
                // match ke label() dasar walau tanpa detail mesin.
                $labelDenganMesin = $namaMesin
                    ? $this->label().' '.strtoupper($namaMesin)
                    : $this->label();

                return [
                    'sumber' => $this->key(),
                    'sumber_label' => $labelDenganMesin,
                    'id_pegawai' => $item->id_pegawai,
                    'nama_pegawai' => $item->pegawai?->nama_pegawai ?? '-',
                    'tanggal' => $item->produksi?->tgl_produksi,
                    'shift' => 'pagi', // rotary tidak punya shift, default pagi
                    'jam_masuk' => $item->jam_masuk,
                    'jam_pulang' => $item->jam_pulang,
                    'izin' => $item->izin,
                    'keterangan' => $item->keterangan,
                    'ref_id' => $item->id,
                ];
            });
    }
}
