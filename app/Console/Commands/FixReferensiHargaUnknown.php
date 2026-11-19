<?php

namespace App\Console\Commands;

use App\Exports\Sheets\LaporanProduksiHotPressJurnalSheetV2;
use App\Models\Grade;
use App\Models\ProduksiHp;
use App\Models\ReferensiHargaProduksi;
use Illuminate\Console\Command;
use ReflectionMethod;

class FixReferensiHargaUnknown extends Command
{
    protected $signature = 'referensi:fix-unknown
                            {tanggal : Tanggal produksi YYYY-MM-DD}
                            {--tahap=semua : mentah (hasil produksi), jadi (bahan dari mutasi keluar platform/triplek), atau semua}
                            {--apply : Simpan ke database (tanpa ini hanya dry-run)}';

    protected $description = 'Buat referensi harga baru (MENTAH untuk hasil, JADI untuk bahan platform/triplek) yang UNKNOWN pada produksi hot press';

    private LaporanProduksiHotPressJurnalSheetV2 $sheet;

    /** @var array<int, string> id grade => nama grade (sudah dinormalisasi) */
    private array $gradeNama = [];

    /**
     * Panggil method private milik sheet export (logika pencarian
     * referensi sama persis dengan yang dipakai export).
     */
    private function sheetCall(string $name, ...$args)
    {
        $m = new ReflectionMethod($this->sheet, $name);
        $m->setAccessible(true);

        return $m->invoke($this->sheet, ...$args);
    }

    private function norm(?string $grade): string
    {
        return str_replace('lokal', 'local', strtolower(trim((string) $grade)));
    }

