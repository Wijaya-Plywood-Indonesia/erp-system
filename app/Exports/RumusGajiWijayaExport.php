<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export format "Rumus Gaji Wijaya".
 *
 * SUMBER DATA: $rekap di sini adalah hasil
 * NewRekapAbsensiPegawaiService::getRekap($tanggal) — SAMA PERSIS dengan
 * yang dipakai NewRekapAbsensiExport. Field yang tersedia per baris:
 * kode_pegawai, nama_pegawai, jam_masuk (jadwal produksi), jam_pulang
 * (jadwal produksi), jam_masuk_finger (scan asli), jam_pulang_finger
 * (scan asli), shift, sumber_label (array divisi/mesin), izin,
 * keterangan.
 *
 * REVISI TERBARU (dibanding revisi sebelumnya):
 *   - FIX BUG "24 JAM KERJA UNTUK PEGAWAI TIDAK MASUK": sebelumnya kalau
 *     Jam Hasil Masuk == Jam Hasil Pulang (mis. keduanya kosong/00:00:00
 *     karena jadwal shift tidak valid untuk pegawai yang izin/alpha),
 *     hitungJamKerja() menganggapnya lintas-tengah-malam dan menghasilkan
 *     24 jam. Sekarang ditambahkan pengecekan: kalau total detik Jam
 *     Hasil Masuk + Jam Hasil Pulang == 0 (mis. 00:00:00 & 00:00:00),
 *     Jam Kerja langsung dianggap 0 — lihat hitungJamKerja() &
 *     jamKeDetik().
 *   - FIX BUG "NILAI 0 TIDAK TAMPIL DI EXCEL": sebelumnya kolom Lembur2
 *     pakai `$lembur > 0 ? number_format(...) : ''` — artinya kalau
 *     Lembur2 = 0, cell-nya jadi string kosong (bukan '0,00'), sehingga
 *     terlihat seperti "hilang" di Excel. Sekarang SELALU
 *     number_format() apa pun nilainya (termasuk 0), jadi kolom Lembur2
 *     selalu tampil angka, misalnya '0,00'. Kolom Jam Kerja juga
 *     dipastikan selalu mengirim nilai int (termasuk 0) ke map(), bukan
 *     null/'' — supaya PhpSpreadsheet menulis 0 sebagai angka, bukan
 *     cell kosong.
 *
 * REVISI SEBELUMNYA (dibandingkan langsung dengan sheet harian "sabtu/
 * minggu/senin/dst" di file RUMUS_GAJI_WIJAYA_*.xlsx asli) — dibuat
 * lebih mirip di sisi WARNA dan RUMUS/BEHAVIOR:
 *
 * WARNA
 *   - Header: fill biru muda "Blue, Accent 1, Lighter 40%" (#BDD7EE),
 *     font hitam bold, rata tengah — bukan abu-abu gelap #333333 seperti
 *     sebelumnya.
 *   - Fill biru sangat muda "Blue, Accent 1, Lighter 80%" (#DDEBF7)
 *     TIDAK merata di semua kolom. Di file asli hanya kolom C, D, G, H,
 *     I, J, L, M, N yang kena fill biru; kolom A, B, E, F, K, O, P
 *     dibiarkan putih/kosong. Tidak ada highlight kuning khusus untuk
 *     baris "tidak" pada kolom Perbandingan, jadi highlight kuning yang
 *     lama dihapus supaya tidak menyesatkan.
 *   - Lebar kolom pakai nilai TETAP (WithColumnWidths), bukan auto-size
 *     lagi — supaya lebar kolom konsisten setiap export dan tidak
 *     berubah-ubah mengikuti panjang isi data terpanjang.
 *
 * RUMUS / BEHAVIOR
 *   - JAM HASIL (G/H) = JADWAL SHIFT PRODUKSI, SELALU: Jam Hasil
 *     Masuk/Pulang (kolom G/H) diambil LANGSUNG dari jadwal shift
 *     produksi (jam_masuk/jam_pulang sistem — field yang sama dengan
 *     kolom "Sistem Masuk/Pulang" di NewRekapAbsensiExport), TIDAK
 *     PERNAH dari finger — lihat resolveJamHasil(). Ini berlaku selalu,
 *     bukan cuma fallback saat finger kosong. Kolom "Jam Masuk"/"Jam
 *     Pulang" (C/D, dari finger asli) dan "Jam Bulat Masuk/Pulang" (E/F,
 *     hasil ceil/floor dari finger) TETAP murni dari
 *     jam_masuk_finger/jam_pulang_finger seperti sebelumnya — tidak
 *     terpengaruh perubahan ini.
 *   - Format angka jam: "Jam Masuk/Pulang" (kolom C/D), "Jam Hasil
 *     Masuk" (G), dan "Jam Hasil Pulang" (H) SAMA-SAMA pakai format
 *     "h:mm:ss" (numFmtId 170). "Jam Bulat Masuk/Pulang" (E/F) pakai
 *     format elapsed "[h]:mm:ss" (numFmtId 171).
 *   - "Jam Kerja" (kolom O) dihitung dari JAM HASIL Masuk/Pulang (bukan
 *     dari jam jadwal), dengan logika lintas-tengah-malam sama seperti
 *     rumus asli:
 *       IF(hasilMasuk < hasilPulang, selisih*24,
 *         IF(hasilMasuk<>0, (selisih+1)*24, 0))
 *     DENGAN TAMBAHAN GUARD BARU: kalau total detik hasilMasuk +
 *     hasilPulang == 0, langsung 0 (lihat catatan revisi terbaru di
 *     atas).
 *   - "Perbandingan" (kolom P) di file asli membandingkan selisih jam
 *     BULAT (F-E) dengan selisih jam HASIL (H-G): sama -> "ya", beda ->
 *     "tidak".
 *   - "Lembur2" (kolom K) di file asli TIDAK flat (jam kerja - 10),
 *     tapi tergantung jam masuk & jenis divisi (kolom Q di sheet
 *     Master, mis. "pabrik"/"jeruk"/"kantor"/"ruko"):
 *       - jam masuk >= 16:00                                -> standar 13 jam
 *       - selain itu & bukan "jeruk"/"kantor"                -> standar 10 jam
 *       - selain itu & "jeruk"                               -> standar 9 jam
 *       - selain itu & "kantor"                              -> standar 8 jam
 *       - selain itu & "ruko"                                -> standar 9 jam
 *     Lembur2 = MAX(0, jam kerja - standar).
 *     ASUMSI: karena getRekap() belum mengirim kode divisi Master!Q,
 *     dipakai heuristik dari field 'shift'/'sumber_label' (lihat
 *     resolveDivisi()). Ini PERLU DIKONFIRMASI — kalau service sudah
 *     bisa kirim kode divisi asli, ganti resolveDivisi() supaya baca
 *     field itu langsung.
 *
 * Field "Potongan" dan "Anak Baru(a)" MASIH PLACEHOLDER kosong (lihat
 * docblock versi sebelumnya untuk detail penelusuran ke file asli).
 *
 * Soft transition: class ini TERPISAH dari NewRekapAbsensiExport, dipakai
 * berdampingan lewat tombol/route sendiri.
 */
class RumusGajiWijayaExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * Jam masuk (jam hasil masuk) mulai dianggap "shift malam" dan
     * memakai standar jam kerja 13 jam sebelum dihitung lembur.
     * Sama dengan Master!$R$4 di file Excel asli (16:00:00).
     */
    protected const AMBANG_SHIFT_MALAM = '16:00:00';

    /**
     * Standar jam kerja per divisi (di luar shift malam). Sama dengan
     * percabangan IF di rumus Lembur2 kolom K file Excel asli.
     */
    protected const STANDAR_JAM_KERJA_DEFAULT = 10;

    protected const STANDAR_JAM_KERJA_PER_DIVISI = [
        'jeruk' => 9,
        'kantor' => 8,
        'ruko' => 9,
    ];

    /**
     * Lebar kolom tetap (dalam satuan karakter, sama seperti kolom
     * Excel biasa). Menggantikan ShouldAutoSize supaya lebar kolom
     * konsisten di setiap export, tidak berubah mengikuti isi data.
     * Sesuaikan angka di sini kalau ada kolom yang masih kepotong
     * atau kelebaran.
     */
    protected const COLUMN_WIDTHS = [
        'A' => 10, // Kodep
        'B' => 22, // Nama Pegawai
        'C' => 11, // Jam Masuk
        'D' => 11, // Jam Pulang
        'E' => 11, // Jam Bulat Masuk
        'F' => 11, // Jam Bulat Pulang
        'G' => 11, // Jam Hasil Masuk
        'H' => 11, // Jam Hasil Pulang
        'I' => 28, // Hasil
        'J' => 10, // Ijin
        'K' => 10, // Lembur2
        'L' => 12, // Potongan
        'M' => 28, // Ket
        'N' => 14, // Anak Baru(a)
        'O' => 10, // Jam Kerja
        'P' => 12, // Perbandingan
    ];

    protected int $originalPrecision;

    protected int $originalSerializePrecision;

    public function __construct(
        protected Collection $rekap,
        protected string $tanggal,
    ) {
        $this->originalPrecision = (int) ini_get('precision');
        $this->originalSerializePrecision = (int) ini_get('serialize_precision');

        ini_set('precision', 16);
        ini_set('serialize_precision', -1);
    }

    public function __destruct()
    {
        ini_set('precision', $this->originalPrecision);
        ini_set('serialize_precision', $this->originalSerializePrecision);
    }

    public function collection(): Collection
    {
        return $this->rekap;
    }

    public function headings(): array
    {
        return [
            'Kodep',
            'Nama Pegawai',
            'Jam Masuk',
            'Jam Pulang',
            'Jam Bulat Masuk',
            'Jam Bulat Pulang',
            'Jam Hasil Masuk',
            'Jam Hasil Pulang',
            'Hasil',
            'Ijin',
            'Lembur2',
            'Potongan',
            'Ket',
            'Anak Baru(a)',
            'Jam Kerja',
            'Perbandingan',
        ];
    }

    /**
     * Lebar kolom tetap, menggantikan ShouldAutoSize.
     */
    public function columnWidths(): array
    {
        return self::COLUMN_WIDTHS;
    }

    /**
     * @param  array  $row  1 baris hasil NewRekapAbsensiPegawaiService::getRekap()
     */
    public function map($row): array
    {
        // Jam Masuk/Pulang (C/D) & Jam Bulat (E/F) TETAP murni dari finger asli
        // (jam_masuk_finger/jam_pulang_finger) — TIDAK pakai fallback jadwal.
        $jamMasukFinger = $row['jam_masuk_finger'] ?? null;
        $jamPulangFinger = $row['jam_pulang_finger'] ?? null;

        $jamBulatMasuk = $this->bulatkanJamMasuk($jamMasukFinger);
        $jamBulatPulang = $this->bulatkanJamPulang($jamPulangFinger);

        // Jam HASIL Masuk/Pulang (G/H) SELALU diambil dari jam shift/
        // jadwal produksi (jam_masuk/jam_pulang sistem). Finger SAMA
        // SEKALI TIDAK dipakai untuk G/H — beda dengan C/D & E/F yang
        // tetap murni dari finger.
        $jamHasilMasuk = $this->resolveJamHasil($row['jam_masuk'] ?? null);
        $jamHasilPulang = $this->resolveJamHasil($row['jam_pulang'] ?? null);

        // $jamKerja & $lembur SELALU int/float (termasuk 0), TIDAK PERNAH
        // null atau string kosong di titik ini — supaya kolom Excel-nya
        // tidak "hilang" saat nilainya 0. Formatting tampilan (mis. jadi
        // string kosong) TIDAK dilakukan di sini lagi, lihat baris return.
        $jamKerja = $this->hitungJamKerja($jamHasilMasuk, $jamHasilPulang);
        $lembur = $this->hitungLembur2($jamKerja, $jamHasilMasuk, $row);

        $perbandingan = $this->tentukanPerbandingan($jamBulatMasuk, $jamBulatPulang, $jamHasilMasuk, $jamHasilPulang);

        return [
            $row['kode_pegawai'] ?? '-',
            $row['nama_pegawai'] ?? '-',
            $this->convertTimeToExcel($jamMasukFinger),
            $this->convertTimeToExcel($jamPulangFinger),
            $this->convertTimeToExcel($jamBulatMasuk),
            $this->convertTimeToExcel($jamBulatPulang),
            $this->convertTimeToExcel($jamHasilMasuk),
            $this->convertTimeToExcel($jamHasilPulang),
            $this->formatDivisi($row),
            $row['izin'] ?? '',
            // FIX: sebelumnya `$lembur > 0 ? number_format(...) : ''`
            // yang membuat nilai 0 tidak tampil di Excel (jadi cell
            // kosong). Sekarang SELALU di-number_format() apa pun
            // nilainya, termasuk 0 -> '0,00'.
            number_format($lembur, 2, ',', ''),
            '', // Potongan — belum ada sumber data, placeholder (lihat docblock)
            $row['keterangan'] ?? '',
            '', // Anak Baru(a) — belum ada sumber data, placeholder (lihat docblock)
            // FIX: cast eksplisit ke int supaya sel selalu berisi angka
            // (termasuk 0), tidak pernah null/''/float aneh.
            (int) $jamKerja,
            $perbandingan,
        ];
    }

    /**
     * Jam Hasil Masuk/Pulang SELALU diambil dari jadwal shift produksi
     * (sistem) — field yang sama dengan kolom "Sistem Masuk/Pulang" di
     * NewRekapAbsensiExport. Finger tidak dipakai sama sekali di sini.
     * Jadwal sistem dipakai apa adanya (tidak dibulatkan), karena dia
     * sudah berupa jam pasti, bukan hasil scan yang perlu dirapikan.
     */
    protected function resolveJamHasil(?string $jadwalSistem): ?string
    {
        if (! empty($jadwalSistem) && $jadwalSistem !== '-' && strlen($jadwalSistem) >= 5) {
            return $jadwalSistem;
        }

        return null;
    }

    /**
     * Bulatkan jam MASUK ke atas (ceil), kecuali menit sudah 00
     * (detik diabaikan) maka tetap di jam itu.
     *
     * Contoh:
     *  07:56:00 -> 08:00:00
     *  08:45:00 -> 09:00:00
     *  08:01:00 -> 09:00:00
     *  08:00:56 -> 08:00:00   (menit = 00, tidak naik)
     *  05:30:00 -> 06:00:00
     */
    protected function bulatkanJamMasuk(?string $time): ?string
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) {
            return null;
        }

        [$h, $m] = $this->pecahJam($time);

        if ($m === 0) {
            return sprintf('%02d:00:00', $h % 24);
        }

        return sprintf('%02d:00:00', ($h + 1) % 24);
    }

    /**
     * Bulatkan jam PULANG ke bawah (floor): menit & detik dibuang,
     * tetap di jam yang sama.
     */
    protected function bulatkanJamPulang(?string $time): ?string
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) {
            return null;
        }

        [$h] = $this->pecahJam($time);

        return sprintf('%02d:00:00', $h % 24);
    }

    /**
     * @return array{0:int,1:int,2:int} [jam, menit, detik]
     */
    protected function pecahJam(string $time): array
    {
        $parts = explode(':', $time);

        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        ];
    }

    /**
     * Konversi string "HH:MM:SS" ke total detik. Dipakai untuk cek
     * apakah Jam Hasil Masuk + Jam Hasil Pulang totalnya 0
     * (mis. 00:00:00 & 00:00:00) supaya tidak salah dihitung sebagai
     * shift lintas-tengah-malam 24 jam.
     */
    protected function jamKeDetik(?string $time): int
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) {
            return 0;
        }

        [$h, $m, $s] = $this->pecahJam($time);

        return ($h * 3600) + ($m * 60) + $s;
    }

    /**
     * Hitung jam kerja dari Jam Hasil Masuk & Pulang. Meniru rumus O di
     * sheet harian asli:
     *   =IF(G<H, (H-G)*24, IF(G<>0, (H-G+1)*24, 0))
     * yaitu: kalau pulang sebelum/sama dengan masuk dianggap lintas
     * tengah malam (tambah 1 hari), dan kalau masuk kosong -> 0.
     *
     * GUARD TAMBAHAN: kalau total detik Jam Hasil Masuk + Jam Hasil
     * Pulang == 0 (mis. keduanya 00:00:00 karena jadwal tidak valid
     * untuk pegawai yang tidak masuk/izin/alpha), langsung return 0.
     * Ini mencegah kasus masuk == pulang == "00:00:00" ke-treat sebagai
     * lintas tengah malam penuh (24 jam).
     */
    protected function hitungJamKerja(?string $jamHasilMasuk, ?string $jamHasilPulang): int
    {
        if (empty($jamHasilMasuk)) {
            return 0;
        }

        if (empty($jamHasilPulang)) {
            return 0;
        }

        if ($this->jamKeDetik($jamHasilMasuk) + $this->jamKeDetik($jamHasilPulang) === 0) {
            return 0;
        }

        try {
            $masuk = Carbon::parse($jamHasilMasuk);
            $pulang = Carbon::parse($jamHasilPulang);
        } catch (\Throwable $e) {
            return 0;
        }

        if ($pulang->lessThanOrEqualTo($masuk)) {
            $pulang->addDay();
        }

        return (int) round($masuk->diffInMinutes($pulang) / 60);
    }

    /**
     * Meniru rumus Lembur2 (kolom K) di sheet harian asli: standar jam
     * kerja tergantung jam masuk (shift malam >= 16:00 -> 13 jam) dan
     * divisi karyawan (jeruk/kantor/ruko punya standar lebih pendek).
     * Lembur2 = MAX(0, jam kerja - standar).
     */
    protected function hitungLembur2(int $jamKerja, ?string $jamHasilMasuk, array $row): float
    {
        if (! empty($jamHasilMasuk) && $jamHasilMasuk >= self::AMBANG_SHIFT_MALAM) {
            $standar = 13;
        } else {
            $divisi = $this->resolveDivisi($row);
            $standar = self::STANDAR_JAM_KERJA_PER_DIVISI[$divisi] ?? self::STANDAR_JAM_KERJA_DEFAULT;
        }

        return max(0, $jamKerja - $standar);
    }

    /**
     * ASUMSI SEMENTARA: getRekap() belum mengirim kode divisi Master!Q
     * ("pabrik"/"jeruk"/"kantor"/"ruko") secara eksplisit, jadi ditebak
     * dari 'shift' atau isi 'sumber_label'. PERLU DIKONFIRMASI dan
     * idealnya diganti membaca field asli begitu service diupdate.
     */
    protected function resolveDivisi(array $row): string
    {
        $kandidat = strtolower((string) ($row['shift'] ?? ''));

        if ($kandidat === '') {
            $kandidat = strtolower(implode(' ', (array) ($row['sumber_label'] ?? [])));
        }

        foreach (array_keys(self::STANDAR_JAM_KERJA_PER_DIVISI) as $divisi) {
            if (str_contains($kandidat, $divisi)) {
                return $divisi;
            }
        }

        return 'pabrik';
    }

    /**
     * Meniru rumus Perbandingan (kolom P) di sheet harian asli:
     *   =IF(((F-E)*24)=((H-G)*24),"ya","tidak")
     * yaitu membandingkan selisih Jam Bulat (dari finger) dengan selisih
     * Jam Hasil (dari jadwal shift produksi, lihat resolveJamHasil()).
     * Efeknya sekarang jadi pembanding jadwal vs jam bulat aktual finger.
     */
    protected function tentukanPerbandingan(?string $bulatMasuk, ?string $bulatPulang, ?string $hasilMasuk, ?string $hasilPulang): string
    {
        $selisihBulat = $this->hitungJamKerja($bulatMasuk, $bulatPulang);
        $selisihHasil = $this->hitungJamKerja($hasilMasuk, $hasilPulang);

        return $selisihBulat === $selisihHasil ? 'ya' : 'tidak';
    }

    protected function convertTimeToExcel(?string $time): ?float
    {
        if (empty($time) || $time === '-' || strlen($time) < 5) {
            return null;
        }

        $parts = explode(':', $time);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);

        $totalSeconds = ($h * 3600) + ($m * 60) + $s;

        return round($totalSeconds / 86400, 8);
    }

    /**
     * Sama persis dengan formatDivisi() di NewRekapAbsensiExport, supaya
     * kolom "Hasil" (divisi/mesin yang dikerjakan) konsisten formatnya
     * dengan kolom "Divisi" di export lama.
     */
    protected function formatDivisi(array $row): string
    {
        $sumber = $row['sumber_label'] ?? [];

        if (empty($sumber)) {
            return '-';
        }

        return collect((array) $sumber)
            ->map(function ($item) {
                $item = trim($item);
                $itemUpper = strtoupper($item);

                if (str_contains($itemUpper, 'LAIN-LAIN')) {
                    $detail = trim(str_ireplace(['LAIN-LAIN', ':', '-'], '', $item));

                    return $detail !== '' ? "LAIN-LAIN ($detail)" : 'LAIN-LAIN';
                }

                if (str_contains($item, ':')) {
                    [$name, $detail] = array_map('trim', explode(':', $item, 2));
                    $name = strtoupper($name);

                    return $detail !== '' ? "{$name} ({$detail})" : $name;
                }

                return strtoupper($item);
            })
            ->unique()
            ->implode(', ') ?: '-';
    }

    public function title(): string
    {
        return 'RUMUS_GAJI_'.$this->tanggal;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rekap->count() + 1;

        // FIX: pastikan sel bernilai 0 (mis. Jam Kerja = 0 untuk pegawai
        // yang tidak masuk) TETAP ditampilkan sebagai "0", bukan
        // disembunyikan/kosong. Tanpa baris ini, PhpSpreadsheet/Excel
        // bisa memakai default "jangan tampilkan nol" pada sheet view,
        // sehingga kolom Jam Kerja terlihat kosong padahal isinya 0.
        $sheet->getSheetView()->setShowZeros(true);

        // Format angka eksplisit untuk kolom Jam Kerja (O) supaya
        // konsisten "General"/angka biasa, bukan warisan format lain
        // yang mungkin menyembunyikan nol (mis. custom format dengan
        // section ke-3 kosong seperti "0;-0;;@").
        $sheet->getStyle("O2:O{$lastRow}")->getNumberFormat()->setFormatCode('0');

        // Aktifkan dropdown filter Excel di baris header, range A1:P{lastRow}.
        $sheet->setAutoFilter("A1:P{$lastRow}");

        // Header: biru muda "Blue, Accent 1, Lighter 40%" (#BDD7EE),
        // font hitam bold, rata tengah — sama seperti sheet harian asli.
        $sheet->getStyle('A1:P1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BDD7EE']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Fill biru sangat muda "Blue, Accent 1, Lighter 80%" (#DDEBF7)
        // HANYA di kolom C, D, G, H, I, J, L, M, N — meniru pola asli.
        // Kolom A, B, E, F, K, O, P dibiarkan putih/kosong. Tidak ada
        // highlight khusus untuk baris "tidak".
        if ($lastRow >= 2) {
            foreach (['C', 'D', 'G', 'H', 'I', 'J', 'L', 'M', 'N'] as $col) {
                $sheet->getStyle("{$col}2:{$col}{$lastRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBF7']],
                ]);
            }
        }

        // Format jam: C/D/G/H semuanya "h:mm:ss;@" (numFmtId 170 di file
        // asli — dicek langsung dari styles.xml, BUKAN "hh:mm:ss" seperti
        // sebelumnya untuk H). E/F pakai elapsed "[h]:mm:ss;@" (numFmtId 171).
        $sheet->getStyle("C2:D{$lastRow}")->getNumberFormat()->setFormatCode('h:mm:ss;@');
        $sheet->getStyle("E2:F{$lastRow}")->getNumberFormat()->setFormatCode('[h]:mm:ss;@');
        $sheet->getStyle("G2:H{$lastRow}")->getNumberFormat()->setFormatCode('h:mm:ss;@');

        $sheet->getStyle("A1:P{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("J2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("K2:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("N2:P{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("I2:I{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("M2:M{$lastRow}")->getAlignment()->setWrapText(true);

        return [];
    }
}
