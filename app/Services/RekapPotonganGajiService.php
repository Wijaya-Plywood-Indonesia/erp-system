<?php

namespace App\Services;

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
use Illuminate\Support\Facades\Log;

/**
 * Service penghitung "Potongan" gaji (potongan target produksi) per
 * pegawai untuk satu tanggal, digabung dari 11 divisi produksi:
 * Rotary, Press Dryer, Stik, Kedi, Repair, Joint, Sanding Joint, Pot
 * Afalan Joint, Pot Siku, Pot Jelek, Pilih Veneer.
 *
 * DIEKSTRAK dari App\Exports\RumusGajiWijayaExport supaya logic yang
 * sama bisa dipakai di dua tempat tanpa duplikasi:
 *   - RumusGajiWijayaExport (kolom "Potongan" di file Excel)
 *   - App\Filament\Pages\NewAbsensi (kolom "Potongan" di tabel Data
 *     Absensi pada blade)
 *
 * Behavior perhitungan (rumus, sumber data per divisi, dsb) TIDAK
 * berubah dari versi sebelumnya di RumusGajiWijayaExport — murni
 * dipindah lokasi.
 *
 * ======================================================================
 * PATCH (dipakai juga oleh PengawasRekapPotonganGaji / WeeklyRekapPotonganGajiService):
 * - loadPotonganDryer(), loadPotonganJoint(), loadPotonganPotAfJoint(),
 *   loadPotonganPotJelek(), loadPotonganPilihVeneer(): label lini yang
 *   dikirim ke addDetailedPotongan() sebelumnya SEMUA hardcode "Rotary"
 *   (copy-paste dari loadPotonganRotary() yang lupa diganti). Sekarang
 *   masing-masing pakai label lini yang benar.
 * - loadPotonganPotSiku(): sebelumnya pakai variabel $table yang TIDAK
 *   PERNAH didefinisikan di scope method ini (selalu error "Undefined
 *   variable $table" tiap dijalankan). Sekarang pakai $mapped, konsisten
 *   dengan pola method ini yang loop per-produksi ($mapped dihasilkan
 *   dari PotSikuDataMap::make($prod) per iterasi $raw, BUKAN per-table
 *   seperti lini yang loop `foreach ($mapped as $table)`).
 * - TIDAK ADA perubahan lain: rumus, sumber data per divisi, flag
 *   POTONGAN_AMBIL_TERKECIL, addPotongan(), addDetailedPotongan(), dan
 *   seluruh method lainnya TETAP UTUH seperti versi asli.
 * ======================================================================
 */
class RekapPotonganGajiService
{
    /**
     * TOGGLE SEMENTARA: kalau true, potongan per pegawai diambil nilai
     * TERKECIL di antara semua divisi produksi yang dia kerjakan hari
     * itu (bukan dijumlah). Kalau false, kembali ke behavior lama:
     * dijumlah/akumulasi dari semua divisi (lihat addPotongan()).
     *
     * TODO: ini masih ASUMSI SEMENTARA — konfirmasi rumus yang benar
     * ke pemilik proses payroll, lalu hapus toggle ini kalau sudah pasti.
     */
    protected const POTONGAN_AMBIL_TERKECIL = true;

    protected string $tanggal;

    /**
     * @var array<string, int>
     */
    protected array $potonganMap = [];

