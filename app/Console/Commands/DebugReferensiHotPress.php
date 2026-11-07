<?php

namespace App\Console\Commands;

use App\Exports\Sheets\LaporanProduksiHotPressJurnalSheetV2;
use App\Models\Grade;
use App\Models\ProduksiHp;
use App\Models\ReferensiHargaProduksi;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use ReflectionMethod;

/**
 * Menelusuri langkah demi langkah kenapa referensi harga sebuah barang
 * UNKNOWN / jatuh ke fallback / salah pilih. READ-ONLY, tidak mengubah data.
 *
 *   php artisan referensi:debug 2026-10-27
 *   php artisan referensi:debug 2026-10-27 --veneer
 *   php artisan referensi:debug 2026-10-27 --semua --domain=wijaya
 */
class DebugReferensiHotPress extends Command
{
    protected $signature = 'referensi:debug
                            {tanggal : Tanggal produksi YYYY-MM-DD}
                            {--domain=wahana : wahana atau wijaya}
                            {--veneer : Tampilkan juga referensi yang terpilih untuk bahan VENEER}
                            {--semua : Tampilkan juga yang OK (default hanya UNKNOWN / fallback)}';

    protected $description = 'Debug pencarian referensi_harga_produksi untuk produksi hot press (read-only)';

    private LaporanProduksiHotPressJurnalSheetV2 $sheet;

    /** @var array<int, string> id grade => nama grade asli */
    private array $gradeNama = [];

    private function sheetCall(string $name, ...$args)
    {
        $m = new ReflectionMethod($this->sheet, $name);
        $m->setAccessible(true);

        return $m->invoke($this->sheet, ...$args);
    }

    public function handle(): int
    {
        $tanggal = $this->argument('tanggal');
        $this->sheet = new LaporanProduksiHotPressJurnalSheetV2($tanggal, (string) $this->option('domain'));
        $this->gradeNama = Grade::pluck('nama_grade', 'id')->all();

        $produksis = ProduksiHp::with([
            'triplekHasilHp.barangSetengahJadi.jenisBarang',
            'triplekHasilHp.barangSetengahJadi.ukuran',
            'triplekHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.barangSetengahJadi.ukuran',
            'platformHasilHp.barangSetengahJadi.grade',
            'bahanHotpress.mutasiKeluarPalet.mutasiKeluar.jenisKayu',
            'bahanHotpress.mutasiKeluarPlatform.mutasiKeluar.jenisBarang',
            'bahanHotpress.mutasiKeluarTriplek.mutasiKeluar',
        ])->whereDate('tanggal_produksi', $tanggal)->get();

        if ($produksis->isEmpty()) {
            $this->error("Tidak ada produksi hot press pada {$tanggal}.");

            return self::FAILURE;
        }

        $this->info("Domain: {$this->option('domain')} | Produksi: {$produksis->count()}");

        $items = $this->kumpulkanItem($produksis);
        $this->debugTriplekPlatform($items);

        if ($this->option('veneer')) {
            $this->debugVeneer($produksis);
        }

        return self::SUCCESS;
    }

    // =====================================================================
    // Kumpulkan barang triplek / platform (hasil = mentah, bahan = jadi)
    // =====================================================================

