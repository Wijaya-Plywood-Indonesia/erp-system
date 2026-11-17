<?php

namespace App\Services;

use App\Models\BahanPenolongProduksi;
use App\Models\DetailBongkarKedi;
use App\Models\DetailHasil;
use App\Models\DetailHasilPaletRotary;
use App\Models\GudangVeneerJadi;
use App\Models\DetailHasilRepair;
use App\Models\HargaPegawai;
use App\Models\HppVeneerBasahLog;
use App\Models\HppVeneerBasahSummary;
use App\Models\HppVeneerJadiLog;
use App\Models\Mesin;
use App\Models\OngkosProduksiDryer;
use App\Models\ProduksiKedi;
use App\Models\ProduksiPressDryer;
use App\Models\ValidasiKedi;
use App\Models\ValidasiPressDryer;
use App\Models\ProduksiJoint;
use App\Models\ProduksiRepair;
use App\Models\SerahTerimaVeneerKering;
use App\Models\StokVeneerJadi;
use App\Models\StokVeneerKering;
use App\Support\Hpp\KomponenHpp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * HPP VENEER JADI
 * ─────────────────────────────────────────────────────────────────────────────
 * Prinsip tunggal untuk semua jalur (nilai tidak boleh hilang atau muncul begitu saja):
 *
 *   HPP jadi / m³ = ( Σ m³ input × HPP input / m³  +  biaya proses )  ÷  m³ hasil
 *
 *   • HPP input  : tergantung asal veneer (lihat komponenInput):
 *        dryer / kedi → HPP batch itu sendiri (BEDA per mesin, tidak dirata-rata)
 *        gudang kering → HPP Average veneer kering
 *        gudang jadi   → HPP Average veneer jadi
 *        joint         → HPP hasil joint
 *   • Biaya proses (hanya repair / joint): pekerja + bahan penolong (tepung, lem pai, dll).
 *   • Susut ikut terserap: m³ hasil < m³ input ⇒ HPP per m³ hasil naik, semua komponen
 *     diskalakan sama (kayu, pekerja, mesin, bahan).
 *
 * Jalur:
 *   1. Kering langsung → jadi : HPP = HPP input (tanpa biaya proses).
 *   2. Kering/jadi → REPAIR → jadi : komponenRepair()  (pekerja + bahan penolong)
 *   3. Kering → JOINT → jadi       : komponenJoint()   (pekerja + MESIN + bahan penolong)
 *
 * Hanya hasil JOINT yang masuk veneer jadi. Sanding Joint & Pot Af Joint tidak dihitung terpisah.
 * Biaya mesin hanya ada di jalur joint (master `mesins`, kategori JOINT).
 *
 * Setelah HPP satu kali masuk dihitung, catatMasuk() menggabungkannya ke stok veneer jadi
 * dengan MOVING AVERAGE per (jenis kayu, ukuran, KW) — termasuk rata-rata rincian komponen,
 * asal lahan, dan asal mesin.
 */
class HppVeneerJadiService
{
    /** panjang (cm) × lebar (cm) × tebal (mm) × lembar ÷ 10.000.000 = m³ (sama dengan seluruh ERP). */
    public const PEMBAGI_M3 = 10_000_000;

    /** Sama dengan HppDryerService::JAM_NORMAL_DEFAULT. Ganti bila repair/joint punya target jam sendiri. */
    private const JAM_NORMAL = 10;

    /**
     * Nama kategori mesin (tabel kategori_mesins) yang ongkosnya dibebankan ke produksi joint.
     * Tambahkan 'SANDING JOINT' / 'POT AFALAN JOINT' di sini bila mesin kedua kategori itu
     * memang harus ikut dibebankan ke veneer jadi.
     */
    private const KATEGORI_MESIN_JOINT = ['JOINT'];

    /**
     * Kolom master mesin yang dibebankan ke joint. 'penyusutan' = sama dengan komponen M pada
     * log HPP veneer basah (penyusutan mesin per hari produksi, dibebankan penuh satu kali).
     * Ganti ke 'ongkos_mesin' bila ingin memakai kolom ongkos seperti dryer/kedi.
     */
    private const KOLOM_MESIN_JOINT = 'penyusutan';

    /** Penanda yang sama dengan log HPP veneer basah (HppVeneerBasahIsiService::TANDA_SEMENTARA). */
    public const TANDA_SEMENTARA = '[HPP SEMENTARA]';

    /** @var string[] catatan/peringatan selama perhitungan (angka estimasi, harga bahan kosong, dst.) */
    private array $catatan = [];

    public function ambilCatatan(): array
    {
        $c = array_values(array_unique($this->catatan));
        $this->catatan = [];

        return $c;
    }

    // =====================================================================
    // A. VENEER KERING — rincian komponen tiap baris MASUK dari produksi
    // =====================================================================

    /**
     * Isi kolom `komposisi` + `tipe_mesin` pada satu baris stok_veneer_kerings hasil produksi
     * (Press Dryer / Kedi). Panggil setelah HppDryerService::terapkanHppPalet() / terapkanHppKedi().
     *
     * Total rincian selalu dipaksa sama dengan hpp_kering_per_m3 baris itu (lihat rekonsiliasi).
     */
    public function isiKomposisiKering(StokVeneerKering $row): KomponenHpp
    {
        $idKayu     = (int) $row->id_jenis_kayu;
        $basahTotal = (float) $row->hpp_veneer_basah_per_m3;
        $p          = $this->proporsiBasah($idKayu);

        $komp = [
            'kayu'           => $basahTotal * $p['kayu'],
            'pekerja_rotary' => $basahTotal * $p['pekerja'],
            'mesin_rotary'   => $basahTotal * $p['mesin'],
            'bahan_rotary'   => $basahTotal * $p['bahan'],
            'lainnya'        => $basahTotal * $p['sisa'],
            'pekerja_kering' => (float) $row->ongkos_pegawai_per_m3,
            'mesin_kering'   => (float) $row->ongkos_mesin_per_m3,
        ];

        $tipe = $row->id_detail_bongkar_kedi ? 'kedi' : 'dryer';

        $acuanLama          = $this->tanggalAcuan;
        $this->tanggalAcuan = $row->tanggal_transaksi ? Carbon::parse($row->tanggal_transaksi)->toDateString() : $acuanLama;
        $lahan              = $this->lahanBasah($idKayu);
        $this->tanggalAcuan = $acuanLama;

        $k = (new KomponenHpp($komp))
            ->denganMesin($tipe)
            ->denganLahan($lahan)
            ->rekonsiliasi((float) $row->hpp_kering_per_m3);

        $row->forceFill(['komposisi' => $k->toArray(), 'tipe_mesin' => $tipe])->saveQuietly();

        return $k;
    }

