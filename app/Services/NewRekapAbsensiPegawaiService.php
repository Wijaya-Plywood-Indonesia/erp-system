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
    /**
     * Jadwal standar shift pagi, dipakai sebagai FALLBACK acuan di
     * resolveJamFingerNonMalam() kalau jam_masuk/jam_pulang produksi
     * kosong (row hasil lengkapiSemuaPegawai(), atau source yang gak
     * ngasih jam kerja). Supaya grouping raw finger tetap bisa nebak
     * "lebih deket ke masuk atau pulang" walau gak ada data produksi
     * sama sekali, bukan cuma nyerah balik ke perilaku lama.
     */
    protected const JAM_MASUK_SHIFT_PAGI_DEFAULT = '08:00:00';

    protected const JAM_PULANG_SHIFT_PAGI_DEFAULT = '16:00:00';

    protected const TOLERANSI_SESI_TUNGGAL_MENIT = 15;

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

        $rekap = $this->lengkapiSemuaPegawai($rekap);

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

    /**
     * Pastikan SEMUA pegawai dari tabel `pegawais` muncul di rekap, bukan
     * cuma yang kebetulan ke-fetch dari source hari itu. Pegawai yang tidak
     * punya data dari source manapun akan ditambahkan sebagai row kosong
     * (jam_masuk, jam_pulang, shift = null) supaya tetap kelihatan di
     * laporan sebagai "tidak ada data" pada tanggal tersebut.
     *
     * Ditaruh SETELAH gabungkanMultiSumber (supaya key id_pegawai yang
     * dipakai untuk dedupe sudah bersih) dan SEBELUM enrichWithFinger
     * (supaya pegawai yang row-nya baru ditambahkan di sini tetap bisa
     * dapat jam_masuk_finger/jam_pulang_finger kalau ternyata dia ada
     * scan finger walau tidak ke-fetch dari source manapun).
     */
    protected function lengkapiSemuaPegawai(Collection $rekap): Collection
    {
        $idPegawaiSudahAda = $rekap
            ->pluck('id_pegawai')
            ->filter()
            ->unique();

        $pegawaiBelumAda = Pegawai::query()
            ->whereNotIn('id', $idPegawaiSudahAda)
            ->get(['id', 'kode_pegawai', 'nama_pegawai']);

        if ($pegawaiBelumAda->isEmpty()) {
            return $rekap;
        }

        $rowKosong = $pegawaiBelumAda->map(fn ($pegawai) => [
            'id_pegawai' => $pegawai->id,
            'kode_pegawai' => $pegawai->kode_pegawai,
            'nama_pegawai' => $pegawai->nama_pegawai,
            'shift' => null,
            'jam_masuk' => null,
            'jam_pulang' => null,
            'sumber_label' => [],
        ]);

        return $rekap->concat($rowKosong)->values();
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
                // Mesin finger nyatet berdasarkan tanggal kalender scan
                // terjadi, bukan berdasarkan sesi shift. Scan malam hari H
                // (jam masuk kerja) tercatat sebagai jam_pulang device di
                // tanggal H (karena itu scan terakhir hari itu). Scan subuh
                // H+1 (jam pulang kerja) tercatat sebagai jam_masuk device
                // di tanggal H+1 (karena itu scan pertama hari itu).
                // JANGAN disederhanakan jadi jam_masuk -> jam_masuk_finger.
                $row['jam_masuk_finger'] = $recordHariIni?->jam_pulang;

                $recordBesok = $fingerBesok->get($kode);
                $row['jam_pulang_finger'] = $recordBesok?->jam_masuk;
            } else {
                [$row['jam_masuk_finger'], $row['jam_pulang_finger']] = $this->resolveJamFingerNonMalam(
                    $recordHariIni?->jam_masuk,
                    $recordHariIni?->jam_pulang,
                    $row['jam_masuk'] ?? null,
                    $row['jam_pulang'] ?? null
                );
            }

            return $row;
        });
    }

    /**
     * Khusus shift NON-malam. Kalau finger cuma di-upload untuk satu sesi
     * scan (mis. upload pagi doang), raw jam_masuk & jam_pulang dari mesin
     * finger jadi hampir sama persis (selisih beberapa detik), karena
     * dua-duanya diambil dari scan yang sama. Kalau dibiarkan apa adanya,
     * jam_masuk_finger & jam_pulang_finger jadi kembar padahal cuma 1 tap.
     *
     * Fix: kalau selisih raw jam_masuk & jam_pulang finger <= toleransi
     * (indikasi 1 sesi scan aja), itu SUDAH PASTI 1 sesi scan — sisanya
     * cuma soal menentukan taruh di kolom jam_masuk_finger atau
     * jam_pulang_finger (gak dua-duanya). Penentuan arah dilakukan dengan
     * membandingkan scan itu ke jam_masuk/jam_pulang PRODUKSI (dari $row,
     * hasil getRekap sebelum di-enrich) — assign ke yang JARAKNYA PALING
     * DEKAT (relatif, siapa pun yang menang), BUKAN dengan syarat jarak
     * itu harus di dalam toleransi. Toleransi 15 menit HANYA dipakai di
     * langkah pertama untuk mendeteksi "1 sesi vs 2 sesi", bukan untuk
     * memutuskan boleh/tidaknya dedupe di langkah ini.
     *
     * Kalau jam_masuk/jam_pulang produksi kosong (row hasil
     * lengkapiSemuaPegawai(), atau source yang gak ngasih jam kerja),
     * TETAP dicoba di-grouping — pakai jadwal standar shift pagi
     * (JAM_MASUK_SHIFT_PAGI_DEFAULT / JAM_PULANG_SHIFT_PAGI_DEFAULT)
     * sebagai acuan pengganti, bukan langsung nyerah ke perilaku lama.
     *
     * Fallback ke perilaku lama (pasang jam_masuk_finger & jam_pulang_finger
     * apa adanya dari record finger) HANYA kalau: raw masuk/pulang finger
     * beneran berjauhan (bukan 1 sesi), atau parsing gagal. Begitu terbukti
     * 1 sesi, TIDAK ADA fallback lain — harus tetap didedupe ke salah satu
     * kolom, seberapa pun jauhnya jam tap dari kedua acuan.
     *
     * @return array{0: ?string, 1: ?string} [jam_masuk_finger, jam_pulang_finger]
     */
    protected function resolveJamFingerNonMalam(
        ?string $rawMasuk,
        ?string $rawPulang,
        ?string $jamMasukProduksi,
        ?string $jamPulangProduksi
    ): array {
        // Kalau salah satu raw kosong, gak ada apa-apa buat dibandingkan —
        // pasang apa adanya seperti perilaku lama.
        if (! $rawMasuk || ! $rawPulang) {
            return [$rawMasuk, $rawPulang];
        }

        try {
            $tRawMasuk = Carbon::parse($rawMasuk);
            $tRawPulang = Carbon::parse($rawPulang);
        } catch (\Throwable $e) {
            return [$rawMasuk, $rawPulang];
        }

        // Raw masuk & pulang finger berjauhan (> toleransi) -> memang 2 sesi
        // scan beneran (masuk pagi, pulang sore/malam). Biarkan seperti biasa.
        if ($tRawMasuk->diffInMinutes($tRawPulang) > self::TOLERANSI_SESI_TUNGGAL_MENIT) {
            return [$rawMasuk, $rawPulang];
        }

        // Sampai sini berarti raw masuk & pulang SUDAH PASTI 1 sesi scan.
        // Produksi kosong (mis. row hasil lengkapiSemuaPegawai(), atau
        // source yang gak ngasih jam kerja) -> tetap coba grouping, tapi
        // pakai jadwal standar shift pagi sebagai acuan, bukan nyerah
        // balikin raw apa adanya.
        $jamMasukProduksi ??= self::JAM_MASUK_SHIFT_PAGI_DEFAULT;
        $jamPulangProduksi ??= self::JAM_PULANG_SHIFT_PAGI_DEFAULT;

        // Representasi waktu tunggal dari sesi scan ini (dua-duanya udah
        // deket, pakai raw masuk sebagai acuan).
        $tScan = $tRawMasuk;

        try {
            $diffKeMasuk = $tScan->diffInMinutes(Carbon::parse($jamMasukProduksi));
            $diffKePulang = $tScan->diffInMinutes(Carbon::parse($jamPulangProduksi));
        } catch (\Throwable $e) {
            // Gagal parse acuan -> tetap 1 sesi, tapi gak ada petunjuk arah.
            // Fallback aman: taruh di jam_masuk (asumsi tap tunggal = masuk).
            return [$rawMasuk, null];
        }

        // TIDAK ADA fallback "di luar toleransi kedua acuan -> kembalikan
        // raw apa adanya" di sini. Karena udah terbukti 1 sesi scan (lolos
        // pengecekan di atas), harus tetap didedupe ke salah satu kolom —
        // assign ke acuan yang jaraknya PALING DEKAT (relatif), seberapa
        // pun jauhnya jarak itu dari kedua acuan.
        return $diffKeMasuk <= $diffKePulang
            ? [$rawMasuk, null]
            : [null, $rawPulang];
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

        // Pegawai shift malam yang scan-nya "nyangkut" ke tanggal besok
        // (lihat catatan di enrichWithFinger) akan muncul lagi di data
        // mesin finger hari ini (sisa scan subuhnya), padahal dia sudah
        // sah tercatat shift malam KEMARIN. Tanpa exclusion ini, dia akan
        // salah kedeteksi sebagai anomali "absen finger tapi gak ada di
        // rekap produksi". JANGAN dihapus.
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
