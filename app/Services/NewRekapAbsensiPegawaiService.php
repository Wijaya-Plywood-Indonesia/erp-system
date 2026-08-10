<?php

namespace App\Services;

use App\Models\DetailAbsensi;
use App\Models\Pegawai;
use App\Services\AbsensiSources\AbsensiSourceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NewRekapAbsensiPegawaiService
{
    /** @var AbsensiSourceInterface[] */
    protected array $sources;

    public function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    public function getRekap(string $tanggal): Collection
    {
        $rekap = collect($this->sources)
            ->flatMap(fn ($source) => $source->fetch($tanggal))
            ->sortBy('nama_pegawai')
            ->values();

        return $this->enrichWithFinger($rekap, $tanggal);
    }

    protected function enrichWithFinger(Collection $rekap, string $tanggal): Collection
    {
        if ($rekap->isEmpty()) {
            return $rekap;
        }

        $idPegawaiList = $rekap->pluck('id_pegawai')->filter()->unique();

        $kodeByIdPegawai = Pegawai::query()
            ->whereIn('id', $idPegawaiList)
            ->pluck('kode_pegawai', 'id');

        $kodePegawaiList = $kodeByIdPegawai->values()->unique();

        $tanggalBerikutnya = Carbon::parse($tanggal)->addDay()->format('Y-m-d');

        $fingerHariIni = DetailAbsensi::query()
            ->whereDate('tanggal', $tanggal)
            ->whereIn('kode_pegawai', $kodePegawaiList)
            ->get()
            ->groupBy('kode_pegawai');

        $fingerBesok = DetailAbsensi::query()
            ->whereDate('tanggal', $tanggalBerikutnya)
            ->whereIn('kode_pegawai', $kodePegawaiList)
            ->get()
            ->groupBy('kode_pegawai');

        return $rekap->map(function ($row) use ($kodeByIdPegawai, $fingerHariIni, $fingerBesok) {
            $kode = $kodeByIdPegawai->get($row['id_pegawai']);
            $row['kode_pegawai'] = $kode;

            if (! $kode) {
                $row['jam_masuk_finger'] = null;
                $row['jam_pulang_finger'] = null;

                return $row;
            }

            $recordHariIni = ($fingerHariIni->get($kode) ?? collect())->first();

            if ($row['shift'] === 'malam') {
                // jam_masuk_finger: jam_pulang dari record hari ini
                $row['jam_masuk_finger'] = $recordHariIni?->jam_pulang;

                // jam_pulang_finger: jam_masuk dari record besok
                $recordBesok = ($fingerBesok->get($kode) ?? collect())->first();
                $row['jam_pulang_finger'] = $recordBesok?->jam_masuk;
            } else {
                $row['jam_masuk_finger'] = $recordHariIni?->jam_masuk;
                $row['jam_pulang_finger'] = $recordHariIni?->jam_pulang;
            }

            return $row;
        });
    }

    public function availableSources(): Collection
    {
        return collect($this->sources)->map(fn ($s) => [
            'key' => $s->key(),
            'label' => $s->label(),
        ]);
    }

    public function getAbsensiLainLain(string $tanggal): Collection
    {
        $rekap = $this->getRekap($tanggal);

        // Kumpulkan semua kode_pegawai yang SUDAH tercatat di produksi hari ini
        $kodeSudahAdaProduksi = $rekap->pluck('kode_pegawai')->filter()->unique();

        // Ambil semua data finger hari ini
        $semuaFinger = DetailAbsensi::query()
            ->whereDate('tanggal', $tanggal)
            ->get();

        // Filter yang kode_pegawai-nya TIDAK ada di rekap produksi
        $fingerTanpaProduksi = $semuaFinger->filter(
            fn ($item) => ! $kodeSudahAdaProduksi->contains($item->kode_pegawai)
        );

        if ($fingerTanpaProduksi->isEmpty()) {
            return collect();
        }

        // Ambil data pegawai buat nama
        $kodeList = $fingerTanpaProduksi->pluck('kode_pegawai')->unique();

        $pegawaiByKode = Pegawai::query()
            ->whereIn('kode_pegawai', $kodeList)
            ->get()
            ->keyBy('kode_pegawai');

        return $fingerTanpaProduksi->map(function ($item) use ($pegawaiByKode) {
            $pegawai = $pegawaiByKode->get($item->kode_pegawai);

            return [
                'kode_pegawai' => $item->kode_pegawai,
                'nama_pegawai' => $pegawai?->nama_pegawai ?? "Kode: {$item->kode_pegawai} (tidak ditemukan)",
                'jam_masuk' => $item->jam_masuk,
                'jam_pulang' => $item->jam_pulang,
                'tanggal' => $item->tanggal,
            ];
        })->sortBy('nama_pegawai')->values();
    }
}
