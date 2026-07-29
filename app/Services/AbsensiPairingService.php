<?php

namespace App\Services;

use Carbon\Carbon;

class AbsensiPairingService
{
    /**
     * === KENAPA KELAS INI DITULIS ULANG TOTAL ===
     *
     * Versi lama menentukan jam masuk/pulang dari POSISI scan dalam array
     * (scan paling awal / paling akhir dalam satu window 3 hari). Itu terbukti
     * salah di data produksi: window 3 hari sering berisi DUA sesi kerja
     * sekaligus (sisa pulang shift kemarin + masuk & pulang shift hari ini),
     * jadi "paling awal" dan "paling akhir" bisa saja diambil dari dua sesi
     * yang berbeda -> durasi kerja meleset ~24 jam (contoh nyata: kode 7302,
     * hasil lama 38 jam padahal seharusnya ~14 jam 47 menit).
     *
     * Solusi di versi ini: JANGAN tebak dari urutan. Klasifikasikan tiap scan
     * berdasarkan JAM dan TANGGAL-nya, lalu cocokkan langsung ke definisi
     * shift untuk tanggal target (targetDate) itu sendiri. Scan yang bukan
     * milik tanggal target (baik dari kemarin maupun besok) sengaja diabaikan
     * di sini -- karena scan itu adalah "jatah" batch tanggal lain, sudah/akan
     * diproses sendiri saat batch tanggal itu diproses.
     */

    // Jam paling awal yang dianggap "masuk shift MALAM". Sebelum jam ini
    // (misal siang hari) scan dianggap bukan masuk shift malam.
    protected const MALAM_MASUK_JAM_MULAI = '14:00:00';

    // Batas jam paling siang yang MASIH dianggap "pulang shift MALAM".
    // Setelah jam ini di hari berikutnya, scan dianggap bukan pulang shift
    // malam lagi (kemungkinan sudah aktivitas lain).
    protected const MALAM_PULANG_JAM_BATAS = '11:59:59';

    // Jam paling awal yang dianggap "pulang shift PAGI" pada hari yang sama.
    protected const PAGI_PULANG_JAM_MULAI = '12:00:00';