    public function handle(): int
    {
        $tanggal = $this->argument('tanggal');
        $apply = (bool) $this->option('apply');
        $opsiTahap = strtolower((string) $this->option('tahap'));

        if (! in_array($opsiTahap, ['mentah', 'jadi', 'semua'], true)) {
            $this->error('Opsi --tahap harus mentah, jadi, atau semua.');

            return self::FAILURE;
        }

        $this->sheet = new LaporanProduksiHotPressJurnalSheetV2($tanggal, 'wahana');

        $this->gradeNama = Grade::pluck('nama_grade', 'id')
            ->map(fn ($n) => $this->norm($n))
            ->all();

        $produksis = ProduksiHp::with([
            'triplekHasilHp.barangSetengahJadi.jenisBarang',
            'triplekHasilHp.barangSetengahJadi.ukuran',
            'triplekHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.barangSetengahJadi.ukuran',
            'platformHasilHp.barangSetengahJadi.grade',
            'bahanHotpress.mutasiKeluarPlatform.mutasiKeluar.jenisBarang',
            'bahanHotpress.mutasiKeluarTriplek.mutasiKeluar',
        ])->whereDate('tanggal_produksi', $tanggal)->get();

        if ($produksis->isEmpty()) {
            $this->error("Tidak ada produksi hot press pada {$tanggal}.");

            return self::FAILURE;
        }

        // ---------------------------------------------------------------
        // Kumpulkan item: tiap item = 1 kombinasi barang yang butuh referensi
        // ---------------------------------------------------------------
        $items = collect();

        foreach ($produksis as $p) {
            // HASIL produksi -> referensi MENTAH
            if (in_array($opsiTahap, ['mentah', 'semua'], true)) {
                foreach (['triplek' => $p->triplekHasilHp, 'platform' => $p->platformHasilHp] as $tipe => $daftar) {
                    foreach ($daftar as $t) {
                        $b = $t->barangSetengahJadi;
                        $u = $b->ukuran ?? null;
                        $g = $b->grade ?? null;
                        $jk = $b->jenisBarang->nama_jenis_barang ?? 'sengon';

                        $items->push([
                            'tipe' => $tipe,
                            'tahap' => 'mentah',
                            'id_ukuran' => $u?->id,
                            'jk' => $jk,
                            'kode_kayu' => strtolower($b->jenisBarang->kode_jenis_barang ?? substr($jk, 0, 1)),
                            'tebal' => (int) ($u->tebal ?? 0),
                            'gr_nama' => $g->nama_grade ?? '',
                            'id_grade' => $g?->id,
                        ]);
                    }
                }
            }

            // BAHAN dari gudang platform jadi / triplek jadi -> referensi JADI
            if (in_array($opsiTahap, ['jadi', 'semua'], true)) {
                foreach ($p->bahanHotpress as $bahan) {
                    $headerPlatform = $bahan->mutasiKeluarPlatform?->mutasiKeluar;
                    $headerTriplek = $bahan->mutasiKeluarTriplek?->mutasiKeluar;

                    if ($headerPlatform) {
                        $tipe = 'platform';
                        $header = $headerPlatform;
                        $jk = $header->jenisBarang?->nama_jenis_barang ?? 'sengon';
                        $kodeKayu = strtolower($header->jenisBarang?->kode_jenis_barang ?? substr($jk, 0, 1));
                    } elseif ($headerTriplek) {
                        $tipe = 'triplek';
                        $header = $headerTriplek;
                        $jk = $this->sheetCall('namaJenisDariHeader', $header);
                        $kodeKayu = strtolower(substr($jk, 0, 1));
                    } else {
                        continue;
                    }

                    $pjg = (float) ($header->panjang ?? 0);
                    $lbr = (float) ($header->lebar ?? 0);
                    $tbl = (float) ($header->tebal ?? 0);
                    $grNama = (string) ($header->kw_grade ?? '');
                    $dasar = $tipe === 'platform' ? 'Platform' : 'Plywood';

                    $items->push([
                        'tipe' => $tipe,
                        'tahap' => 'jadi',
                        'id_ukuran' => $this->sheetCall('resolveIdUkuranByDimensi', $pjg, $lbr, $tbl),
                        'jk' => $jk,
                        'kode_kayu' => $kodeKayu,
                        'tebal' => (int) $tbl,
                        'gr_nama' => $grNama,
                        'id_grade' => $this->sheetCall('getIdGradeTahap', $grNama, $dasar, 'jadi'),
                    ]);
                }
            }
        }

        $sudah = [];
        $dibuat = 0;
        $manual = 0;

        foreach ($items as $it) {
            $tipe = $it['tipe'];
            $tahap = $it['tahap'];
            $idUkuran = $it['id_ukuran'];
            $jk = $it['jk'];
            $grNama = $it['gr_nama'];
            $idGrade = $it['id_grade'];
            $tebal = $it['tebal'];
            $dasar = $tipe === 'platform' ? 'Platform' : 'Plywood';

            $idKayu = $this->sheetCall('resolveIdJenisKayu', $jk);

            // Satu kombinasi cukup diproses sekali
            $key = "{$tahap}|{$tipe}|{$idUkuran}|{$idKayu}|{$grNama}";
            if (isset($sudah[$key])) {
                continue;
            }

            // Cek referensi pada tahap yang SAMA (tanpa fallback ke mentah)
            if ($idUkuran) {
                $ref = $this->sheetCall('fetchReferensi', $tipe, $idUkuran, $idKayu, $grNama, $idGrade, $tahap);
                if ($ref) {
                    continue;
                }
            }

            $sudah[$key] = true;

            $label = strtoupper($tahap);
            $this->warn("UNKNOWN {$label}: {$tipe} | ukuran={$idUkuran} | kayu={$jk}({$idKayu}) | grade={$grNama}({$idGrade}) | tebal={$tebal}");

            if (! $idUkuran) {
                $this->error('  -> ukuran tidak ditemukan di master ukuran, buat manual.');
                $manual++;

                continue;
            }

            $idKategoriTarget = $this->sheetCall('getIdKategoriTahap', $dasar, $tahap);
            if (! $idKategoriTarget) {
                $this->error("  -> kategori \"{$dasar} ".ucfirst($tahap).'" belum ada di master kategori_barang, buat manual.');
                $manual++;

                continue;
            }

            // Template: tahap yang sama dulu. Untuk JADI, kalau belum ada,
            // pakai template MENTAH (harga & akun disalin -> WAJIB direview).
            $template = $this->cariTemplate($tipe, $tahap, $idUkuran, $idKayu, $grNama, $dasar);
            $dariMentah = false;

            if (! $template && $tahap === 'jadi') {
                $template = $this->cariTemplate($tipe, 'mentah', $idUkuran, $idKayu, $grNama, $dasar);
                $dariMentah = (bool) $template;
            }

            if (! $template) {
                $this->error('  -> tidak ada template referensi yang cocok, buat manual.');
                $manual++;

                continue;
            }

            $labelGrade = str_replace('local', 'lokal', $this->norm($grNama));
            // MTH = singkatan "mentah". Varian mentah berakhiran " MTH",
            // varian jadi berakhiran " JADI" (tanpa MTH).
            $akhiran = $tahap === 'jadi' ? 'JADI' : 'MTH';

            $nama = $tipe === 'platform'
                ? "platform {$tebal} {$labelGrade} {$akhiran}"
                : "{$tebal}{$it['kode_kayu']} {$labelGrade} {$akhiran}";

            $baru = [
                'nama' => $nama,
                'id_ukuran' => $idUkuran,
                'id_jenis_kayu' => $tipe === 'platform' ? null : $idKayu,
                'id_kategori_barang' => $idKategoriTarget,
                'id_grade' => $idGrade ?? $template->id_grade,
                'harga' => $template->harga,
                'id_sub_anak_akun' => $template->id_sub_anak_akun,
                'created_by' => $template->created_by,
            ];

            $this->line("  template : id {$template->id} ({$template->nama}) harga {$template->harga}");
            $this->line('  baru     : '.json_encode($baru));

            if ($dariMentah) {
                $this->warn('  ! template dari MENTAH: harga & id_sub_anak_akun disalin dari mentah, update ke harga/akun JADI yang benar.');
            }

            if ($apply) {
                ReferensiHargaProduksi::create($baru);
                $dibuat++;
                $this->info('  -> dibuat.');
            }
        }

        $this->newLine();

        if ($sudah === []) {
            $this->info('Semua barang sudah punya referensi pada tahap yang diminta. Tidak ada UNKNOWN.');

            return self::SUCCESS;
        }

        $this->info($apply
            ? "Selesai. {$dibuat} referensi dibuat, {$manual} perlu manual."
            : "Dry-run selesai ({$manual} perlu manual). Tambahkan --apply untuk menyimpan.");

        return self::SUCCESS;
    }

    /**
     * Cari template: referensi pada KATEGORI tahap yang diminta, ukuran sama,
     * NAMA grade sama (id grade beda per kategori), dan untuk triplek kayu sama.
     * Dibatasi per kategori supaya template JADI tidak tercampur dengan MENTAH
     * (nama "platform ..." sama-sama diawali "platform" di kedua tahap).
     */
    private function cariTemplate(string $tipe, string $tahap, int $idUkuran, ?int $idKayu, string $grNama, string $dasar): ?ReferensiHargaProduksi
    {
        $idKategori = $this->sheetCall('getIdKategoriTahap', $dasar, $tahap);
        if (! $idKategori) {
            return null;
        }

        return ReferensiHargaProduksi::where('id_ukuran', $idUkuran)
            ->where('id_kategori_barang', $idKategori)
            ->get()
            ->filter(function ($r) use ($tipe, $idKayu, $grNama) {
                $namaGrade = $this->gradeNama[$r->id_grade] ?? '';

                return $namaGrade === $this->norm($grNama)
                    && ($tipe !== 'triplek' || $r->id_jenis_kayu == $idKayu);
            })
            ->first();
    }
}