    /**
     * Hitung & kembalikan mapping potongan gaji pegawai (kode_pegawai
     * => nominal potongan) untuk satu tanggal. Dihitung ulang setiap
     * dipanggil (tidak di-cache antar request) — konsisten dengan
     * pola service lain di aplikasi ini (mis. ValidasiTargetProduksiService).
     *
     * @return array<string, int>
     */
    public function getDetailPotongan(string $tanggal, array $liniTerpilih = []): array
    {

        $this->tanggal = $tanggal;
        $this->detailPotongan = [];

        if (empty($liniTerpilih) || in_array('rotary', $liniTerpilih)) {
            $this->loadPotonganRotary();
        }
        if (empty($liniTerpilih) || in_array('dryer', $liniTerpilih)) {
            $this->loadPotonganDryer();
        }
        if (empty($liniTerpilih) || in_array('stik', $liniTerpilih)) {
            $this->loadPotonganStik();
        }
        if (empty($liniTerpilih) || in_array('kedi', $liniTerpilih)) {
            $this->loadPotonganKedi();
        }
        if (empty($liniTerpilih) || in_array('repair', $liniTerpilih)) {
            $this->loadPotonganRepair();
        }
        if (empty($liniTerpilih) || in_array('joint', $liniTerpilih)) {
            $this->loadPotonganJoint();
        }
        if (empty($liniTerpilih) || in_array('sanding_joint', $liniTerpilih)) {
            $this->loadPotonganSandingJoint();
        }
        if (empty($liniTerpilih) || in_array('pot_afalan_joint', $liniTerpilih)) {
            $this->loadPotonganPotAfJoint();
        }
        if (empty($liniTerpilih) || in_array('pot_siku', $liniTerpilih)) {
            $this->loadPotonganPotSiku();
        }
        if (empty($liniTerpilih) || in_array('pot_jelek', $liniTerpilih)) {
            $this->loadPotonganPotJelek();
        }
        if (empty($liniTerpilih) || in_array('pilih_veneer', $liniTerpilih)) {
            $this->loadPotonganPilihVeneer();
        }

        return $this->detailPotongan;

    }

    /**
     * Cari nominal potongan seorang pegawai dari $map hasil
     * getPotonganMap(). Method stateless (tidak menghitung ulang apa
     * pun) — dipakai berulang kali per baris pegawai tanpa perlu
     * query ulang ke semua divisi produksi.
     *
     * Melakukan fallback pencarian dengan trim angka nol di depan
     * kode pegawai (mis. "007" vs "7"), sama seperti getPotongan() di
     * RumusGajiWijayaExport sebelumnya.
     *
     * @param  array<string, int>  $map
     */
    public function resolvePotongan(array $map, ?string $kodep): int
    {
        if (! $kodep || $kodep === '-') {
            return 0;
        }

        if (isset($map[$kodep])) {
            return (int) $map[$kodep];
        }

        $trimmed = ltrim($kodep, '0');
        if ($trimmed !== '' && isset($map[$trimmed])) {
            return (int) $map[$trimmed];
        }

        foreach ($map as $mapKey => $val) {
            if (ltrim((string) $mapKey, '0') === $trimmed) {
                return (int) $val;
            }
        }

        return 0;
    }

    public array $detailPotongan = [];

    protected function addDetailedPotongan(string $tanggal, string $lini, string|int|null $kodep, int|float $pot, float $target, float $hasil, float $selisih): void
    {
        if (empty($kodep) || $kodep === '-' || $pot <= 0) {
            return;
        }
        $key = (string) $kodep;
        $this->detailPotongan[] = [
            'tanggal' => $tanggal,
            'lini' => $lini,
            'kodep' => $key,
            'potongan' => (int) $pot,
            'target' => $target,
            'hasil' => $hasil,
            'selisih' => $selisih,
        ];
    }

