<?php

namespace App\Services;

use App\Models\NewDataFinger;
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

        $rekap = $this->gabungkanMultiSumber($rekap);

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

        // Data finger sudah 1 row per (kode_pegawai, tanggal), hasil agregat
        // MIN/MAX dari proses upload. Cukup keyBy kode_pegawai, tanpa perlu
        // groupBy + first() lagi seperti versi lama.
        $fingerHariIni = NewDataFinger::query()
            ->whereDate('tanggal', $tanggal)
            ->whereIn('kode_pegawai', $kodePegawaiList)
            ->get()
            ->keyBy('kode_pegawai');

        $fingerBesok = NewDataFinger::query()
            ->whereDate('tanggal', $tanggalBerikutnya)
            ->whereIn('kode_pegawai', $kodePegawaiList)
            ->get()
            ->keyBy('kode_pegawai');

        return $rekap->map(function ($row) use ($kodeByIdPegawai, $fingerHariIni, $fingerBesok) {
            $kode = $kodeByIdPegawai->get($row['id_pegawai']);
            $row['kode_pegawai'] = $kode;

            if (! $kode) {
                $row['jam_masuk_finger'] = null;
                $row['jam_pulang_finger'] = null;

                return $row;
            }

            $recordHariIni = $fingerHariIni->get($kode);

            if ($row['shift'] === 'malam') {
                // Shift malam: masuk hari ini, pulang besok pagi.
                // jam_masuk_finger diambil dari jam_pulang record hari ini
                // (karena checklog masuk malam biasanya belum ke-tap sebagai
                // "masuk" murni oleh mesin, ikutin logic lama).
                $row['jam_masuk_finger'] = $recordHariIni?->jam_pulang;

                $recordBesok = $fingerBesok->get($kode);
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

        // Cegah "absen bocor": pegawai yang shift MALAM kemarin, checkout-nya
        // kecatat di tanggal hari ini (karena shift malam nyebrang hari).
        // Row finger hari ini punya kode_pegawai yang sama, tapi itu cuma
        // ekor checkout shift kemarin, BUKAN checklog baru hari ini.
        // Jadi mereka wajib dikecualikan dari daftar "Lain-lain" hari ini.
        $kodeShiftMalamKemarin = $this->getKodePegawaiShiftMalam(
            Carbon::parse($tanggal)->subDay()->format('Y-m-d')
        );

        $kodeDikecualikan = $kodeSudahAdaProduksi->merge($kodeShiftMalamKemarin)->unique();

        // Ambil semua data finger hari ini
        $semuaFinger = NewDataFinger::query()
            ->whereDate('tanggal', $tanggal)
            ->get();

        // Filter yang kode_pegawai-nya TIDAK ada di rekap produksi
        // MAUPUN bukan ekor checkout shift malam kemarin.
        $fingerTanpaProduksi = $semuaFinger->filter(
            fn ($item) => ! $kodeDikecualikan->contains($item->kode_pegawai)
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

    /**
     * Kalau 1 pegawai muncul di lebih dari 1 sumber produksi pada tanggal
     * yang sama (misal kerja di Kedi & Repair sekaligus), gabung jadi 1
     * row saja. Kolom "sumber" jadi array supaya bisa ditampilkan sebagai
     * beberapa badge dalam 1 baris (bukan baris terpisah).
     */
    protected function gabungkanMultiSumber(Collection $rekap): Collection
    {
        return $rekap
            ->groupBy(fn ($row) => $row['id_pegawai'] ?? $row['nama_pegawai'])
            ->map(function ($rows) {
                $pertama = $rows->first();

                // Kumpulkan semua label sumber unik dari pegawai ini,
                // pertahankan urutan kemunculan pertama kali.
                $pertama['sumber_label'] = $rows
                    ->pluck('sumber_label')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return $pertama;
            })
            ->values();
    }

    protected function getKodePegawaiShiftMalam(string $tanggal): Collection
    {
        $rekap = collect($this->sources)
            ->flatMap(fn ($source) => $source->fetch($tanggal))
            ->values();

        if ($rekap->isEmpty()) {
            return collect();
        }

        $idPegawaiShiftMalam = $rekap
            ->where('shift', 'malam')
            ->pluck('id_pegawai')
            ->filter()
            ->unique();

        if ($idPegawaiShiftMalam->isEmpty()) {
            return collect();
        }

        return Pegawai::query()
            ->whereIn('id', $idPegawaiShiftMalam)
            ->pluck('kode_pegawai');
    }
}