    /**
     * @param array       $entries     Kumpulan scan (hasil parser) untuk SATU pegawai,
     *                                 boleh berisi campuran tanggal prevDate/targetDate/
     *                                 nextDate -- method ini yang akan memilah sendiri
     *                                 mana yang relevan untuk targetDate.
     * @param string|null $forcedShift 'PAGI' atau 'MALAM' kalau shift pegawai untuk
     *                                 tanggal ini sudah DIKETAHUI dari sumber otoritatif
     *                                 (jadwal produksi / jam_masuk_sistem pegawai). Kalau
     *                                 dikasih, kita TIDAK menebak lagi dari pola scan --
     *                                 langsung dipakai sebagai fakta. Ini jauh lebih aman
     *                                 daripada menebak, karena data scan bisa bolong.
     *                                 Kosongkan (null) hanya kalau memang tidak ada info
     *                                 shift dari sumber manapun untuk pegawai ini.
     */
    public function pairEmployeeLogs(array $entries, string $targetDate, string $nextDate, ?string $forcedShift = null): ?array
    {
        if (empty($entries)) {
            return null;
        }

        // Dedup scan yang jaraknya < 5 menit (kemungkinan scan ganda karena
        // device lag / orang scan ulang), supaya tidak dianggap dua sesi.
        $sorted = collect($entries)->sortBy('full')->values();
        $filtered = [];
        foreach ($sorted as $entry) {
            if (empty($filtered)) {
                $filtered[] = $entry;
                continue;
            }
            $last = end($filtered);
            if ($entry['full']->diffInMinutes($last['full'], true) >= 5) {
                $filtered[] = $entry;
            }
        }

        $forcedShift = $forcedShift ? strtoupper(trim($forcedShift)) : null;

        // ==========================================================
        // JALUR A: shift sudah diketahui dari sumber otoritatif.
        // Tidak perlu tebak-tebakan sama sekali -- ini paling akurat.
        // ==========================================================
        if ($forcedShift === 'MALAM') {
            $scanSoreTargetDate = collect($filtered)
                ->filter(fn($e) => $e['date'] === $targetDate && $e['time'] >= self::MALAM_MASUK_JAM_MULAI)
                ->sortBy('full')
                ->first();

            return $scanSoreTargetDate !== null
                ? $this->pairShiftMalam($filtered, $scanSoreTargetDate, $targetDate, $nextDate)
                : $this->pairShiftMalamMasukHilang($filtered, $targetDate, $nextDate, 'Shift MALAM dipastikan dari data produksi/jadwal pegawai, tapi scan masuk (sore) tidak ditemukan.');
        }

        if ($forcedShift === 'PAGI') {
            $scanPagiTargetDate = collect($filtered)
                ->filter(fn($e) => $e['date'] === $targetDate && $e['time'] < self::MALAM_MASUK_JAM_MULAI)
                ->sortBy('full')
                ->first();

            return $scanPagiTargetDate !== null
                ? $this->pairShiftPagi($filtered, $scanPagiTargetDate, $targetDate)
                : null; // Dijadwalkan PAGI tapi tidak ada scan sama sekali di targetDate -> memang tidak masuk kerja / belum tercatat.
        }

        // ==========================================================
        // JALUR B: fallback -- tidak ada info shift dari sumber luar,
        // baru di sini kita menebak dari pola jam scan.
        // ==========================================================

        // Langkah 1: apakah pegawai ini punya scan SORE/MALAM pada targetDate
        // itu sendiri? Kalau ya, ini pegawai shift MALAM untuk tanggal ini.
        $scanSoreTargetDate = collect($filtered)
            ->filter(fn($e) => $e['date'] === $targetDate && $e['time'] >= self::MALAM_MASUK_JAM_MULAI)
            ->sortBy('full')
            ->first();

        if ($scanSoreTargetDate !== null) {
            return $this->pairShiftMalam($filtered, $scanSoreTargetDate, $targetDate, $nextDate);
        }

        // Langkah 1b: tidak ada scan sore di targetDate BUKAN berarti
        // otomatis pegawai ini PAGI -- bisa juga MALAM yang lupa scan sore
        // hari ini. Cek apakah dia punya scan sore di hari SEBELUMNYA
        // sebagai bukti pendukung.
        $prevDate = Carbon::parse($targetDate)->subDay()->format('Y-m-d');
        $scanSorePrevDate = collect($filtered)
            ->first(fn($e) => $e['date'] === $prevDate && $e['time'] >= self::MALAM_MASUK_JAM_MULAI);

        if ($scanSorePrevDate !== null) {
            return $this->pairShiftMalamMasukHilang($filtered, $targetDate, $nextDate, 'Shift MALAM: scan masuk (sore) tidak ditemukan pada tanggal ini, padahal pegawai tercatat shift malam pada hari sebelumnya. Kemungkinan lupa scan atau device error -- perlu dicek manual.');
        }

        // Langkah 2: bukan MALAM -> cek apakah ada scan PAGI pada targetDate.
        $scanPagiTargetDate = collect($filtered)
            ->filter(fn($e) => $e['date'] === $targetDate && $e['time'] < self::MALAM_MASUK_JAM_MULAI)
            ->sortBy('full')
            ->first();

        if ($scanPagiTargetDate !== null) {
            return $this->pairShiftPagi($filtered, $scanPagiTargetDate, $targetDate);
        }

        // Tidak ada scan relevan sama sekali untuk targetDate.
        return null;
    }