    private function kumpulkanItem(Collection $produksis): array
    {
        $items = [];

        $tambah = function (array $it) use (&$items) {
            $key = implode('|', [$it['tahap'], $it['tipe'], $it['id_ukuran'], $it['jk'], strtolower($it['gr_nama'])]);
            if (! isset($items[$key])) {
                $items[$key] = $it + ['jumlah' => 0, 'sumber' => []];
            }
            $items[$key]['jumlah']++;
            $items[$key]['sumber'][$it['sumber_label']] = true;
        };

        foreach ($produksis as $p) {
            foreach (['triplek' => $p->triplekHasilHp, 'platform' => $p->platformHasilHp] as $tipe => $daftar) {
                foreach ($daftar as $t) {
                    $b = $t->barangSetengahJadi;
                    $u = $b->ukuran ?? null;
                    $g = $b->grade ?? null;

                    $tambah([
                        'tipe' => $tipe,
                        'tahap' => 'mentah',
                        'sumber_label' => "hasil {$tipe}",
                        'id_ukuran' => $u?->id,
                        'jk' => $b->jenisBarang->nama_jenis_barang ?? 'sengon',
                        'tebal' => (int) ($u->tebal ?? 0),
                        'gr_nama' => $g->nama_grade ?? '',
                        'id_grade' => $g?->id,
                    ]);
                }
            }

            foreach ($p->bahanHotpress as $bahan) {
                $hp = $bahan->mutasiKeluarPlatform?->mutasiKeluar;
                $ht = $bahan->mutasiKeluarTriplek?->mutasiKeluar;

                if ($hp) {
                    $tipe = 'platform';
                    $header = $hp;
                    $jk = $header->jenisBarang?->nama_jenis_barang ?? 'sengon';
                } elseif ($ht) {
                    $tipe = 'triplek';
                    $header = $ht;
                    $jk = $this->sheetCall('namaJenisDariHeader', $header);
                } else {
                    continue;
                }

                $pjg = (float) ($header->panjang ?? 0);
                $lbr = (float) ($header->lebar ?? 0);
                $tbl = (float) ($header->tebal ?? 0);
                $grNama = (string) ($header->kw_grade ?? '');
                $dasar = $tipe === 'platform' ? 'Platform' : 'Plywood';

                $tambah([
                    'tipe' => $tipe,
                    'tahap' => 'jadi',
                    'sumber_label' => "bahan {$tipe} (mutasi keluar)",
                    'id_ukuran' => $this->sheetCall('resolveIdUkuranByDimensi', $pjg, $lbr, $tbl),
                    'jk' => $jk,
                    'tebal' => (int) $tbl,
                    'gr_nama' => $grNama,
                    'id_grade' => $this->sheetCall('getIdGradeTahap', $grNama, $dasar, 'jadi'),
                ]);
            }
        }

        return array_values($items);
    }

    // =====================================================================
    // Trace: kategori -> ukuran -> kayu -> grade -> domain
    // =====================================================================

    private function trace(string $tipe, string $tahap, array $it, ?int $idKayu): array
    {
        $langkah = [
            'ukuran' => fn ($c) => $this->sheetCall('filterByUkuran', $c, $it['id_ukuran']),
            'kayu' => fn ($c) => $this->sheetCall('filterByKayu', $c, $idKayu),
            'grade' => fn ($c) => $this->sheetCall('filterByGrade', $c, $it['gr_nama'], $it['id_grade']),
            'domain' => fn ($c) => $this->sheetCall('filterByDomain', $c),
        ];

        $c = $this->sheetCall('baseQuery', $tipe, $tahap)->get();
        $jumlah = ['kategori' => $c->count()];
        $gagal = $c->isEmpty() ? 'kategori' : null;
        $sebelum = $c;

        if (! $gagal) {
            foreach ($langkah as $nama => $fn) {
                $sebelum = $c;
                $c = $fn($c);
                $jumlah[$nama] = $c->count();
                if ($c->isEmpty()) {
                    $gagal = $nama;
                    break;
                }
            }
        }

        return ['jumlah' => $jumlah, 'gagal' => $gagal, 'sebelum' => $sebelum, 'ref' => $gagal ? null : $c->first()];
    }

