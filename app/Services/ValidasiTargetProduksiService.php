<?php

namespace App\Services;

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
use App\Models\ProduksiPilihVeneer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Mengecek seluruh divisi produksi untuk tanggal tertentu, mengumpulkan
 * daftar ukuran/item yang TIDAK PUNYA TARGET (has_target === false pada
 * hasil masing-masing *DataMap::make()).
 *
 * TUJUAN: dipakai SEBELUM export "Rumus Gaji Wijaya" supaya user bisa
 * lihat dulu item mana yang belum ada target-nya di Master Target.
 * Ini SENGAJA TIDAK MEMBLOKIR export — user tetap bisa lanjut export,
 * dengan konsekuensi: potongan untuk item tanpa target itu otomatis
 * dianggap 0 (lihat HitungPotonganProduksiAction / masing-masing
 * transformer, yang memang skip perhitungan potongan kalau target
 * tidak ditemukan).
 *
 * REVISI TERBARU: hasil di-MERGE (dedupe) per kombinasi divisi+ukuran.
 * Sebelumnya satu ukuran yang sama bisa muncul berkali-kali di daftar
 * kalau dikerjakan di beberapa meja/mesin berbeda pada tanggal yang
 * sama (mis. "244mm x 122mm x 0.5mm (Sengon, KW3)" muncul di Meja 1,
 * Meja 2, dst) — padahal yang perlu dilengkapi admin di Master Target
 * itu SATU baris per ukuran/jenis kayu/KW, bukan per meja. Field
 * 'mesin' dihapus sepenuhnya dari hasil karena sudah tidak relevan
 * untuk tujuan ini.
 *
 * CARA KERJA: dipakai generic recursive scanner (scanForMissingTarget)
 * yang mencari key 'has_target' === false di level manapun dalam array
 * hasil transformer, supaya tidak perlu tahu persis struktur nested
 * tiap divisi. Divisi yang transformer-nya BELUM mengirim flag
 * 'has_target' otomatis tidak akan terdeteksi di sini (bukan berarti
 * datanya aman, tapi memang belum ada infonya) — itu wajar bagian dari
 * keterbatasan pendekatan generic ini.
 */