    /**
     * Menambahkan potongan untuk seorang pegawai. Behavior tergantung
     * flag POTONGAN_AMBIL_TERKECIL:
     *   - true  -> potonganMap[$key] = MIN(nilai lama, $pot)
     *   - false -> potonganMap[$key] = nilai lama + $pot (akumulasi)
     */
    protected function addPotongan(string|int|null $kodep, int|float $pot): void
    {
        if (empty($kodep) || $kodep === '-' || $pot <= 0) {
            return;
        }

        $key = (string) $kodep;
        $pot = (int) $pot;

        if (self::POTONGAN_AMBIL_TERKECIL) {
            // Ambil nilai TERKECIL di antara semua divisi yang
            // mengenai pegawai ini, bukan dijumlah.
            $this->potonganMap[$key] = isset($this->potonganMap[$key])
                ? min($this->potonganMap[$key], $pot)
                : $pot;
        } else {
            // Behavior lama: akumulasi/dijumlah dari semua divisi.
            $this->potonganMap[$key] = ($this->potonganMap[$key] ?? 0) + $pot;
        }
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
                            $this->addDetailedPotongan($this->tanggal, 'Rotary', $kodep, $pot, $table['target'] ?? 0, $table['hasil'] ?? 0, $table['selisih'] ?? 0);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Rotary in PotonganGajiService: '.$e->getMessage());
        }
    }

    /**
     * 1. Press Dryer
     *
     * PATCH: label lini "Rotary" -> "Dryer" (sebelumnya salah copy-paste
     * dari loadPotonganRotary()).
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
                            $this->addDetailedPotongan($this->tanggal, 'Dryer', $kodep, $pot, $table['target'] ?? 0, $table['hasil'] ?? 0, $table['selisih'] ?? 0);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Dryer in PotonganGajiService: '.$e->getMessage());
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
                            $this->addDetailedPotongan($this->tanggal, 'Stik', $kodep, $potonganDibulatkan, $result?->targetAdjusted ?? 0, $hasilPalet ?? 0, max(0, ($result?->targetAdjusted ?? 0) - ($hasilPalet ?? 0)));
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Stik in PotonganGajiService: '.$e->getMessage());
        }
    }

    /**
     * 3. Kedi
     *
     * Filter tanggal HANYA whereDate('tanggal_actual_bongkar',
     * $tanggal) — disamakan dengan LaporanKedi::loadAllData() /
     * buildAggregatedPotongan() (referensi tampilan "Rincian Potongan
     * Target" yang sudah benar). $tglStr (dasar jam masuk/pulang
     * pegawai kedi) fallback ke tanggal_actual_bongkar ?? tanggal.
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
                        ->orWhere(function ($sub) {
                            $sub->whereNull('tanggal_actual_bongkar')
                                ->whereDate('tanggal_bongkar', $this->tanggal);
                        });
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
                    $stdTarget = 0;
                    $selisih = 0;

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
                        $stdTarget = $hitung?->targetAdjusted ?? 0;
                        $selisih = $totalHasil - $stdTarget;
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
                            $this->addDetailedPotongan($this->tanggal, 'Kedi', $kodep, (int) $potonganFinal, $stdTarget, $totalHasil, $selisih);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Kedi in PotonganGajiService: '.$e->getMessage());
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
                                $this->addDetailedPotongan($this->tanggal, 'Repair', $kodep, $pot, $table['total_target'] ?? 0, $table['total_hasil'] ?? 0, $table['total_selisih'] ?? 0);
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Repair in PotonganGajiService: '.$e->getMessage());
        }
    }

    /**
     * 5. Joint
     *
     * PATCH: label lini "Rotary" -> "Joint".
     */
    protected function loadPotonganJoint(): void
    {
        try {
            $raw = LoadLaporanJoin::run($this->tanggal);
            if ($raw && $raw->isNotEmpty()) {
                $mapped = JoinDataMap::make($raw);
                foreach ($mapped as $table) {
                    $target = array_sum(array_column($table['items'] ?? [], 'target'));
                    $hasil = array_sum(array_column($table['items'] ?? [], 'hasil'));
                    $selisih = array_sum(array_column($table['items'] ?? [], 'selisih'));

                    foreach ($table['pekerja'] ?? [] as $p) {
                        $kodep = $p['id'] ?? null;
                        $pot = (int) ($p['pot_target'] ?? 0);
                        if ($pot > 0) {
                            $this->addDetailedPotongan($this->tanggal, 'Joint', $kodep, $pot, $target, $hasil, $selisih);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Joint in PotonganGajiService: '.$e->getMessage());
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
                
                $totalTarget = array_sum(array_column($mapped['per_ukuran'] ?? [], 'target'));
                $totalHasil = array_sum(array_column($mapped['per_ukuran'] ?? [], 'hasil'));
                $totalSelisih = array_sum(array_column($mapped['per_ukuran'] ?? [], 'selisih'));

                foreach ($mapped['pekerja'] ?? [] as $p) {
                    $kodep = $p['id'] ?? null;
                    $pot = (int) ($p['pot_target'] ?? 0);
                    if ($pot > 0) {
                        $this->addDetailedPotongan($this->tanggal, 'Sanding Joint', $kodep, $pot, $totalTarget, $totalHasil, $totalSelisih);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Sanding Joint in PotonganGajiService: '.$e->getMessage());
        }
    }

    /**
     * 7. Pot Afalan Joint
     *
     * PATCH: label lini "Rotary" -> "Pot Afalan Joint".
     */
    protected function loadPotonganPotAfJoint(): void
    {
        try {
            $raw = LoadLaporanPotAfalan::run($this->tanggal);
            if ($raw && $raw->isNotEmpty()) {
                $mapped = PotAfalanDataMap::make($raw);
                foreach ($mapped as $table) {
                    $target = array_sum(array_column($table['detail_produksi'] ?? [], 'target'));
                    $hasil = array_sum(array_column($table['detail_produksi'] ?? [], 'hasil'));
                    $selisih = array_sum(array_column($table['detail_produksi'] ?? [], 'selisih'));

                    foreach ($table['rekap_pekerja'] ?? [] as $p) {
                        $kodep = $p['id'] ?? null;
                        $pot = (int) ($p['pot_target'] ?? 0);
                        if ($pot > 0) {
                            $this->addDetailedPotongan($this->tanggal, 'Pot Afalan Joint', $kodep, $pot, $target, $hasil, $selisih);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan PotAfalan in PotonganGajiService: '.$e->getMessage());
        }
    }

    /**
     * 8. Pot Siku
     *
     * PATCH: sebelumnya pakai $table["target"]/$table["hasil"]/$table["selisih"]
     * yang TIDAK PERNAH didefinisikan di scope method ini (selalu error
     * "Undefined variable $table" tiap dijalankan) — diganti $mapped,
     * yang memang variabel yang tersedia di scope loop ini. Label lini
     * "Rotary" -> "Pot Siku".
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
                            $target = array_sum(array_column($p['items'] ?? [], 'target'));
                            $hasil = array_sum(array_column($p['items'] ?? [], 'hasil'));
                            $selisih = array_sum(array_column($p['items'] ?? [], 'selisih'));

                            $this->addDetailedPotongan(
                                $this->tanggal,
                                'Pot Siku',
                                $kodep,
                                $pot,
                                $target,
                                $hasil,
                                $selisih
                            );
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Pot Siku in PotonganGajiService: '.$e->getMessage());
        }
    }

    /**
     * 9. Pot Jelek
     *
     * PATCH: label lini "Rotary" -> "Pot Jelek".
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
                            $target = array_sum(array_column($p['items'] ?? [], 'target'));
                            $hasil = array_sum(array_column($p['items'] ?? [], 'hasil'));
                            $selisih = array_sum(array_column($p['items'] ?? [], 'selisih'));

                            $this->addDetailedPotongan($this->tanggal, 'Pot Jelek', $kodep, $pot, $target, $hasil, $selisih);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Pot Jelek in PotonganGajiService: '.$e->getMessage());
        }
    }

    /**
     * 10. Pilih Veneer
     *
     * PATCH: label lini "Rotary" -> "Pilih Veneer".
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
                    $target = array_sum(array_column($table['detail_produksi'] ?? [], 'target'));
                    $hasil = array_sum(array_column($table['detail_produksi'] ?? [], 'hasil'));
                    $selisih = array_sum(array_column($table['detail_produksi'] ?? [], 'selisih'));

                    foreach ($table['rekap_pekerja'] ?? [] as $p) {
                        $kodep = $p['id'] ?? null;
                        $pot = (int) ($p['pot_target'] ?? 0);
                        if ($pot > 0) {
                            $this->addDetailedPotongan($this->tanggal, 'Pilih Veneer', $kodep, $pot, $target, $hasil, $selisih);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error loading Potongan Pilih Veneer in PotonganGajiService: '.$e->getMessage());
        }
    }
}
