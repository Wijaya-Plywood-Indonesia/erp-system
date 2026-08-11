<?php

namespace App\Services;

use App\Models\NewDataFinger;
use App\Models\Pegawai;
use App\Services\AbsensiSources\AbsensiSourceInterface;
use Filament\Facades\Filament;
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
            ->values();

        // Beberapa source (mis. Repair) return jam_masuk/jam_pulang dalam
        // format datetime penuh (Y-m-d H:i:s), sementara source lain sudah
        // H:i:s saja. Normalisasi semua ke H:i:s di sini supaya konsisten
        // sebelum diproses lebih lanjut (gabung sumber, sorting, dst).
        $rekap = $this->normalisasiJam($rekap);

        $rekap = $this->gabungkanMultiSumber($rekap);

        $rekap = $this->enrichWithFinger($rekap, $tanggal);

        return $this->urutkanByGrupKode($rekap);
    }

    /**
     * Normalisasi field jam_masuk & jam_pulang jadi format H:i:s saja,
     * apapun format aslinya dari source (bisa H:i:s murni atau
     * Y-m-d H:i:s / datetime penuh). Pakai Carbon::parse() karena bisa
     * handle kedua format itu sekaligus.
     */
    protected function normalisasiJam(Collection $rekap): Collection
    {
        return $rekap->map(function ($row) {
            foreach (['jam_masuk', 'jam_pulang'] as $field) {
                if (! empty($row[$field])) {
                    try {
                        $row[$field] = Carbon::parse($row[$field])->format('H:i:s');
                    } catch (\Throwable $e) {
                        // Kalau gagal parse (format aneh/tak terduga),
                        // biarkan apa adanya daripada bikin error.
                    }
                }
            }

            return $row;
        });
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

        $kodeSudahAdaProduksi = $rekap->pluck('kode_pegawai')->filter()->unique();

        $kodeShiftMalamKemarin = $this->getKodePegawaiShiftMalam(
            Carbon::parse($tanggal)->subDay()->format('Y-m-d')
        );

        $kodeDikecualikan = $kodeSudahAdaProduksi->merge($kodeShiftMalamKemarin)->unique();

        $semuaFinger = NewDataFinger::query()
            ->whereDate('tanggal', $tanggal)
            ->get();

        $fingerTanpaProduksi = $semuaFinger->filter(
            fn ($item) => ! $kodeDikecualikan->contains($item->kode_pegawai)
        );

        if ($fingerTanpaProduksi->isEmpty()) {
            return collect();
        }

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

    protected function gabungkanMultiSumber(Collection $rekap): Collection
    {
        return $rekap
            ->groupBy(fn ($row) => $row['id_pegawai'] ?? $row['nama_pegawai'])
            ->map(function ($rows) {
                $pertama = $rows->first();

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

    /**
     * Urutkan hasil rekap. Untuk brand WAHANA, pakai grup prioritas kode:
     *   1. Kode 8000-8999 (nama_pegawai A-Z di dalam grup)
     *   2. Kode 9000-9999 (nama_pegawai A-Z di dalam grup)
     *   3. Kode 7000-7999 (nama_pegawai A-Z di dalam grup)
     *   4. Sisanya (0-6999 dan kode di luar rentang manapun) — diurutkan
     *      kode_pegawai ASCENDING (kecil ke besar), BUKAN nama_pegawai.
     * Untuk brand LAIN (Wijaya, dst), urutan balik ke simpel: kode_pegawai
     * ascending seperti biasa.
     */
    protected function urutkanByGrupKode(Collection $rekap): Collection
    {
        if (! $this->isBrandWahana()) {
            return $rekap
                ->sortBy(function ($row) {
                    $kode = $row['kode_pegawai'] ?? null;

                    return $kode && is_numeric($kode) ? (int) $kode : PHP_INT_MAX;
                })
                ->values();
        }

        // Hitung dulu nomor grup + kunci urutan kedua sebagai field biasa
        // di tiap row, baru sortBy pakai nama field. Ini supaya sortBy
        // multi-kolom Laravel bisa membandingkan lewat data_get() secara
        // langsung, alih-alih lewat closure kustom yang gampang salah pakai.
        //
        // Kunci urutan kedua beda tergantung grup:
        //   - Grup 8000/9000/7000 (grup 0,1,2): diurutkan by nama_pegawai
        //     supaya enak dibaca di dalam grup yang sama.
        //   - Grup default/sisanya (grup 3): diurutkan by kode_pegawai
        //     ascending (kecil ke besar), BUKAN by nama.
        $rekap = $rekap->map(function ($row) {
            $grup = $this->grupKode($row['kode_pegawai'] ?? null);
            $row['_grup_urutan'] = $grup;

            if ($grup === 3) {
                $kode = $row['kode_pegawai'] ?? null;
                $row['_sort_kedua'] = ($kode && is_numeric($kode))
                    ? str_pad((string) (int) $kode, 10, '0', STR_PAD_LEFT)
                    : str_repeat('9', 10); // kode kosong/invalid ditaruh paling belakang dalam grupnya
            } else {
                $row['_sort_kedua'] = $row['nama_pegawai'] ?? '';
            }

            return $row;
        });

        return $rekap
            ->sortBy(['_grup_urutan', '_sort_kedua'])
            ->values()
            ->map(function ($row) {
                // Field internal, gak perlu ikut ke view
                unset($row['_grup_urutan'], $row['_sort_kedua']);

                return $row;
            });
    }

    protected function isBrandWahana(): bool
    {
        $panel = Filament::getCurrentPanel()
            ?? Filament::getPanel('admin');

        return $panel?->getBrandName() === 'Wahana';
    }

    protected function grupKode(?string $kodePegawai): int
    {
        if (! $kodePegawai || ! is_numeric($kodePegawai)) {
            return 4; // kode kosong/tidak valid ditaruh paling belakang
        }

        $kode = (int) $kodePegawai;

        return match (true) {
            $kode >= 8000 && $kode <= 8999 => 0,
            $kode >= 9000 && $kode <= 9999 => 1,
            $kode >= 7000 && $kode <= 7999 => 2,
            default => 3, // sisanya: 0-6999 dan kode di luar rentang manapun
        };
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
