<?php

namespace App\Exports;

use App\Actions\HitungPotonganProduksiAction;
use App\DataTransferObjects\PekerjaKerjaInput;
use App\Enums\Mesin;
use App\Enums\StrategiPembagian;
use App\Filament\Pages\LaporanJoin\Queries\LoadLaporanJoin;
use App\Filament\Pages\LaporanJoin\Transformers\JoinDataMap;
use App\Filament\Pages\LaporanPilihVeneer\Transformers\PilihVeneerDataMap;
use App\Filament\Pages\LaporanPotAfalanJoin\Queries\LoadLaporanPotAfalan;
use App\Filament\Pages\LaporanPotAfalanJoin\Transformers\PotAfalanDataMap;
use App\Filament\Pages\LaporanPotJelek\Queries\LoadLaporanPotJelek;
use App\Filament\Pages\LaporanPotJelek\Transformers\PotJelekDataMap;
use App\Filament\Pages\LaporanPotSiku\Queries\LoadLaporanPotSiku;
use App\Filament\Pages\LaporanPotSiku\Transformers\PotSikuDataMap;
use App\Filament\Pages\LaporanPressDryer\Queries\LoadPressDryer;
use App\Filament\Pages\LaporanPressDryer\Transformers\DryerDataMap;
use App\Filament\Pages\LaporanProduksi\Queries\LoadProduksi;
use App\Filament\Pages\LaporanProduksi\Transformers\ProduksiDataMap;
use App\Filament\Pages\LaporanRepairs\Queries\LoadLaporanRepairs;
use App\Filament\Pages\LaporanRepairs\Transformers\RepairDataMap;
use App\Filament\Pages\LaporanSandingJoin\Queries\LoadLaporanSandingJoin;
use App\Filament\Pages\LaporanSandingJoin\Transformers\SandingJoinDataMap;
use App\Models\ProduksiKedi;
use App\Models\ProduksiPilihVeneer;
use App\Models\ProduksiStik;
use App\Models\Target;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
 *   - TAMBAH DIVISI PRODUKSI ROTARY ke perhitungan potongan (lihat
 *     loadPotonganRotary()). Sebelumnya loadPotonganMap() hanya
 *     mencakup 10 divisi produksi dan tidak menyertakan Laporan
 *     Produksi Rotary (App\Filament\Pages\LaporanProduksi), sehingga
 *     potongan pekerja mesin rotary tidak pernah masuk ke kolom
 *     "Potongan" export ini. Sekarang sudah ditambahkan mengikuti pola
 *     yang sama seperti loadPotonganDryer() (per mesin -> pekerja ->
 *     pot_target).
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

    protected array $potonganMap = [];

    public function __construct(
        protected Collection $rekap,
        protected string $tanggal,
    ) {
        $this->originalPrecision = (int) ini_get('precision');
        $this->originalSerializePrecision = (int) ini_get('serialize_precision');

        ini_set('precision', 16);
        ini_set('serialize_precision', -1);

        $this->loadPotonganMap();
    }

    /**
     * Memuat mapping potongan gaji pegawai dari perhitungan produksi.
     * Mencakup 11 divisi produksi:
     * 1. Produksi Rotary
     * 2. Press Dryer
     * 3. Stik
     * 4. Kedi
     * 5. Repair
     * 6. Joint
     * 7. Sanding Joint
     * 8. Pot Afalan Joint
     * 9. Pot Siku
     * 10. Pot Jelek
     * 11. Pilih Veneer
     */
    protected function loadPotonganMap(): void
    {
        $this->potonganMap = [];

        $this->loadPotonganRotary();
        $this->loadPotonganDryer();
        $this->loadPotonganStik();
        $this->loadPotonganKedi();
        $this->loadPotonganRepair();
        $this->loadPotonganJoint();
        $this->loadPotonganSandingJoint();
        $this->loadPotonganPotAfJoint();
        $this->loadPotonganPotSiku();
        $this->loadPotonganPotJelek();
        $this->loadPotonganPilihVeneer();
    }

    protected function addPotongan(string|int|null $kodep, int|float $pot): void
    {
        if (empty($kodep) || $kodep === '-' || $pot <= 0) {
            return;
        }

        $key = (string) $kodep;
        $this->potonganMap[$key] = ($this->potonganMap[$key] ?? 0) + (int) $pot;
    }

    protected function getPotongan(?string $kodep): int
    {
        if (! $kodep || $kodep === '-') {
            return 0;
        }

        if (isset($this->potonganMap[$kodep])) {
            return (int) $this->potonganMap[$kodep];
        }

        $trimmed = ltrim($kodep, '0');
        if ($trimmed !== '' && isset($this->potonganMap[$trimmed])) {
            return (int) $this->potonganMap[$trimmed];
        }

        foreach ($this->potonganMap as $mapKey => $val) {
            if (ltrim((string) $mapKey, '0') === $trimmed) {
                return (int) $val;
            }
        }

        return 0;
    }

    protected function roundToNearest500(float $value): int
    {
        $ribuan = floor($value / 1000);
        $ratusan = $value % 1000;
        if ($ratusan < 300) {
            return (int) ($ribuan * 1000);
        }
        if ($ratusan < 800) {
            return (int) ($ribuan * 1000 + 500);
        }

        return (int) (($ribuan + 1) * 1000);
    }

    /**
     * 0. Produksi Rotary
     *
     * Mengikuti pola yang sama seperti loadPotonganDryer(): data
     * dimuat lewat LoadProduksi::run($tanggal), ditransformasi lewat
     * ProduksiDataMap::make(), lalu setiap baris 'pekerja' di tiap
     * mesin diambil field 'id' (kode_pegawai) dan 'pot_target'-nya.
     */
    protected function loadPotonganRotary(): void
    {
        try {
            $raw = LoadProduksi::run($this->tanggal);
            if ($raw && $raw->isNotEmpty()) {
                $mapped = ProduksiDataMap::make($raw);
                foreach ($mapped as $table) {
                    foreach ($table['pekerja'] ?? [] as $p) {
                        $kodep = $p['id'] ?? null;
                        $pot = (int) ($p['pot_target'] ?? 0);
                        if ($pot > 0) {
                            $this->addPotongan($kodep, $pot);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Rotary in RumusGajiWijayaExport: '.$e->getMessage());
        }
    }

    /**
     * 1. Press Dryer
     */
    protected function loadPotonganDryer(): void
    {
        try {
            $raw = LoadPressDryer::run($this->tanggal);
            if ($raw && $raw->isNotEmpty()) {
                $mapped = DryerDataMap::make($raw);
                foreach ($mapped as $table) {
                    foreach ($table['pekerja'] ?? [] as $p) {
                        $kodep = $p['id'] ?? null;
                        $pot = (int) ($p['pot_target'] ?? 0);
                        if ($pot > 0) {
                            $this->addPotongan($kodep, $pot);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Dryer in RumusGajiWijayaExport: '.$e->getMessage());
        }
    }

    /**
     * 2. Stik
     */
    protected function loadPotonganStik(): void
    {
        try {
            $produksiList = ProduksiStik::with([
                'detailPegawaiStik.pegawai:id,kode_pegawai,nama_pegawai',
                'detailHasilStik.ukuran',
                'detailHasilStik.jenisKayu',
            ])
                ->whereDate('tanggal_produksi', $this->tanggal)
                ->get();

            if ($produksiList->isNotEmpty()) {
                $action = app(HitungPotonganProduksiAction::class);

                foreach ($produksiList as $produksi) {
                    $hasilPalet = count($produksi->detailHasilStik ?? []);

                    $pekerjaInput = collect($produksi->detailPegawaiStik ?? [])->map(function ($detail) {
                        $masuk = $detail->masuk ? Carbon::parse($detail->masuk) : null;
                        $pulang = $detail->pulang ? Carbon::parse($detail->pulang) : null;
                        $menitIstirahat = $detail->menit_istirahat ?? 60;
                        $menit = ($masuk && $pulang)
                            ? max(0, abs($pulang->diffInMinutes($masuk)) - $menitIstirahat)
                            : (9 * 60);

                        return new PekerjaKerjaInput(
                            idPegawai: $detail->pegawai?->kode_pegawai ?? '-',
                            menitKerja: (float) $menit,
                        );
                    })->all();

                    $result = $action->execute(
                        mesin: Mesin::Stik,
                        strategi: StrategiPembagian::Kolektif,
                        pekerja: $pekerjaInput,
                        hasilAktual: $hasilPalet,
                    );

                    $potonganPerPegawai = $result?->potonganPerPegawai ?? [];
                    foreach ($produksi->detailPegawaiStik ?? [] as $detail) {
                        $kodep = $detail->pegawai?->kode_pegawai ?? '-';
                        $potonganPegawaiIni = $potonganPerPegawai[$kodep] ?? 0;
                        $potonganDibulatkan = $potonganPegawaiIni > 0
                            ? $this->roundToNearest500((float) $potonganPegawaiIni)
                            : 0;
                        if ($potonganDibulatkan > 0) {
                            $this->addPotongan($kodep, $potonganDibulatkan);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Stik in RumusGajiWijayaExport: '.$e->getMessage());
        }
    }

    /**
     * 3. Kedi
     */
    protected function loadPotonganKedi(): void
    {
        try {
            $produksiList = ProduksiKedi::with([
                'detailBongkarKedi.jenisKayu',
                'detailMasukKedi.jenisKayu',
                'detailPegawaiKedi.pegawai',
            ])
                ->where(function ($q) {
                    $q->whereDate('tanggal_actual_bongkar', $this->tanggal)
                        ->orWhereDate('tanggal_bongkar', $this->tanggal)
                        ->orWhereDate('tanggal', $this->tanggal);
                })
                ->get();

            if ($produksiList->isNotEmpty()) {
                $groups = $produksiList->groupBy(fn ($p) => $p->status);

                foreach ($groups as $status => $groupProduksi) {
                    $totalHasil = 0;
                    foreach ($groupProduksi as $produksi) {
                        if ($status === 'bongkar' && $produksi->detailBongkarKedi) {
                            $totalHasil += $produksi->detailBongkarKedi->count();
                        } elseif ($status === 'masuk' && $produksi->detailMasukKedi) {
                            $totalHasil += $produksi->detailMasukKedi->sum('jumlah');
                        }
                    }

                    $uniquePegawai = [];
                    foreach ($groupProduksi as $produksi) {
                        if (! $produksi->detailPegawaiKedi) {
                            continue;
                        }

                        $tglStr = Carbon::parse($produksi->tanggal_actual_bongkar ?? $produksi->tanggal_bongkar ?? $produksi->tanggal ?? now())->format('Y-m-d');

                        foreach ($produksi->detailPegawaiKedi as $dp) {
                            if (! $dp->pegawai) {
                                continue;
                            }

                            $kodep = $dp->pegawai->kode_pegawai ?? '-';
                            $masukAt = null;
                            $pulangAt = null;
                            if (! empty($dp->masuk) && ! empty($dp->pulang)) {
                                $masukAt = Carbon::parse($tglStr.' '.$dp->masuk);
                                $pulangAt = Carbon::parse($tglStr.' '.$dp->pulang);
                                if ($pulangAt->lessThan($masukAt)) {
                                    $pulangAt->addDay();
                                }
                            }

                            if (! isset($uniquePegawai[$kodep])) {
                                $uniquePegawai[$kodep] = [
                                    'pegawai' => $dp->pegawai,
                                    'masuk' => $masukAt,
                                    'pulang' => $pulangAt,
                                    'potongan_manual' => $dp->potongan,
                                ];
                            } else {
                                if ($masukAt && (! $uniquePegawai[$kodep]['masuk'] || $masukAt->lessThan($uniquePegawai[$kodep]['masuk']))) {
                                    $uniquePegawai[$kodep]['masuk'] = $masukAt;
                                }
                                if ($pulangAt && (! $uniquePegawai[$kodep]['pulang'] || $pulangAt->greaterThan($uniquePegawai[$kodep]['pulang']))) {
                                    $uniquePegawai[$kodep]['pulang'] = $pulangAt;
                                }
                                if ($dp->potongan !== null) {
                                    $uniquePegawai[$kodep]['potongan_manual'] = $dp->potongan;
                                }
                            }
                        }
                    }

                    $jumlahPekerja = count($uniquePegawai);
                    $potonganPerPegawai = [];

                    if ($status === 'bongkar') {
                        $mesinEnum = Mesin::Bongkar;
                        $strategi = $mesinEnum->strategiPembagian();

                        $pekerjaInput = [];
                        foreach ($uniquePegawai as $kodep => $p) {
                            $menitKerja = 0;
                            if ($p['masuk'] && $p['pulang']) {
                                $menitKerja = max(0, $p['masuk']->diffInMinutes($p['pulang']));
                            }
                            $pekerjaInput[] = new PekerjaKerjaInput(
                                idPegawai: $kodep,
                                menitKerja: (float) $menitKerja,
                            );
                        }

                        $action = app(HitungPotonganProduksiAction::class);
                        $hitung = $action->execute(
                            $mesinEnum,
                            $strategi,
                            $pekerjaInput,
                            (float) $totalHasil,
                        );

                        $potonganPerPegawai = $hitung?->potonganPerPegawai ?? [];
                    } else {
                        $targetRef = Target::where('kode_ukuran', 'MASUK')->first();
                        $stdTarget = (int) ($targetRef->target ?? 0);
                        $stdPotHarga = (int) ($targetRef->potongan ?? 0);

                        $selisih = $totalHasil - $stdTarget;
                        $potonganPerOrangLegacy = 0;

                        if ($stdTarget > 0 && $selisih < 0 && $stdPotHarga > 0 && $jumlahPekerja > 0) {
                            $kekurangan = abs($selisih);
                            $totalPot = $kekurangan * $stdPotHarga;
                            $potonganRaw = $totalPot / $jumlahPekerja;
                            $potonganPerOrangLegacy = $this->roundToNearest500((float) $potonganRaw);
                        }

                        foreach ($uniquePegawai as $kodep => $p) {
                            $potonganPerPegawai[$kodep] = $potonganPerOrangLegacy;
                        }
                    }

                    foreach ($uniquePegawai as $kodep => $p) {
                        $potonganFinal = $p['potongan_manual'] ?? ($potonganPerPegawai[$kodep] ?? 0);
                        if ($potonganFinal > 0) {
                            $this->addPotongan($kodep, (int) $potonganFinal);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Kedi in RumusGajiWijayaExport: '.$e->getMessage());
        }
    }

    /**
     * 4. Repair
     */
    protected function loadPotonganRepair(): void
    {
        try {
            $raw = LoadLaporanRepairs::run($this->tanggal);
            if ($raw && $raw->isNotEmpty()) {
                $mapped = RepairDataMap::make($raw);
                $handled = [];
                foreach ($mapped as $table) {
                    foreach ($table['pekerja'] ?? [] as $p) {
                        $kodep = $p['id'] ?? null;
                        if ($kodep && ! isset($handled[$kodep])) {
                            $handled[$kodep] = true;
                            $pot = (int) ($p['pot_target'] ?? 0);
                            if ($pot > 0) {
                                $this->addPotongan($kodep, $pot);
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Repair in RumusGajiWijayaExport: '.$e->getMessage());
        }
    }

    /**
     * 5. Joint
     */
    protected function loadPotonganJoint(): void
    {
        try {
            $raw = LoadLaporanJoin::run($this->tanggal);
            if ($raw && $raw->isNotEmpty()) {
                $mapped = JoinDataMap::make($raw);
                foreach ($mapped as $table) {
                    foreach ($table['pekerja'] ?? [] as $p) {
                        $kodep = $p['id'] ?? null;
                        $pot = (int) ($p['pot_target'] ?? 0);
                        if ($pot > 0) {
                            $this->addPotongan($kodep, $pot);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Joint in RumusGajiWijayaExport: '.$e->getMessage());
        }
    }

    /**
     * 6. Sanding Joint
     */
    protected function loadPotonganSandingJoint(): void
    {
        try {
            $raw = LoadLaporanSandingJoin::run($this->tanggal);
            if ($raw && $raw->isNotEmpty()) {
                $mapped = SandingJoinDataMap::make($raw);
                foreach ($mapped['pekerja'] ?? [] as $p) {
                    $kodep = $p['id'] ?? null;
                    $pot = (int) ($p['pot_target'] ?? 0);
                    if ($pot > 0) {
                        $this->addPotongan($kodep, $pot);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Sanding Joint in RumusGajiWijayaExport: '.$e->getMessage());
        }
    }

    /**
     * 7. Pot Afalan Joint
     */
    protected function loadPotonganPotAfJoint(): void
    {
        try {
            $raw = LoadLaporanPotAfalan::run($this->tanggal);
            if ($raw && $raw->isNotEmpty()) {
                $mapped = PotAfalanDataMap::make($raw);
                foreach ($mapped as $table) {
                    foreach ($table['rekap_pekerja'] ?? [] as $p) {
                        $kodep = $p['id'] ?? null;
                        $pot = (int) ($p['pot_target'] ?? 0);
                        if ($pot > 0) {
                            $this->addPotongan($kodep, $pot);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan PotAfalan in RumusGajiWijayaExport: '.$e->getMessage());
        }
    }

    /**
     * 8. Pot Siku
     */
    protected function loadPotonganPotSiku(): void
    {
        try {
            $raw = LoadLaporanPotSiku::byTanggal(Carbon::parse($this->tanggal));
            if ($raw && $raw->isNotEmpty()) {
                foreach ($raw as $prod) {
                    $mapped = PotSikuDataMap::make($prod);
                    foreach ($mapped['pekerja'] ?? [] as $p) {
                        $kodep = $p['kode_pegawai'] ?? null;
                        $pot = (int) ($p['potongan'] ?? 0);
                        if ($pot > 0) {
                            $this->addPotongan($kodep, $pot);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Pot Siku in RumusGajiWijayaExport: '.$e->getMessage());
        }
    }

    /**
     * 9. Pot Jelek
     */
    protected function loadPotonganPotJelek(): void
    {
        try {
            $raw = LoadLaporanPotJelek::run($this->tanggal);
            if ($raw && $raw->isNotEmpty()) {
                $mapped = PotJelekDataMap::make($raw);
                foreach ($mapped as $table) {
                    foreach ($table['pekerja'] ?? [] as $p) {
                        $kodep = $p['kode_pegawai'] ?? null;
                        $pot = (int) ($p['potongan'] ?? 0);
                        if ($pot > 0) {
                            $this->addPotongan($kodep, $pot);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Pot Jelek in RumusGajiWijayaExport: '.$e->getMessage());
        }
    }

    /**
     * 10. Pilih Veneer
     */
    protected function loadPotonganPilihVeneer(): void
    {
        try {
            $raw = ProduksiPilihVeneer::with([
                'hasilPilihVeneer.modalPilihVeneer.ukuran',
                'hasilPilihVeneer.modalPilihVeneer.jenisKayu',
                'hasilPilihVeneer.modalPilihVeneer.stokVeneerJadi.jenisKayu',
                'pegawaiPilihVeneer.pegawai',
            ])
                ->whereDate('tanggal_produksi', $this->tanggal)
                ->get();

            if ($raw && $raw->isNotEmpty()) {
                $mapped = PilihVeneerDataMap::make($raw);
                foreach ($mapped as $table) {
                    foreach ($table['rekap_pekerja'] ?? [] as $p) {
                        $kodep = $p['id'] ?? null;
                        $pot = (int) ($p['pot_target'] ?? 0);
                        if ($pot > 0) {
                            $this->addPotongan($kodep, $pot);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Pilih Veneer in RumusGajiWijayaExport: '.$e->getMessage());
        }
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

        $kodep = $row['kode_pegawai'] ?? null;
        $potongan = $this->getPotongan($kodep);

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
            // Potongan target produksi (11 divisi produksi)
            $potongan > 0 ? (int) $potongan : '',
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
        $sheet->getStyle("L2:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("L2:L{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("N2:P{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("I2:I{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("M2:M{$lastRow}")->getAlignment()->setWrapText(true);

        return [];
    }
}