class ValidasiTargetProduksiService
{
    /**
     * @return array<int, array{divisi: string, ukuran: string, keterangan: string}>
     */
    public function cekMissingTarget(string $tanggal): array
    {
        $tanggal = Carbon::parse($tanggal)->format('Y-m-d');

        $missing = [];

        $missing = array_merge($missing, $this->cekRotary($tanggal));
        $missing = array_merge($missing, $this->cekPotAfalanJoint($tanggal));
        $missing = array_merge($missing, $this->cekDryer($tanggal));
        $missing = array_merge($missing, $this->cekRepair($tanggal));
        $missing = array_merge($missing, $this->cekJoint($tanggal));
        $missing = array_merge($missing, $this->cekSandingJoint($tanggal));
        $missing = array_merge($missing, $this->cekPotSiku($tanggal));
        $missing = array_merge($missing, $this->cekPotJelek($tanggal));
        $missing = array_merge($missing, $this->cekPilihVeneer($tanggal));

        // Merge/dedupe: satu ukuran (dengan jenis kayu & KW yang sama)
        // yang belum punya target hanya perlu tampil SEKALI per divisi,
        // meskipun dikerjakan di banyak meja/mesin pada tanggal yang
        // sama. Urutan kemunculan pertama yang dipertahankan.
        return collect($missing)
            ->unique(fn (array $item) => $item['divisi'].'|'.$item['ukuran'])
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // 1. Produksi Rotary
    // ------------------------------------------------------------------
    protected function cekRotary(string $tanggal): array
    {
        try {
            $raw = LoadProduksi::run($tanggal);
            if (! $raw || $raw->isEmpty()) {
                return [];
            }

            return $this->scanForMissingTarget(
                ProduksiDataMap::make($raw),
                'Produksi Rotary'
            );
        } catch (\Throwable $e) {
            Log::error('Error cek target Rotary: '.$e->getMessage());

            return [];
        }
    }

    // ------------------------------------------------------------------
    // 2. Pot Afalan Join
    // ------------------------------------------------------------------
    protected function cekPotAfalanJoint(string $tanggal): array
    {
        try {
            $raw = LoadLaporanPotAfalan::run($tanggal);
            if (! $raw || $raw->isEmpty()) {
                return [];
            }

            return $this->scanForMissingTarget(
                PotAfalanDataMap::make($raw),
                'Pot Afalan Join'
            );
        } catch (\Throwable $e) {
            Log::error('Error cek target Pot Afalan Join: '.$e->getMessage());

            return [];
        }
    }

    // ------------------------------------------------------------------
    // 3. Press Dryer
    // ------------------------------------------------------------------
    protected function cekDryer(string $tanggal): array
    {
        try {
            $raw = LoadPressDryer::run($tanggal);
            if (! $raw || $raw->isEmpty()) {
                return [];
            }

            return $this->scanForMissingTarget(
                DryerDataMap::make($raw),
                'Press Dryer'
            );
        } catch (\Throwable $e) {
            Log::error('Error cek target Dryer: '.$e->getMessage());

            return [];
        }
    }

    // ------------------------------------------------------------------
    // 4. Repair
    // ------------------------------------------------------------------
    protected function cekRepair(string $tanggal): array
    {
        try {
            $raw = LoadLaporanRepairs::run($tanggal);
            if (! $raw || $raw->isEmpty()) {
                return [];
            }

            return $this->scanForMissingTarget(
                RepairDataMap::make($raw),
                'Repair'
            );
        } catch (\Throwable $e) {
            Log::error('Error cek target Repair: '.$e->getMessage());

            return [];
        }
    }

    // ------------------------------------------------------------------
    // 5. Joint
    // ------------------------------------------------------------------
    protected function cekJoint(string $tanggal): array
    {
        try {
            $raw = LoadLaporanJoin::run($tanggal);
            if (! $raw || $raw->isEmpty()) {
                return [];
            }

            return $this->scanForMissingTarget(
                JoinDataMap::make($raw),
                'Joint'
            );
        } catch (\Throwable $e) {
            Log::error('Error cek target Joint: '.$e->getMessage());

            return [];
        }
    }

    // ------------------------------------------------------------------
    // 6. Sanding Joint
    // ------------------------------------------------------------------
    protected function cekSandingJoint(string $tanggal): array
    {
        try {
            $raw = LoadLaporanSandingJoin::run($tanggal);
            if (! $raw || $raw->isEmpty()) {
                return [];
            }

            return $this->scanForMissingTarget(
                SandingJoinDataMap::make($raw),
                'Sanding Joint'
            );
        } catch (\Throwable $e) {
            Log::error('Error cek target Sanding Joint: '.$e->getMessage());

            return [];
        }
    }

    // ------------------------------------------------------------------
    // 7. Pot Siku
    // ------------------------------------------------------------------
    protected function cekPotSiku(string $tanggal): array
    {
        try {
            $raw = LoadLaporanPotSiku::byTanggal(Carbon::parse($tanggal));
            if (! $raw || $raw->isEmpty()) {
                return [];
            }

            $result = [];
            foreach ($raw as $prod) {
                $mapped = PotSikuDataMap::make($prod);
                $result = array_merge($result, $this->scanForMissingTarget($mapped, 'Pot Siku'));
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('Error cek target Pot Siku: '.$e->getMessage());

            return [];
        }
    }

    // ------------------------------------------------------------------
    // 8. Pot Jelek
    // ------------------------------------------------------------------
    protected function cekPotJelek(string $tanggal): array
    {
        try {
            $raw = LoadLaporanPotJelek::run($tanggal);
            if (! $raw || $raw->isEmpty()) {
                return [];
            }

            return $this->scanForMissingTarget(
                PotJelekDataMap::make($raw),
                'Pot Jelek'
            );
        } catch (\Throwable $e) {
            Log::error('Error cek target Pot Jelek: '.$e->getMessage());

            return [];
        }
    }

    // ------------------------------------------------------------------
    // 9. Pilih Veneer
    // ------------------------------------------------------------------
    protected function cekPilihVeneer(string $tanggal): array
    {
        try {
            $raw = ProduksiPilihVeneer::with([
                'hasilPilihVeneer.modalPilihVeneer.ukuran',
                'hasilPilihVeneer.modalPilihVeneer.jenisKayu',
                'hasilPilihVeneer.modalPilihVeneer.stokVeneerJadi.jenisKayu',
                'pegawaiPilihVeneer.pegawai',
            ])
                ->whereDate('tanggal_produksi', $tanggal)
                ->get();

            if ($raw->isEmpty()) {
                return [];
            }

            return $this->scanForMissingTarget(
                PilihVeneerDataMap::make($raw),
                'Pilih Veneer'
            );
        } catch (\Throwable $e) {
            Log::error('Error cek target Pilih Veneer: '.$e->getMessage());

            return [];
        }
    }

    // ------------------------------------------------------------------
    // Generic recursive scanner
    // ------------------------------------------------------------------

    /**
     * @param  array<int, array<string,mixed>>|array<string,mixed>  $data
     * @return array<int, array{divisi: string, ukuran: string, keterangan: string}>
     */
    protected function scanForMissingTarget($data, string $divisiLabel): array
    {
        $result = [];

        if (! is_array($data)) {
            return $result;
        }

        $this->recursiveScan($data, $divisiLabel, $result);

        return $result;
    }

    /**
     * @param  mixed  $node
     * @param  array<int, array{divisi: string, ukuran: string, keterangan: string}>  $result
     */
    protected function recursiveScan($node, string $divisiLabel, array &$result): void
    {
        if (! is_array($node)) {
            return;
        }

        if (array_key_exists('has_target', $node) && $node['has_target'] === false) {
            $ukuran = $node['ukuran']
                ?? $node['kode_ukuran']
                ?? $node['kode_ukuran_raw']
                ?? '-';

            $detailTambahan = [];
            if (! empty($node['jenis_kayu'])) {
                $detailTambahan[] = $node['jenis_kayu'];
            }
            if (! empty($node['kw'])) {
                $detailTambahan[] = 'KW'.$node['kw'];
            }
            if (! empty($detailTambahan)) {
                $ukuran .= ' ('.implode(', ', $detailTambahan).')';
            }

            // BARU: kalau node punya field 'mesin' (saat ini hanya diisi oleh
            // ProduksiDataMap/Rotary, karena Target di divisi itu unik per
            // id_mesin + id_ukuran — jadi item yang sama bisa "aman" di satu
            // mesin tapi "belum ada target" di mesin lain), tampilkan nama
            // mesinnya juga supaya admin tahu persis kombinasi mana yang
            // perlu dilengkapi, bukan cuma ukurannya saja.
            if (! empty($node['mesin'])) {
                $ukuran = $node['mesin'].' — '.$ukuran;
            }

            $result[] = [
                'divisi' => $divisiLabel,
                'ukuran' => (string) $ukuran,
                'keterangan' => 'Target untuk item ini tidak ditemukan di Master Target.',
            ];
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->recursiveScan($value, $divisiLabel, $result);
            }
        }
    }
}