    /**
     * Rincian rata-rata veneer kering di gudang untuk satu (ukuran, jenis kayu, KW).
     * Stok yang tersisa dianggap tersusun dari pemasukan TERBARU (pendekatan), lalu total
     * dipaksa sama dengan HPP Average yang tersimpan.
     */
    public function komposisiKeringAverage(int $idUkuran, int $idJenisKayu, string $kw): KomponenHpp
    {
        $snap   = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);
        $stokM3 = (float) $snap['stok_m3'];
        $avg    = (float) $snap['hpp_average'];

        if ($stokM3 <= 0) {
            return new KomponenHpp(['lainnya' => $avg]);
        }

        $rows = StokVeneerKering::forProduk($idUkuran, $idJenisKayu, $kw)
            ->where('jenis_transaksi', 'masuk')
            ->where('hpp_kering_per_m3', '>', 0)
            ->orderByDesc('tanggal_transaksi')->orderByDesc('id')
            ->limit(200)
            ->get();

        $sisa   = $stokM3;
        $bagian = [];

        foreach ($rows as $row) {
            if ($sisa <= 0.000001) {
                break;
            }
            $ambil = min($sisa, (float) $row->m3);

            if ($row->id_detail_hasil_dryer || $row->id_detail_bongkar_kedi) {
                // hasil produksi dryer/kedi: rincian dihitung bila belum ada (tidak perlu menjalankan perintah isi-komposisi dulu)
                $kompRow = $this->komposisiRow($row);
            } else {
                $kompRow = $row->komposisi
                    ? KomponenHpp::dari($row->komposisi)
                    : new KomponenHpp(['lainnya' => (float) $row->hpp_kering_per_m3]);
            }

            $bagian[] = [$ambil, $kompRow];
            $sisa -= $ambil;
        }

        if ($sisa > 0.000001) {
            $bagian[] = [$sisa, KomponenHpp::kosong()]; // stok awal / tak tertelusur → jatuh ke "lainnya"
        }