    /**
     * Kasus khusus: pegawai terbukti tipe MALAM (ada scan sore di hari
     * sebelumnya), tapi scan sore/masuk untuk targetDate tidak ada. Kita
     * tetap coba cari kemungkinan scan pulang di nextDate sekadar untuk info
     * -- tapi TIDAK dipakai menghitung durasi, karena tanpa jam masuk yang
     * valid, durasi apa pun yang kita hitung bisa menyesatkan (sama seperti
     * bug awal yang kita perbaiki).
     */
    protected function pairShiftMalamMasukHilang(array $filtered, string $targetDate, string $nextDate, string $catatan): array
    {
        $kemungkinanPulang = collect($filtered)
            ->filter(fn($e) => $e['date'] === $nextDate && $e['time'] <= self::MALAM_PULANG_JAM_BATAS)
            ->sortBy('full')
            ->last();

        return [
            'jam_masuk'  => null,
            'jam_pulang' => $kemungkinanPulang['time'] ?? null,
            'shift'      => 'MALAM',
            'status'     => 'tidak_lengkap',
            'catatan'    => $catatan,
        ];
    }

    /**
     * Pasangkan masuk (sudah ditemukan di targetDate sore) dengan pulang
     * (dicari di nextDate, jam pagi s/d batas siang).
     */
    protected function pairShiftMalam(array $filtered, array $jamMasukEntry, string $targetDate, string $nextDate): array
    {
        // Kalau ada lebih dari satu scan sore di targetDate (jarang, tapi
        // bisa terjadi), yang dipakai sudah otomatis yang PALING AWAL karena
        // firstWhere di atas mengurutkan dulu -- itu asumsi paling wajar
        // untuk "jam datang".
        $jamPulangEntry = collect($filtered)
            ->filter(fn($e) => $e['date'] === $nextDate && $e['time'] <= self::MALAM_PULANG_JAM_BATAS)
            ->sortBy('full')
            ->last(); // ambil yang PALING AKHIR -> scan terakhir sebelum benar-benar pulang

        if ($jamPulangEntry === null) {
            return [
                'jam_masuk'  => $jamMasukEntry['time'],
                'jam_pulang' => null,
                'shift'      => 'MALAM',
                'status'     => 'tidak_lengkap',
                'catatan'    => 'Shift MALAM: scan pulang tidak ditemukan pada tanggal berikutnya.',
            ];
        }

        $durasiJam = $jamMasukEntry['full']->diffInMinutes($jamPulangEntry['full'], true) / 60;

        return [
            'jam_masuk'  => $jamMasukEntry['time'],
            'jam_pulang' => $jamPulangEntry['time'],
            'shift'      => 'MALAM',
            'status'     => $durasiJam > 18 ? 'perlu_review' : 'normal',
            'catatan'    => $durasiJam > 18 ? "Durasi kerja {$durasiJam} jam, melebihi batas wajar." : null,
        ];
    }

    /**
     * Pasangkan masuk (sudah ditemukan di targetDate pagi) dengan pulang
     * (dicari di targetDate JUGA, tapi di jam siang/sore -- shift PAGI tidak
     * menyeberang hari).
     */
    protected function pairShiftPagi(array $filtered, array $jamMasukEntry, string $targetDate): array
    {
        $jamPulangEntry = collect($filtered)
            ->filter(fn($e) => $e['date'] === $targetDate && $e['time'] >= self::PAGI_PULANG_JAM_MULAI)
            ->sortBy('full')
            ->last();

        if ($jamPulangEntry === null) {
            return [
                'jam_masuk'  => $jamMasukEntry['time'],
                'jam_pulang' => null,
                'shift'      => 'PAGI',
                'status'     => 'tidak_lengkap',
                // Sesuai konfirmasi: pegawai PAGI SEHARUSNYA scan pulang juga,
                // jadi ini benar-benar dianggap data hilang, bukan kondisi normal.
                'catatan'    => 'Shift PAGI: scan pulang tidak ditemukan pada tanggal yang sama. Perlu dicek manual (kemungkinan lupa scan atau device error).',
            ];
        }

        $durasiJam = $jamMasukEntry['full']->diffInMinutes($jamPulangEntry['full'], true) / 60;

        return [
            'jam_masuk'  => $jamMasukEntry['time'],
            'jam_pulang' => $jamPulangEntry['time'],
            'shift'      => 'PAGI',
            'status'     => $durasiJam > 14 ? 'perlu_review' : 'normal',
            'catatan'    => $durasiJam > 14 ? "Durasi kerja {$durasiJam} jam, melebihi batas wajar." : null,
        ];
    }
}