    private function debugTriplekPlatform(array $items): void
    {
        $jumlahBermasalah = 0;

        foreach ($items as $it) {
            $tipe = $it['tipe'];
            $tahap = $it['tahap'];
            $idKayu = $this->sheetCall('resolveIdJenisKayu', $it['jk']);

            $hasil = $this->trace($tipe, $tahap, $it, $idKayu);
            $status = $hasil['ref'] ? 'OK' : 'UNKNOWN';
            $fallback = null;

            if (! $hasil['ref'] && $tahap === 'jadi') {
                $fallback = $this->trace($tipe, 'mentah', $it, $idKayu);
                $status = $fallback['ref'] ? 'FALLBACK ke MENTAH' : 'UNKNOWN';
            }

            if ($status === 'OK' && ! $this->option('semua')) {
                continue;
            }

            if ($status !== 'OK') {
                $jumlahBermasalah++;
            }

            $gradeLabel = $it['gr_nama'].'('.($it['id_grade'] ?? 'null').')';
            $sumber = implode(', ', array_keys($it['sumber']));
            $header = strtoupper($tahap)." {$tipe} | ukuran=".($it['id_ukuran'] ?? 'null')." | kayu={$it['jk']}({$idKayu}) | grade={$gradeLabel} | tebal={$it['tebal']} | x{$it['jumlah']} [{$sumber}]";

            if ($status === 'OK') {
                $this->line("<info>[OK]</info> {$header}");
                $this->line("      -> id {$hasil['ref']->id} \"{$hasil['ref']->nama}\" harga {$hasil['ref']->harga} akun {$hasil['ref']->id_sub_anak_akun}");

                continue;
            }

            $this->newLine();
            $this->line("<error>[{$status}]</error> {$header}");
            $this->line('   alur ('.$tahap.'): '.$this->ringkasJumlah($hasil['jumlah']));

            if ($hasil['gagal'] === 'kategori') {
                $this->warn("   Tidak ada referensi di kategori tahap {$tahap} (kategori belum ada / belum ada baris).");
            } else {
                $this->warn("   Gagal di langkah: {$hasil['gagal']}. Kandidat sebelum langkah itu:");
                $this->tabelKandidat($hasil['sebelum'], $hasil['gagal'] === 'ukuran' ? $it['tebal'] : null);
            }

            if ($fallback) {
                if ($fallback['ref']) {
                    $this->line("   fallback MENTAH: id {$fallback['ref']->id} \"{$fallback['ref']->nama}\" harga {$fallback['ref']->harga}");
                } else {
                    $this->line('   fallback MENTAH juga gagal: '.$this->ringkasJumlah($fallback['jumlah']).' (gagal di '.$fallback['gagal'].')');
                }
            }
        }

        $this->newLine();
        $this->info($jumlahBermasalah === 0
            ? 'Semua triplek/platform punya referensi pada tahap yang benar.'
            : "{$jumlahBermasalah} kombinasi bermasalah.");
    }