        return KomponenHpp::gabung($bagian)->rekonsiliasi($avg);
    }

    /** Rata-rata rincian veneer jadi di stok (untuk veneer yang keluar dari gudang jadi). */
    public function komposisiJadi(?StokVeneerJadi $stok): KomponenHpp
    {
        if (! $stok) {
            return KomponenHpp::kosong();
        }

        return KomponenHpp::dari($stok->komposisi)->rekonsiliasi((float) $stok->hpp_average);
    }

    // =====================================================================
    // B. HPP INPUT — dari mana veneer yang masuk ke repair / joint / jadi
    // =====================================================================

    /** HPP per m³ (beserta rincian, lahan, mesin) dari satu serah terima veneer. */
    public function komponenInput(SerahTerimaVeneerKering $st): KomponenHpp
    {
        switch ($st->tipe_sumber) {
            case 'dryer':
                if (! $st->id_detail_hasil) {
                    return $this->inputYatim($st);
                }
                $row = StokVeneerKering::where('id_detail_hasil_dryer', $st->id_detail_hasil)
                    ->where('jenis_transaksi', 'masuk')->orderBy('id')->first();

                return $row ? $this->komposisiRow($row) : $this->komponenPaletLangsung($st);

            case 'kedi':
                if (! $st->id_detail_bongkar_kedi) {
                    return $this->inputYatim($st);
                }
                $row = StokVeneerKering::where('id_detail_bongkar_kedi', $st->id_detail_bongkar_kedi)
                    ->where('jenis_transaksi', 'masuk')->orderBy('id')->first();

                return $row ? $this->komposisiRow($row) : $this->komponenPaletLangsung($st);

            case 'gudang':
                $palet = $st->mutasiKeluarPalet;
                if (! $palet) {
                    break;
                }

                return $this->komposisiKeringAverage((int) $palet->id_ukuran, (int) $palet->id_jenis_kayu, (string) $palet->kw);

            case 'gudang_jadi':
                if (isset($this->komposisiKeluar[$st->id])) {
                    return $this->komposisiKeluar[$st->id];
                }
                $m = $st->mutasiKeluarPaletJadi?->mutasiKeluar;
                if (! $m) {
                    break;
                }
                $stok = StokVeneerJadi::where('id_jenis_kayu', $m->id_jenis_kayu)
                    ->where('panjang', $m->panjang)->where('lebar', $m->lebar)->where('tebal', $m->tebal)
                    ->where('kw_grade', (string) $m->kw_grade)->first();

                return $this->komposisiJadi($stok);

            case 'joint':
                $produksi = $st->hasilJoint?->produksiJoint;
                if (! $produksi) {
                    break;
                }

                return $this->komponenJoint($produksi);
        }

        $this->catatan[] = "Serah terima #{$st->id} (sumber {$st->tipe_sumber}): HPP input tidak ditemukan, dianggap Rp 0.";

        return KomponenHpp::kosong();
    }

    /**
     * Serah terima yang palet sumbernya sudah terhapus (FK ON DELETE SET NULL). Tidak boleh
     * dicari lewat where(kolom, null) — itu akan cocok ke baris stok mana pun yang kolomnya NULL.
     */
    private function inputYatim(SerahTerimaVeneerKering $st): KomponenHpp
    {
        $this->catatan[] = "Serah terima #{$st->id}: palet sumber ({$st->tipe_sumber}) sudah terhapus, HPP input tidak bisa dilacak: Rp 0, ditandai sementara.";

        return KomponenHpp::kosong()->tandaiSementara();
    }

    private function komposisiRow(StokVeneerKering $row): KomponenHpp
    {
        $k = $row->komposisi ? KomponenHpp::dari($row->komposisi) : null;

        // komposisi basi (HPP kering baris itu berubah setelah disimpan) → hitung ulang
        if (! $k || abs($k->total() - (float) $row->hpp_kering_per_m3) > 1) {
            $k = $this->isiKomposisiKering($row);
        }

        if ($k->total() <= 0) {
            $this->catatan[] = "Baris stok kering #{$row->id}: HPP kering belum dihitung (produksi dryer/kedi belum divalidasi). HPP input Rp 0, ditandai sementara.";

            return $k->tandaiSementara();
        }

        return $k;
    }

    /** Tanggal transaksi yang sedang dihitung (YYYY-MM-DD). Dipakai agar asal lahan memakai pemasukan basah SEBELUM tanggal itu. */
    private ?string $tanggalAcuan = null;

    /** @var array<int,KomponenHpp> serah terima id → rincian stok jadi saat veneer itu KELUAR (diisi saat hitung ulang riwayat) */
    private array $komposisiKeluar = [];

    /** @var array<string,array{pegawai: float, mesin: float, final: bool}> cache ongkos per produksi */
    private array $cacheOngkos = [];

    /**
     * Palet dryer/kedi yang diserahkan LANGSUNG ke veneer jadi tidak pernah punya baris di
     * stok_veneer_kerings, jadi HPP kering-nya tidak pernah dihitung. Di sini dihitung dari data
     * produksinya: HPP basah (modal) + pegawai/m³ + mesin/m³ — rumus yang sama dengan HppDryerService.
     * Ditandai SEMENTARA bila produksinya belum divalidasi.
     */
    private function komponenPaletLangsung(SerahTerimaVeneerKering $st): KomponenHpp
    {
        $dryer = app(HppDryerService::class);

        if ($st->tipe_sumber === 'dryer') {
            $dh = DetailHasil::with('ukuran')->find($st->id_detail_hasil);
            if (! $dh) {
                return $this->fallbackKering($st);
            }

            $kunci = 'dryer:'.$dh->id_produksi_dryer;
            if (! isset($this->cacheOngkos[$kunci])) {
                $produksi = ProduksiPressDryer::with(['detailHasils.ukuran', 'detailMesins.mesin', 'detailPegawais'])->find($dh->id_produksi_dryer);
                if (! $produksi) {
                    return $this->fallbackKering($st);
                }
                $komp = $dryer->komponenProduksi($produksi);
                $m3   = (float) $komp['m3_total'];
                $o    = OngkosProduksiDryer::where('id_produksi_dryer', $produksi->id)->first();
                $rpPegawai = $o && $o->is_final ? (float) $o->ongkos_pekerja : (float) $komp['pegawai'];
                $rpMesin   = $o && $o->is_final ? (float) $o->ongkos_mesin : (float) $komp['mesin'];

                $this->cacheOngkos[$kunci] = [
                    'pegawai' => $m3 > 0 ? $rpPegawai / $m3 : 0.0,
                    'mesin'   => $m3 > 0 ? $rpMesin / $m3 : 0.0,
                    'final'   => ValidasiPressDryer::where('id_produksi_dryer', $produksi->id)->where('status', 'divalidasi')->exists(),
                ];
            }

            $basah = $dryer->rincianBasahUntukPalet($dh);
            $o     = $this->cacheOngkos[$kunci];

            return $this->susunKomposisiLangsung((int) $dh->id_jenis_kayu, (float) $basah['hpp'], $o['pegawai'], $o['mesin'], 'dryer', ! $o['final']);
        }

        if ($st->tipe_sumber === 'kedi') {
            $dbk = DetailBongkarKedi::with('ukuran')->find($st->id_detail_bongkar_kedi);
            if (! $dbk) {
                return $this->fallbackKering($st);
            }

            $kunci = 'kedi:'.$dbk->id_produksi_kedi;
            if (! isset($this->cacheOngkos[$kunci])) {
                $produksi = ProduksiKedi::with(['detailBongkarKedi.ukuran', 'detailPegawaiKedi', 'mesin'])->find($dbk->id_produksi_kedi);
                if (! $produksi) {
                    return $this->fallbackKering($st);
                }
                $komp = $dryer->komponenKedi($produksi);
                $m3   = (float) $komp['m3_total'];

                $this->cacheOngkos[$kunci] = [
                    'pegawai' => $m3 > 0 ? (float) $komp['pegawai'] / $m3 : 0.0,
                    'mesin'   => $m3 > 0 ? (float) $komp['mesin'] / $m3 : 0.0,
                    'final'   => ValidasiKedi::where('id_produksi_kedi', $produksi->id)->where('tipe', 'bongkar')->where('status', 'divalidasi')->exists(),
                ];
            }

            $basah = $dryer->rincianBasahUntukKedi($dbk);
            $o     = $this->cacheOngkos[$kunci];

            return $this->susunKomposisiLangsung((int) $dbk->id_jenis_kayu, (float) $basah['hpp'], $o['pegawai'], $o['mesin'], 'kedi', ! $o['final']);
        }

        return $this->fallbackKering($st);
    }

    private function susunKomposisiLangsung(int $idKayu, float $basah, float $pegawaiPerM3, float $mesinPerM3, string $tipe, bool $sementara): KomponenHpp
    {
        $p = $this->proporsiBasah($idKayu);

        $k = (new KomponenHpp([
            'kayu'           => $basah * $p['kayu'],
            'pekerja_rotary' => $basah * $p['pekerja'],
            'mesin_rotary'   => $basah * $p['mesin'],
            'bahan_rotary'   => $basah * $p['bahan'],
            'lainnya'        => $basah * $p['sisa'],
            'pekerja_kering' => $pegawaiPerM3,
            'mesin_kering'   => $mesinPerM3,
        ]))->denganMesin($tipe)->denganLahan($this->lahanBasah($idKayu));

        if ($k->total() <= 0) {
            $this->catatan[] = "Palet {$tipe}: HPP veneer basah tidak ditemukan, HPP input Rp 0.";
            $sementara = true;
        }

        return $k->tandaiSementara($sementara);
    }

    private function fallbackKering(SerahTerimaVeneerKering $st): KomponenHpp
    {
        $s = $st->sumber;
        if (! $s) {
            return KomponenHpp::kosong();
        }

        $this->catatan[] = "Serah terima #{$st->id}: baris stok kering batch tidak ada, memakai HPP Average kering.";

        return $this->komposisiKeringAverage((int) $s->id_ukuran, (int) $s->id_jenis_kayu, (string) $s->kw);
    }

    // =====================================================================
    // C. HPP HASIL PRODUKSI — repair & joint
    // =====================================================================

    /**
     * HPP per m³ untuk SATU baris hasil repair (detail_hasil_repairs).
     *
     *   input   = HPP veneer yang dimasukkan di modal repair (sesuai sumbernya).
     *             Bila hasil TIDAK terhubung ke modal (id_modal_repair NULL, data lama / meja tanpa
     *             modal), semua modal produksi itu dirata-rata tertimbang m³.
     *   susut   = m³ modal ÷ m³ hasil, hanya bila modal sudah selesai (ditutup / sisa 0); kalau belum = 1.
     *   proses  = (ongkos pekerja hari itu + bahan penolong repair) ÷ total m³ hasil produksi.
     */
    public function komponenRepair(DetailHasilRepair $hasil): KomponenHpp
    {
        $produksi = $hasil->produksiRepair;

        if (! $produksi) {
            $this->catatan[] = "Hasil repair #{$hasil->id}: produksi repair tidak ditemukan.";

            return KomponenHpp::kosong();
        }

        $modal  = $hasil->modalRepair;
        $modals = $modal
            ? collect([$modal])
            : $produksi->modalRepairs()->with(['ukuran', 'serahTerimaVeneerKering'])->get();

        if (! $modal) {
            $this->catatan[] = "Hasil repair #{$hasil->id} tidak terhubung ke modal: HPP input dirata-rata dari semua modal produksi #{$produksi->id}.";
        }

        $bagian = [];
        $m3In   = 0.0;

        foreach ($modals as $m) {
            $m3 = $this->m3Ukuran($m->ukuran, (float) $m->jumlah);
            if ($m3 <= 0) {
                continue;
            }

            $st = $m->serahTerimaVeneerKering;
            if (! $st) {
                $this->catatan[] = "Modal repair #{$m->id} tanpa serah terima: HPP input Rp 0.";
            }

            $bagian[] = [$m3, $st ? $this->komponenInput($st) : KomponenHpp::kosong()];
            $m3In    += $m3;
        }

        $input = KomponenHpp::gabung($bagian);

        // susut
        $keluar  = $modal ? $modal->detailHasilRepairs()->with('ukuran')->get() : $produksi->detailHasilRepairs()->with('ukuran')->get();
        $m3Out   = $this->m3Hasil($keluar);
        $selesai = $modals->isNotEmpty() && $modals->every(
            fn ($m) => $m->ditutup_manual_at !== null || (int) $m->sisa_stok === 0
        );

        $faktor = 1.0;
        if ($selesai && $m3In > 0 && $m3Out > 0) {
            $faktor = $m3In / $m3Out;
        } elseif (! $selesai) {
            $this->catatan[] = "Modal repair produksi #{$produksi->id} belum selesai: susut belum diperhitungkan (HPP sementara).";
        }

        // biaya proses dibagi seluruh m³ hasil produksi repair hari itu
        $m3OutProduksi = $this->m3Hasil($produksi->detailHasilRepairs()->with('ukuran')->get());
        $perM3 = fn (float $rp) => $m3OutProduksi > 0 ? $rp / $m3OutProduksi : 0.0;

        return $input->skala($faktor)
            ->tambah('pekerja_proses', $perM3($this->ongkosPekerjaRepair($produksi)))
            ->tambah('bahan_proses', $perM3($this->ongkosBahanRepair($produksi)))
            ->tandaiSementara($input->sementara || ! $selesai);
    }

    /**
     * HPP per m³ hasil JOINT (satu produksi joint, dihitung gabungan karena hasil_joint
     * tidak terhubung ke modal tertentu).
     */
    public function komponenJoint(ProduksiJoint $produksi): KomponenHpp
    {
        $bagian = [];
        $m3In   = 0.0;

        foreach ($produksi->modalJoint()->with(['ukuran', 'serahTerimaVeneerKering'])->get() as $modal) {
            $m3 = $this->m3Ukuran($modal->ukuran, (float) $modal->jumlah);
            if ($m3 <= 0) {
                continue;
            }
            $st = $modal->serahTerimaVeneerKering;
            if ($st && $st->tipe_sumber === 'joint' && (int) $st->hasilJoint?->id_produksi_joint === (int) $produksi->id) {
                continue; // cegah rekursi tak berujung
            }
            $bagian[] = [$m3, $st ? $this->komponenInput($st) : KomponenHpp::kosong()];
            $m3In += $m3;
        }

        if (! $bagian) {
            $this->catatan[] = "Produksi joint #{$produksi->id}: tidak ada modal, HPP input Rp 0.";
        }

        $input = KomponenHpp::gabung($bagian);

        $m3Out = $this->m3Hasil($produksi->hasilJoint()->with('ukuran')->get());
        $final = $produksi->validasiJoint()->exists();

        $faktor = 1.0;
        if ($final && $m3In > 0 && $m3Out > 0) {
            $faktor = $m3In / $m3Out;
        } elseif (! $final) {
            $this->catatan[] = "Produksi joint #{$produksi->id} belum divalidasi: susut belum diperhitungkan (HPP sementara).";
        }

        $perM3 = fn (float $rp) => $m3Out > 0 ? $rp / $m3Out : 0.0;

        return $input->skala($faktor)
            ->tambah('pekerja_proses', $perM3($this->ongkosPegawai($produksi->pegawaiJoint, 'masuk', 'pulang')))
            ->tambah('mesin_proses', $perM3($this->ongkosMesinJoint()))
            ->tambah('bahan_proses', $perM3($this->ongkosBahanJoint($produksi)))
            ->tandaiSementara($input->sementara || ! $final);
    }

    // ---- biaya proses ---------------------------------------------------

    private function ongkosPekerjaRepair(ProduksiRepair $produksi): float
    {
        return $this->ongkosPegawai($produksi->rencanaPegawais, 'jam_masuk', 'jam_pulang');
    }

    /** Σ (harga per hari ÷ jam normal × jam kerja). Rumus sama dengan HppDryerService::ongkosPegawaiDari. */
    private function ongkosPegawai(iterable $daftar, string $kolomMasuk, string $kolomPulang): float
    {
        $harga = (float) (HargaPegawai::first()->harga ?? 0);
        if ($harga <= 0) {
            $this->catatan[] = 'Harga pegawai per hari belum diisi: ongkos pekerja Rp 0.';
        }
        $perJam = $harga / self::JAM_NORMAL;
        $total  = 0.0;

        foreach ($daftar as $p) {
            $jam = $this->jamKerja($p->{$kolomMasuk}, $p->{$kolomPulang});
            if ($jam <= 0) {
                if (filled($p->ijin ?? null)) {
                    continue; // ijin & tanpa jam kerja → tidak dihitung
                }
                $jam = self::JAM_NORMAL;
            }
            $total += $perJam * $jam;
        }

        return $total;
    }

    private function jamKerja(?string $masuk, ?string $pulang): float
    {
        if (! $masuk || ! $pulang) {
            return 0.0;
        }
        $menit = (strtotime($pulang) - strtotime($masuk)) / 60;
        if ($menit < 0) {
            $menit += 1440; // lewat tengah malam
        }

        return $menit / 60;
    }

    /**
     * Σ penyusutan mesin kategori JOINT (master `mesins`), PENUH satu kali per produksi joint.
     * Tabel produksi joint belum punya pilihan mesin, jadi semua mesin kategori itu dianggap
     * dipakai. Tiap mesin yang dibebankan dicatat di ambilCatatan() supaya angkanya kelihatan.
     */
    private function ongkosMesinJoint(): float
    {
        $daftar = Mesin::whereHas('kategoriMesin', fn ($q) => $q->whereIn('nama_kategori_mesin', self::KATEGORI_MESIN_JOINT))->get();

        if ($daftar->isEmpty()) {
            $this->catatan[] = 'Tidak ada mesin kategori JOINT di master mesin: ongkos mesin joint Rp 0.';

            return 0.0;
        }

        $total = 0.0;
        foreach ($daftar as $m) {
            $nilai = (float) $m->{self::KOLOM_MESIN_JOINT};
            $total += $nilai;
            $this->catatan[] = 'Mesin joint dibebankan: '.$m->nama_mesin.' ('.self::KOLOM_MESIN_JOINT.') Rp '.number_format($nilai, 0, ',', '.').' per produksi.';
        }

        return $total;
    }

    private function ongkosBahanRepair(ProduksiRepair $produksi): float
    {
        $total = 0.0;
        foreach ($produksi->bahanPenolongRepair()->with('bahanPenolong')->get() as $b) {
            $harga = (float) ($b->bahanPenolong->harga ?? 0);
            if ($harga <= 0) {
                $this->catatan[] = 'Harga master bahan penolong repair "'.($b->bahanPenolong->nama_bahan_penolong ?? '?').'" masih kosong/0.';
            }
            $total += $harga * (float) $b->jumlah;
        }

        return $total;
    }

    /**
     * bahan_produksi (joint) menyimpan nama bahan sebagai teks bebas ("tepung", "lem_dover"),
     * bukan FK. Dicocokkan ke master bahan_penolong_produksi kategori 'joint' lewat nama yang
     * dinormalkan (huruf kecil, "_" → spasi). Yang tidak cocok → Rp 0 + peringatan.
     */
    private function ongkosBahanJoint(ProduksiJoint $produksi): float
    {
        $master = BahanPenolongProduksi::where('kategori_produksi', 'joint')->get()
            ->mapWithKeys(fn ($b) => [$this->normalNama($b->nama_bahan_penolong) => (float) $b->harga]);

        $total = 0.0;
        foreach ($produksi->bahanProduksi as $b) {
            $kunci = $this->normalNama($b->nama_bahan);
            $harga = $master[$kunci] ?? null;

            if ($harga === null) {
                $this->catatan[] = "Bahan joint \"{$b->nama_bahan}\" tidak ada di master bahan penolong (kategori joint): Rp 0.";
                continue;
            }
            if ($harga <= 0) {
                $this->catatan[] = "Harga master bahan joint \"{$b->nama_bahan}\" masih 0.";
            }
            $total += $harga * (float) $b->jumlah;
        }

        return $total;
    }

    private function normalNama(?string $nama): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', mb_strtolower((string) $nama))));
    }

    // =====================================================================
    // D. STOK — moving average veneer jadi (termasuk rincian)
    // =====================================================================

    /**
     * Catat SATU pemasukan veneer jadi: log + stok + average rincian.
     * Menggantikan blok duplikat di StokVeneerJadiService, GudangVeneerJadi (terimaDryer,
     * terimaDariGudang), dst. Semua pemasukan memakai satuan HPP yang sama: Rp per m³.
     */
    public function catatMasuk(
        int $idJenisKayu,
        float $panjang,
        float $lebar,
        float $tebal,
        string $kw,
        float $lembar,
        KomponenHpp $komponen,
        string $jalur,
        string $keterangan,
        ?Model $referensi = null,
        null|string|\DateTimeInterface $tanggal = null,
    ): StokVeneerJadi {
        return DB::transaction(function () use (
            $idJenisKayu, $panjang, $lebar, $tebal, $kw, $lembar, $komponen, $jalur, $keterangan, $referensi, $tanggal
        ) {
            $kunci = [
                'id_jenis_kayu' => $idJenisKayu,
                'panjang'       => $panjang,
                'lebar'         => $lebar,
                'tebal'         => $tebal,
                'kw_grade'      => $kw,
            ];

            $stok = StokVeneerJadi::where($kunci)->lockForUpdate()->first()
                ?? StokVeneerJadi::create($kunci + ['stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);

            $m3            = $this->m3Dimensi($panjang, $lebar, $tebal, $lembar);
            $lembarBefore  = (int) $stok->stok_lembar;
            $m3Before      = (float) $stok->stok_kubikasi;
            $nilaiBefore   = (float) $stok->nilai_stok;

            $hppTransaksi  = $komponen->total();
            $nilaiMasuk    = $m3 * $hppTransaksi;

            $lembarAfter   = $lembarBefore + (int) round($lembar);
            $m3After       = $m3Before + $m3;
            $nilaiAfter    = $nilaiBefore + $nilaiMasuk;
            $hppAverage    = $m3After > 0 ? $nilaiAfter / $m3After : 0.0;

            $komposisiStok = KomponenHpp::dari($stok->komposisi)
                ->rekonsiliasi($m3Before > 0 ? $nilaiBefore / $m3Before : 0.0);
            $komposisiBaru = KomponenHpp::gabung([[$m3Before, $komposisiStok], [$m3, $komponen]]);

            $g = $komponen->kelompok();

            if ($komponen->sementara) {
                $keterangan = rtrim(mb_substr($keterangan, 0, 255 - mb_strlen(self::TANDA_SEMENTARA) - 1)).' '.self::TANDA_SEMENTARA;
            }

            $log = HppVeneerJadiLog::create($kunci + [
                'tanggal'              => $tanggal ? Carbon::parse($tanggal)->toDateString() : now()->toDateString(),
                'tipe_transaksi'       => 'masuk',
                'jalur'                => $jalur,
                'keterangan'           => mb_substr($keterangan, 0, 255),
                'referensi_type'       => $referensi ? $referensi::class : null,
                'referensi_id'         => $referensi?->getKey(),
                'total_lembar'         => (int) round($lembar),
                'total_kubikasi'       => round($m3, 6),
                'hpp_kayu'             => round($g['kayu'], 2),
                'hpp_pekerja'          => round($g['pekerja'], 2),
                'hpp_mesin'            => round($g['mesin'], 2),
                'hpp_bahan_penolong'   => round($g['bahan'], 2),
                'hpp_lainnya'          => round($g['lainnya'], 2),
                // Sama dengan log basah: K/P/M/B(+lainnya) = HPP transaksi ini; HPP Average = rata-rata STOK setelah transaksi.
                'hpp_average'          => round($hppAverage, 2),
                'nilai_stok'           => round($nilaiMasuk, 2),
                'stok_lembar_before'   => $lembarBefore,
                'stok_kubikasi_before' => round($m3Before, 6),
                'nilai_stok_before'    => round($nilaiBefore, 2),
                'stok_lembar_after'    => $lembarAfter,
                'stok_kubikasi_after'  => round($m3After, 6),
                'nilai_stok_after'     => round($nilaiAfter, 2),
                'komposisi'            => $komponen->toArray(),
            ]);

            $stok->update([
                'stok_lembar'             => $lembarAfter,
                'stok_kubikasi'           => round($m3After, 6),
                'nilai_stok'              => round($nilaiAfter, 2),
                'hpp_average'             => round($hppAverage, 2),
                'hpp_kayu_last'           => round($g['kayu'], 2),
                'hpp_pekerja_last'        => round($g['pekerja'], 2),
                'hpp_mesin_last'          => round($g['mesin'], 2),
                'hpp_bahan_penolong_last' => round($g['bahan'], 2),
                'komposisi'               => $komposisiBaru->toArray(),
                'id_last_log'             => $log->id,
            ]);

            return $stok->fresh();
        });
    }

    // =====================================================================
    // Helper kubikasi & proporsi basah
    // =====================================================================

    public function m3Dimensi(float $panjang, float $lebar, float $tebal, float $lembar): float
    {
        return $panjang * $lebar * $tebal * $lembar / self::PEMBAGI_M3;
    }

    private function m3Ukuran($ukuran, float $lembar): float
    {
        return $ukuran ? $this->m3Dimensi((float) $ukuran->panjang, (float) $ukuran->lebar, (float) $ukuran->tebal, $lembar) : 0.0;
    }

    private function m3Hasil(iterable $daftarHasil): float
    {
        $total = 0.0;
        foreach ($daftarHasil as $h) {
            $total += $this->m3Ukuran($h->ukuran, (float) $h->jumlah);
        }

        return $total;
    }

    /**
     * Proporsi komponen HPP veneer basah (kayu/pekerja/mesin/bahan) untuk satu jenis kayu,
     * dari komponen "last" pada hpp_veneer_basah_summaries dibobot stok m³.
     * Catatan: summary hanya menyimpan komponen TERAKHIR, bukan rata-rata — jadi ini pendekatan
     * proporsi; totalnya tetap dipaksa sama dengan HPP basah yang dipakai produksi.
     *
     * @return array{kayu: float, pekerja: float, mesin: float, bahan: float, sisa: float}
     */
    private function proporsiBasah(int $idJenisKayu): array
    {
        $s = ['kayu' => 0.0, 'pekerja' => 0.0, 'mesin' => 0.0, 'bahan' => 0.0];

        foreach (HppVeneerBasahSummary::where('id_jenis_kayu', $idJenisKayu)->where('stok_kubikasi', '>', 0)->get() as $r) {
            $w = (float) $r->stok_kubikasi;
            $s['kayu']    += (float) $r->hpp_kayu_last * $w;
            $s['pekerja'] += (float) $r->hpp_pekerja_last * $w;
            $s['mesin']   += (float) $r->hpp_mesin_last * $w;
            $s['bahan']   += (float) $r->hpp_bahan_penolong_last * $w;
        }

        $total = array_sum($s);
        if ($total <= 0) {
            return ['kayu' => 0.0, 'pekerja' => 0.0, 'mesin' => 0.0, 'bahan' => 0.0, 'sisa' => 1.0];
        }

        return [
            'kayu'    => $s['kayu'] / $total,
            'pekerja' => $s['pekerja'] / $total,
            'mesin'   => $s['mesin'] / $total,
            'bahan'   => $s['bahan'] / $total,
            'sisa'    => 0.0,
        ];
    }

    /**
     * Asal lahan veneer basah suatu jenis kayu: porsi m³ per id_lahan dari pemasukan basah
     * terbaru (log masuk palet rotary → penggunaan lahan), diambil sampai kira-kira sebesar stok basah.
     * Ini PENDEKATAN — stok basah dirata-rata tanpa jejak lahan. Untuk jejak pasti, simpan
     * komposisi pada hpp_veneer_basah_summaries (lihat dokumen rancangan).
     *
     * @return array<string,float>
     */
    private function lahanBasah(int $idJenisKayu): array
    {
        $stokM3 = (float) HppVeneerBasahSummary::where('id_jenis_kayu', $idJenisKayu)->sum('stok_kubikasi');

        $logs = HppVeneerBasahLog::where('id_jenis_kayu', $idJenisKayu)
            ->where('tipe_transaksi', 'masuk')
            ->where('referensi_type', DetailHasilPaletRotary::class)
            ->when($this->tanggalAcuan, fn ($q, $t) => $q->whereDate('tanggal', '<=', $t))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->limit(300)
            ->get(['referensi_id', 'total_kubikasi']);

        if ($logs->isEmpty()) {
            return [];
        }

        $palet = DetailHasilPaletRotary::with('penggunaanLahan')
            ->whereIn('id', $logs->pluck('referensi_id'))->get()->keyBy('id');

        $batas = $stokM3 > 0 ? $stokM3 : null;
        $acc   = 0.0;
        $lahan = [];

        foreach ($logs as $log) {
            $idLahan = $palet->get($log->referensi_id)?->penggunaanLahan?->id_lahan;
            $m3      = (float) $log->total_kubikasi;

            $lahan[$idLahan ?: 'tidak_diketahui'] = ($lahan[$idLahan ?: 'tidak_diketahui'] ?? 0.0) + $m3;
            $acc += $m3;

            if ($batas !== null && $acc >= $batas) {
                break;
            }
            if ($batas === null && count($lahan) >= 30) {
                break;
            }
        }

        return $lahan;
    }

    // =====================================================================
    // E. HITUNG ULANG RIWAYAT LOG (mengisi HPP data lama)
    // =====================================================================

    /**
     * Putar ulang SEMUA log veneer jadi berurutan menurut id (lintas produk), sehingga veneer jadi yang
     * keluar lalu masuk lagi ke repair/joint membawa rinciannya saat itu.
     *   MASUK  : HPP dari referensinya (serah terima dryer/kedi/joint/gudang, hasil repair).
     *            Sumber tanpa HPP (opname, nota, pilih veneer, pengembalian hotpress) memakai
     *            HPP Average stok saat itu — sama dengan aturan veneer kering.
     *   KELUAR : HPP Average stok saat itu.
     * Lembar dan m³ tidak diubah; hanya kolom HPP, nilai, jalur, dan komposisi.
     *
     * @return array{log: int, masuk_berhpp: int, nilai_akhir: float, produk: array<int,array>}
     */
    public function hitungUlangSemua(?int $idJenisKayu = null): array
    {
        $this->komposisiKeluar = [];
        $state  = [];
        $jumlah = 0;
        $berHpp = 0;

        $query = HppVeneerJadiLog::query()->when($idJenisKayu, fn ($q, $v) => $q->where('id_jenis_kayu', $v));

        $query->orderBy('id')->chunkById(500, function ($logs) use (&$state, &$jumlah, &$berHpp) {
            foreach ($logs as $log) {
                $jumlah++;
                $kunci = implode('|', [(int) $log->id_jenis_kayu, (float) $log->panjang, (float) $log->lebar, (float) $log->tebal, (string) $log->kw_grade]);
                $s = $state[$kunci] ?? ['nilai' => 0.0, 'komp' => KomponenHpp::kosong(), 'm3' => 0.0, 'log' => 0, 'masuk' => 0, 'kw' => $log];
                $s['log']++;

                $this->tanggalAcuan = $log->tanggal ? Carbon::parse($log->tanggal)->toDateString() : null;

                $masuk    = strtolower((string) $log->tipe_transaksi) === 'masuk';
                $m3Tx     = (float) $log->total_kubikasi;
                $m3Before = (float) $log->stok_kubikasi_before;
                $m3After  = (float) $log->stok_kubikasi_after;

                if ($m3Before <= 0.000001) {
                    $s['nilai'] = 0.0;
                    $s['komp']  = KomponenHpp::kosong();
                }
                $avgBefore = $m3Before > 0 ? $s['nilai'] / $m3Before : 0.0;
                $kompStok  = $s['komp']->rekonsiliasi($avgBefore);

                if ($masuk) {
                    [$komponen, $jalur] = $this->komponenUntukLog($log, $kompStok);

                    $nilaiTx    = $m3Tx * $komponen->total();
                    $nilaiAfter = $s['nilai'] + $nilaiTx;
                    $avgAfter   = $m3After > 0.000001 ? $nilaiAfter / $m3After : 0.0;
                    $g          = $komponen->kelompok();

                    $dasar = trim(str_replace(self::TANDA_SEMENTARA, '', (string) $log->keterangan));
                    $ket   = $komponen->sementara ? rtrim(mb_substr($dasar, 0, 255 - mb_strlen(self::TANDA_SEMENTARA) - 1)).' '.self::TANDA_SEMENTARA : $dasar;

                    $log->forceFill([
                        'hpp_kayu'           => round($g['kayu'], 2),
                        'hpp_pekerja'        => round($g['pekerja'], 2),
                        'hpp_mesin'          => round($g['mesin'], 2),
                        'hpp_bahan_penolong' => round($g['bahan'], 2),
                        'hpp_lainnya'        => round($g['lainnya'], 2),
                        'hpp_average'        => round($avgAfter, 2),
                        'nilai_stok'         => round($nilaiTx, 2),
                        'nilai_stok_before'  => round($s['nilai'], 2),
                        'nilai_stok_after'   => round($nilaiAfter, 2),
                        'jalur'              => $jalur,
                        'komposisi'          => $komponen->toArray(),
                        'keterangan'         => $ket,
                    ])->saveQuietly();

                    $s['komp']  = KomponenHpp::gabung([[$m3Before, $kompStok], [$m3Tx, $komponen]]);
                    $s['nilai'] = $nilaiAfter;
                    if ($komponen->total() > 0) {
                        $berHpp++;
                        $s['masuk']++;
                    }
                } else {
                    $nilaiTx    = $m3Tx * $avgBefore;
                    $nilaiAfter = $m3After > 0.000001 ? max(0.0, $s['nilai'] - $nilaiTx) : 0.0;

                    $log->forceFill([
                        'hpp_average'       => round($avgBefore, 2),
                        'nilai_stok'        => round($nilaiTx, 2),
                        'nilai_stok_before' => round($s['nilai'], 2),
                        'nilai_stok_after'  => round($nilaiAfter, 2),
                    ])->saveQuietly();

                    // rincian stok saat veneer ini keluar → dipakai bila nanti masuk lagi ke repair/joint
                    if (str_ends_with((string) $log->referensi_type, 'SerahTerimaVeneerKering') && $log->referensi_id) {
                        $this->komposisiKeluar[(int) $log->referensi_id] = $kompStok;
                    }

                    $s['nilai'] = $nilaiAfter;
                    $s['komp']  = $kompStok;
                }

                $s['m3']     = $m3After;
                $s['kw']     = $log;
                $state[$kunci] = $s;
            }
        });

        $this->tanggalAcuan = null;

        // simpan keadaan akhir ke stok_veneer_jadi
        $nilaiTotal = 0.0;
        $produk     = [];

        foreach ($state as $s) {
            $log      = $s['kw'];
            $m3Akhir  = (float) $s['m3'];
            $nilai    = $m3Akhir > 0.000001 ? (float) $s['nilai'] : 0.0;
            $hppAkhir = $m3Akhir > 0.000001 ? $nilai / $m3Akhir : 0.0;
            $komp     = $s['komp']->rekonsiliasi($hppAkhir);
            $g        = $komp->kelompok();

            $stok = StokVeneerJadi::where([
                'id_jenis_kayu' => $log->id_jenis_kayu, 'panjang' => $log->panjang, 'lebar' => $log->lebar,
                'tebal' => $log->tebal, 'kw_grade' => $log->kw_grade,
            ])->first();

            $stok?->forceFill([
                'nilai_stok'              => round($nilai, 2),
                'hpp_average'             => round($hppAkhir, 2),
                'hpp_kayu_last'           => round($g['kayu'], 2),
                'hpp_pekerja_last'        => round($g['pekerja'], 2),
                'hpp_mesin_last'          => round($g['mesin'], 2),
                'hpp_bahan_penolong_last' => round($g['bahan'], 2),
                'komposisi'               => $komp->toArray(),
            ])->saveQuietly();

            $nilaiTotal += $nilai;
            $produk[] = [
                'kayu' => (int) $log->id_jenis_kayu, 'ukuran' => (float) $log->panjang.'×'.(float) $log->lebar.'×'.(float) $log->tebal,
                'kw' => (string) $log->kw_grade, 'log' => $s['log'], 'masuk' => $s['masuk'], 'hpp' => round($hppAkhir, 0),
            ];
        }

        return ['log' => $jumlah, 'masuk_berhpp' => $berHpp, 'nilai_akhir' => round($nilaiTotal, 2), 'produk' => $produk];
    }

    /**
     * Komponen HPP untuk satu log MASUK lama + jalurnya.
     *
     * @return array{0: KomponenHpp, 1: ?string}
     */
    private function komponenUntukLog(HppVeneerJadiLog $log, KomponenHpp $kompStok): array
    {
        $tipe = (string) $log->referensi_type;

        if (str_ends_with($tipe, 'SerahTerimaVeneerKering') && $log->referensi_id) {
            $st = SerahTerimaVeneerKering::find($log->referensi_id);
            if ($st) {
                return [$this->komponenInput($st), $st->tipe_sumber === 'joint' ? 'joint' : 'kering_langsung'];
            }
        }

        if (str_ends_with($tipe, 'GudangVeneerJadi') && $log->referensi_id) {
            $g = GudangVeneerJadi::find($log->referensi_id);

            if ($g && preg_match('/Hasil Repair ID #(\d+)/', (string) $g->keterangan, $m)) {
                $d = DetailHasilRepair::find((int) $m[1]);
                if ($d) {
                    return [$this->komponenRepair($d), 'repair'];
                }
            }

            if ($g && $g->komposisi) {
                return [KomponenHpp::dari($g->komposisi), $g->jalur ?: 'repair'];
            }
        }

        // opname, nota, pilih veneer, pengembalian hotpress: HPP Average stok saat itu
        return [$kompStok->tandaiSementara(false), null];
    }
}