    private function ringkasJumlah(array $jumlah): string
    {
        return implode(' -> ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($jumlah), $jumlah));
    }

    private function tabelKandidat(Collection $c, ?int $tebal = null): void
    {
        $tampil = $c;

        // Kalau gagal di ukuran, kandidatnya satu kategori penuh -> saring yang namanya memuat tebal itu
        if ($tebal) {
            $mirip = $c->filter(fn ($r) => (bool) preg_match('/(^|\s)'.$tebal.'(\D|$)/i', (string) $r->nama));
            if ($mirip->isNotEmpty()) {
                $tampil = $mirip;
            }
        }

        $this->tabel($tampil);
    }

    private function tabel(Collection $c, int $batas = 15): void
    {
        if ($c->isEmpty()) {
            $this->line('   (kosong)');

            return;
        }

        $this->table(
            ['id', 'nama', 'kat', 'ukuran', 'kayu', 'grade', 'harga', 'sub_akun'],
            $c->take($batas)->map(fn ($r) => [
                $r->id, $r->nama, $r->id_kategori_barang, $r->id_ukuran, $r->id_jenis_kayu,
                $r->id_grade.' ('.($this->gradeNama[$r->id_grade] ?? '-').')',
                $r->harga, $r->id_sub_anak_akun,
            ])->all()
        );

        if ($c->count() > $batas) {
            $this->line('   ... dan '.($c->count() - $batas).' lainnya');
        }
    }

    // =====================================================================
    // Veneer: referensi yang DIPILIH sheet vs kandidat yang sesuai
    // =====================================================================

    private function debugVeneer(Collection $produksis): void
    {
        $this->newLine();
        $this->info('=== VENEER (referensi yang dipilih resolveRefBahan vs kandidat sesuai tebal/kw) ===');

        $grup = [];
        foreach ($produksis as $p) {
            foreach ($p->bahanHotpress as $bahan) {
                $h = $bahan->mutasiKeluarPalet?->mutasiKeluar;
                if (! $h) {
                    continue;
                }
                $jk = $h->jenisKayu?->nama_kayu ?? 'sengon';
                $tebal = (float) ($h->tebal ?? 0);
                $gr = (string) ($h->kw_grade ?? '');
                $key = "{$jk}|{$tebal}|{$gr}";
                $grup[$key] ??= ['jk' => $jk, 'id_kayu' => $h->id_jenis_kayu ?? null, 'tebal' => $tebal, 'gr' => $gr, 'n' => 0];
                $grup[$key]['n']++;
            }
        }

        if ($grup === []) {
            $this->line('Tidak ada bahan veneer.');

            return;
        }

        $idSengon = $this->sheetCall('getIdSengon');
        $idMeranti = $this->sheetCall('getIdMeranti');
        $katJadi = $this->sheetCall('getIdKategoriBarang', 'Veneer Jadi');
        $katKering = $this->sheetCall('getIdKategoriBarang', 'Veneer Kering');
        $katAfalan = $this->sheetCall('getIdKategoriBarang', 'Veneer Afalan');

        foreach ($grup as $g) {
            $kayuLookup = ($g['id_kayu'] && $g['id_kayu'] === $idSengon) ? $idSengon : $idMeranti;
            $isAf = str_contains(strtolower($g['gr']), 'af');
            $ref = $this->sheetCall('resolveRefBahan', null, $g['id_kayu'], $g['gr']);

            $this->newLine();
            $this->line("<comment>{$g['jk']} (id kayu ".($g['id_kayu'] ?? 'null').", dicari sbg id {$kayuLookup}) | tebal {$g['tebal']} | kw {$g['gr']} | x{$g['n']}</comment>");
            $this->line('   DIPILIH: '.($ref
                ? "id {$ref->id} \"{$ref->nama}\" kat {$ref->id_kategori_barang} harga {$ref->harga} akun {$ref->id_sub_anak_akun}"
                : 'tidak ada (UNKNOWN)'));

            if ($isAf) {
                continue;
            }

            $kw = is_numeric($g['gr']) ? (int) $g['gr'] : null;
            $kandidat = ReferensiHargaProduksi::whereIn('id_kategori_barang', array_filter([$katJadi, $katKering]))
                ->where('id_jenis_kayu', $kayuLookup)
                ->get()
                ->filter(function ($r) use ($g, $kw) {
                    if ($r->t_min !== null && $r->t_max !== null) {
                        $tebalOk = $g['tebal'] < 1 ? ((float) $r->t_max <= 1.0) : ((float) $r->t_min >= 1.0);
                        if (! $tebalOk || $g['tebal'] < (float) $r->t_min || $g['tebal'] > (float) $r->t_max) {
                            return false;
                        }
                    }

                    return $kw === null || $r->kw_min === null || ($r->kw_min <= $kw && $kw <= $r->kw_max);
                });

            $this->line('   KANDIDAT sesuai tebal & kw (kategori Veneer Jadi/Kering, kayu '.$kayuLookup.'):');
            $this->tabel($kandidat);
        }

        $this->newLine();
        $this->line("Kategori: Veneer Jadi={$katJadi}, Veneer Kering={$katKering}, Veneer Afalan={$katAfalan}");
    }
}
