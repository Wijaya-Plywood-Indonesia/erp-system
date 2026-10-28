<?php

namespace App\Services;

use App\Models\GrajiStik;
use App\Models\ProduksiDempul;
use App\Models\ProduksiGrajiBalken;
use App\Models\ProduksiGrajitriplek;
use App\Models\produksi_guellotine;
use App\Models\ProduksiHp;
use App\Models\ProduksiJoint;
use App\Models\ProduksiKedi;
use App\Models\ProduksiNyusup;
use App\Models\PengaturanValidasiOtomatis;
use App\Models\ProduksiPalet;
use App\Models\ProduksiPilihPlywood;
use App\Models\ProduksiPilihVeneer;
use App\Models\ProduksiPotAfJoint;
use App\Models\ProduksiPotJelek;
use App\Models\ProduksiPotSiku;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiRepair;
use App\Models\ProduksiRotary;
use App\Models\ProduksiSanding;
use App\Models\ProduksiSandingJoint;
use App\Models\ProduksiStik;
use App\Models\ProduksiTembeltriplek;
use App\Models\ProduksiTerimaGudangSatu;
use App\Models\ScheduledNotification;
use App\Models\User;
use App\Models\validasi_guellotine;
use App\Models\ValidasiDempul;
use App\Models\ValidasiGrajiBalken;
use App\Models\ValidasiGrajiStik;
use App\Models\ValidasiGrajiTriplek;
use App\Models\ValidasiHasilRotary;
use App\Models\ValidasiHp;
use App\Models\ValidasiJoint;
use App\Models\ValidasiKedi;
use App\Models\ValidasiNyusup;
use App\Models\ValidasiPilihPlywood;
use App\Models\ValidasiPilihVeneer;
use App\Models\ValidasiPotAfJoint;
use App\Models\ValidasiPotJelek;
use App\Models\ValidasiPotSiku;
use App\Models\ValidasiPressDryer;
use App\Models\ValidasiProduksiPalet;
use App\Models\ValidasiRepair;
use App\Models\ValidasiSanding;
use App\Models\ValidasiSandingJoint;
use App\Models\ValidasiStik;
use App\Models\ValidasiTembeltriplek;
use App\Models\ValidasiTerimaGudangSatu;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * VALIDASI OTOMATIS SEMUA PRODUKSI
 * ============================================================================
 * Aturan:
 *   Produksi bertanggal D  -> pada D + hari_setelah (0 = hari yang sama, 1 = besok),
 *   jam jam_validasi otomatis divalidasi, khusus menu yang dicentang di pengaturan (baris validasi dibuat oleh "sistem_otomatis"). Logikanya SAMA
 *   dengan validasi manual: baris validasi dibuat lewat model yang sama, jadi
 *   observer / potong stok / jurnal yang sudah ada ikut jalan sendiri.
 *
 *   Dua kondisi role:
 *   1) SELAIN super admin -> setelah divalidasi (otomatis maupun manual) tidak
 *      bisa apa-apa: tombol tersembunyi (kunci UI yang sudah ada) + kunci keras
 *      di level model (create / update / delete ditolak walau lewat jalur lain).
 *   2) SUPER ADMIN -> tetap bebas. Baris validasi otomatis "disamarkan" untuk
 *      super admin (status tampil 'otomatis', bukan 'divalidasi') sehingga
 *      ratusan pengecekan `validasiTerakhir?->status === 'divalidasi'` di
 *      Filament tidak mengunci dia — tanpa mengubah satu pun file Tables.
 *
 * Cara pakai:
 *   - AppServiceProvider::boot()  -> ValidasiOtomatisProduksiService::boot();
 *   - Jadwal: `php artisan produksi:validasi-otomatis` (scheduler tiap 5 menit)
 *     + cadangan lewat middleware RunDailyScheduler::terminate() bila tidak ada cron.
 *
 * Pengaturan (aktif/nonaktif, jam, hari ini/besok, tanggal mulai, dan menu produksi
 * mana saja yang divalidasi otomatis) TIDAK lagi ada di kodingan, tetapi diatur lewat
 * menu "Pengaturan Validasi Otomatis" (tabel pengaturan_validasi_otomatis).
 */
class ValidasiOtomatisProduksiService
{
    // ───────────────────────── KONSTANTA TEKNIS ─────────────────────────
    // (Jadwal & pilihan menu ada di menu Pengaturan Validasi Otomatis, bukan di sini.)

    /** Kunci keras di level model untuk role selain super admin. false = hanya kunci UI. */
    public const KUNCI_KERAS = true;

    /** Jeda minimal antar pengecekan lewat fallback middleware (menit). */
    public const JEDA_CEK_MENIT = 5;

    /** Nilai kolom `role` pada baris validasi buatan sistem. */
    public const ROLE_SISTEM = 'sistem_otomatis';

    /** Status yang dianggap sudah final. */
    protected const STATUS_FINAL = ['divalidasi', 'disetujui'];

    /** Produksi dengan status ini TIDAK divalidasi otomatis (keputusan manual validator). */
    protected const STATUS_TAHAN = ['ditolak', 'ditangguhkan'];

    protected const ROLE_SUPER_ADMIN = ['super_admin', 'Super Admin', 'super-admin'];

    // ───────────────────────── STATE INTERNAL ─────────────────────────

    protected static bool $sistemBerjalan = false;

    protected static ?array $peta = null;

    // ───────────────────────── DAFTAR SEMUA PRODUKSI ─────────────────────────

    /**
     * Daftar semua menu produksi.
     *
     *  produksi  : model produksi          validasi : model validasi
     *  fk        : kolom FK di tabel validasi
     *  tanggal   : kolom/ekspresi SQL tanggal produksi
     *  anak      : nama relasi tab (RelationManager) yang ikut terkunci
     *  tambahan  : kolom ekstra baris validasi (juga jadi syarat "sudah divalidasi")
     *  where     : syarat ekstra produksi yang boleh divalidasi
     *  peran     : (Rotary) validasi lengkap bila SEMUA potongan ini ada di kolom role
     *  status    : status yang ditulis (default 'divalidasi')
     *  samar     : true = status disamarkan untuk super admin
     *  ganda     : true = cegah validasi manual kedua setelah ada validasi otomatis
     */
    public static function daftar(): array
    {
        static $daftar = null;

        return $daftar ??= [
            'rotary' => [
                'produksi' => ProduksiRotary::class, 'validasi' => ValidasiHasilRotary::class,
                'fk' => 'id_produksi', 'tanggal' => 'tgl_produksi',
                'status' => 'disetujui', 'peran' => ['kepala_produksi', 'pengawas_rotary'],
                'samar' => false, 'ganda' => false,
                'anak' => ['BahanPenolongRotary', 'detailGantiPisauRotary', 'DetailKayuPecah', 'detailLahanRotary',
                    'detailPaletRotary', 'detailPegawaiRotary', 'kendalaRotaries', 'serahTerima'],
            ],
            'press_dryer' => [
                'produksi' => ProduksiPressDryer::class, 'validasi' => ValidasiPressDryer::class,
                'fk' => 'id_produksi_dryer', 'tanggal' => 'tanggal_produksi',
                'anak' => ['detailHasils', 'detailMasuks', 'detailMesins', 'detailPegawais', 'kendalaPressDryers',
                    'serahTerimaVeneerBasah', 'serahTerimaVeneerKering'],
            ],
            'kedi' => [
                // Hanya Kedi yang sudah tahap 'bongkar' (sama dgn aturan observer: validasi bongkar).
                'produksi' => ProduksiKedi::class, 'validasi' => ValidasiKedi::class,
                'fk' => 'id_produksi_kedi', 'tanggal' => 'COALESCE(tanggal_actual_bongkar, tanggal)',
                'where' => ['status' => 'bongkar'], 'tambahan' => ['tipe' => 'bongkar'],
                'anak' => ['detailBongkarKedi', 'detailMasukKedi', 'kendalaKedis', 'DetailPegawaiKedi'],
            ],
            'stik' => [
                'produksi' => ProduksiStik::class, 'validasi' => ValidasiStik::class,
                'fk' => 'id_produksi_stik', 'tanggal' => 'tanggal_produksi',
                'anak' => ['detailHasilStik', 'detailMasukStik', 'detailPegawaiStik'],
            ],
            'hotpress' => [
                'produksi' => ProduksiHp::class, 'validasi' => ValidasiHp::class,
                'fk' => 'id_produksi_hp', 'tanggal' => 'tanggal_produksi',
                'anak' => ['bahanHotpress', 'bahanPenolongHp', 'detailPegawaiHp', 'kendalaHps', 'mutasiMasuk',
                    'platformBahanHp', 'platformHasilHp', 'RencanaKerjaHp', 'serahTerimaHp', 'triplekHasilHp',
                    'veneerBahanHp'],
            ],
            'sanding' => [
                'produksi' => ProduksiSanding::class, 'validasi' => ValidasiSanding::class,
                'fk' => 'id_produksi_sanding', 'tanggal' => 'tanggal',
                'anak' => ['hasilSandings', 'kendalaSandings', 'modalSandings', 'pegawaiSandings'],
            ],
            'sanding_joint' => [
                'produksi' => ProduksiSandingJoint::class, 'validasi' => ValidasiSandingJoint::class,
                'fk' => 'id_produksi_sanding_joint', 'tanggal' => 'tanggal_produksi',
                'anak' => ['HasilSandingJoint', 'PegawaiSandingJoint'],
            ],
            'joint' => [
                'produksi' => ProduksiJoint::class, 'validasi' => ValidasiJoint::class,
                'fk' => 'id_produksi_joint', 'tanggal' => 'tanggal_produksi',
                'anak' => ['bahanProduksi', 'HasilJoint', 'ModalJoint', 'PegawaiJoint'],
            ],
            'pot_siku' => [
                'produksi' => ProduksiPotSiku::class, 'validasi' => ValidasiPotSiku::class,
                'fk' => 'id_produksi_pot_siku', 'tanggal' => 'tanggal_produksi',
                'anak' => ['DetailBarangDikerjakanPotSiku', 'PegawaiPotSiku'],
            ],
            'pot_jelek' => [
                'produksi' => ProduksiPotJelek::class, 'validasi' => ValidasiPotJelek::class,
                'fk' => 'id_produksi_pot_jelek', 'tanggal' => 'tanggal_produksi',
                'anak' => ['DetailBarangDikerjakanPotJelek', 'PegawaiPotJelek'],
            ],
            'pot_af_joint' => [
                'produksi' => ProduksiPotAfJoint::class, 'validasi' => ValidasiPotAfJoint::class,
                'fk' => 'id_produksi_pot_af_joint', 'tanggal' => 'tanggal_produksi',
                'anak' => ['HasilPotAfJoint', 'PegawaiPotAfJoint'],
            ],
            'graji_balken' => [
                'produksi' => ProduksiGrajiBalken::class, 'validasi' => ValidasiGrajiBalken::class,
                'fk' => 'id_produksi_graji_balken', 'tanggal' => 'tanggal_produksi',
                'anak' => ['HasilGrajiBalken', 'PegawaiGrajiBalken'],
            ],
            'graji_triplek' => [
                'produksi' => ProduksiGrajitriplek::class, 'validasi' => ValidasiGrajiTriplek::class,
                'fk' => 'id_produksi_graji_triplek', 'tanggal' => 'tanggal_produksi',
                'anak' => ['HasilGrajiTriplek', 'kendalaGrajiTripleks', 'MasukGrajiTriplek', 'PegawaiGrajiTriplek'],
            ],
            'graji_stik' => [
                'produksi' => GrajiStik::class, 'validasi' => ValidasiGrajiStik::class,
                'fk' => 'id_graji_stiks', 'tanggal' => 'tanggal',
                'anak' => ['hasilGrajiStik', 'modalGrajiStik', 'pegawaiGrajiStik'],
            ],
            'guellotine' => [
                'produksi' => produksi_guellotine::class, 'validasi' => validasi_guellotine::class,
                'fk' => 'id_produksi_guellotine', 'tanggal' => 'tanggal_produksi',
                'anak' => ['HasilGuellotine', 'PegawaiGuellotine'],
            ],
            'pilih_veneer' => [
                'produksi' => ProduksiPilihVeneer::class, 'validasi' => ValidasiPilihVeneer::class,
                'fk' => 'id_produksi_pilih_veneer', 'tanggal' => 'tanggal_produksi',
                'anak' => ['HasilPilihVeneer', 'ModalPilihVeneer', 'PegawaiPilihVeneer'],
            ],
            'pilih_plywood' => [
                'produksi' => ProduksiPilihPlywood::class, 'validasi' => ValidasiPilihPlywood::class,
                'fk' => 'id_produksi_pilih_plywood', 'tanggal' => 'tanggal_produksi',
                'anak' => ['BahanPilihPlywood', 'hasilPilihPlywood', 'ListPekerjaanMenumpuk', 'PegawaiPilihPlywood',
                    'serahTerimaTriplekCacat', 'serahTerimaTriplekJadi'],
            ],
            'nyusup' => [
                'produksi' => ProduksiNyusup::class, 'validasi' => ValidasiNyusup::class,
                'fk' => 'id_produksi_nyusup', 'tanggal' => 'tanggal_produksi',
                'anak' => ['DetailBarangDikerjakan', 'PegawaiNyusup'],
            ],
            'dempul' => [
                'produksi' => ProduksiDempul::class, 'validasi' => ValidasiDempul::class,
                'fk' => 'id_produksi_dempul', 'tanggal' => 'tanggal',
                'anak' => ['bahanDempuls', 'detailDempuls', 'rencanaPegawaiDempuls'],
            ],
            'repair' => [
                'produksi' => ProduksiRepair::class, 'validasi' => ValidasiRepair::class,
                'fk' => 'id_produksi_repair', 'tanggal' => 'tanggal',
                'anak' => ['bahanPenolongRepair', 'detailHasilRepairs', 'rencanaPegawais', 'hasilRepairs',
                    'modalRepairs', 'rencanaRepair'],
            ],
            'terima_gudang_satu' => [
                'produksi' => ProduksiTerimaGudangSatu::class, 'validasi' => ValidasiTerimaGudangSatu::class,
                'fk' => 'id_produksi_terima_gudang_satu', 'tanggal' => 'tanggal_produksi',
                'anak' => ['bahanTerimaGudangSatu', 'hasil', 'pegawaiTerimaGudangSatu', 'serahTerima'],
            ],
            'tembel_triplek' => [
                'produksi' => ProduksiTembeltriplek::class, 'validasi' => ValidasiTembeltriplek::class,
                'fk' => 'id_produksi_tembel_triplek', 'tanggal' => 'tanggal',
                'anak' => ['bahanPenolongTembeltriplek', 'hasilTembeltriplek', 'pegawaiTembeltriplek'],
            ],
            'palet' => [
                // Palet: kunci/validasi ditangani ValidasiProduksiPaletService (potong stok log core).
                'produksi' => ProduksiPalet::class, 'validasi' => ValidasiProduksiPalet::class,
                'fk' => 'id_produksi_palet', 'tanggal' => 'tanggal',
                'samar' => false, 'palet' => true,
                'anak' => ['hasilProduksiPalets', 'pegawaiPalets'],
            ],
        ];
    }

    // ───────────────────────── PENGATURAN DARI DATABASE ─────────────────────────

    /** Pengaturan aktif (aktif, jam, hari_setelah, mulai_tanggal, menu_aktif). */
    public static function pengaturan(): array
    {
        $p = PengaturanValidasiOtomatis::nilai();

        // Buang kunci menu yang sudah tidak ada di daftar().
        $p['menu_aktif'] = array_values(array_intersect($p['menu_aktif'], array_keys(self::daftar())));

        return $p;
    }

    /**
     * Pilihan menu untuk halaman pengaturan: kunci => nama tampil.
     * Menu baru di daftar() otomatis muncul (nama default dari kuncinya).
     *
     * @return array<string,string>
     */
    public static function daftarMenu(): array
    {
        $label = [
            'rotary' => 'Produksi Rotary',
            'press_dryer' => 'Produksi Press Dryer',
            'kedi' => 'Produksi Kedi (bongkar)',
            'stik' => 'Produksi Stik',
            'hotpress' => 'Produksi Hot Press',
            'sanding' => 'Produksi Sanding',
            'sanding_joint' => 'Produksi Sanding Joint',
            'joint' => 'Produksi Joint',
            'pot_siku' => 'Produksi Pot Siku',
            'pot_jelek' => 'Produksi Pot Jelek',
            'pot_af_joint' => 'Produksi Pot Afalan Joint',
            'graji_balken' => 'Produksi Graji Balken',
            'graji_triplek' => 'Produksi Graji Triplek',
            'graji_stik' => 'Produksi Graji Stik',
            'guellotine' => 'Produksi Guellotine',
            'pilih_veneer' => 'Produksi Pilih Veneer',
            'pilih_plywood' => 'Produksi Pilih Plywood',
            'nyusup' => 'Produksi Nyusup',
            'dempul' => 'Produksi Dempul',
            'repair' => 'Produksi Repair',
            'terima_gudang_satu' => 'Produksi Terima Gudang Satu',
            'tembel_triplek' => 'Produksi Tembel Triplek',
            'palet' => 'Produksi Palet',
        ];

        $hasil = [];
        foreach (array_keys(self::daftar()) as $kunci) {
            $hasil[$kunci] = $label[$kunci] ?? ucwords(str_replace('_', ' ', $kunci));
        }

        return $hasil;
    }

    // ───────────────────────── BOOT (dipanggil dari AppServiceProvider) ─────────────────────────

    public static function boot(): void
    {
        foreach (self::daftar() as $c) {
            /** @var class-string<Model> $vm */
            $vm = $c['validasi'];

            // (2) Samarkan baris otomatis untuk super admin.
            if ($c['samar'] ?? true) {
                $vm::retrieved(fn (Model $m) => self::samarkan($m));
            }

            // Baris validasi otomatis tidak boleh diedit / dihapus sembarang orang
            // (mengedit status-nya bisa memicu potong stok ganda).
            $vm::updating(fn (Model $m) => self::cegahUbahOtomatis($m));
            $vm::deleting(fn (Model $m) => self::cegahHapusOtomatis($m));

            if ($c['ganda'] ?? true) {
                $vm::creating(fn (Model $m) => self::cegahValidasiGanda($m, $c));
            }
        }

        // (1) Kunci keras untuk selain super admin.
        if (self::KUNCI_KERAS) {
            Event::listen(
                ['eloquent.creating: *', 'eloquent.updating: *', 'eloquent.deleting: *'],
                fn (string $event, array $payload) => self::kunciKeras($event, $payload)
            );
        }
    }

    // ───────────────────────── PROSES VALIDASI OTOMATIS ─────────────────────────

    /**
     * Fallback tanpa cron: dipanggil dari RunDailyScheduler::terminate().
     * Dibatasi supaya paling sering 1x per JEDA_CEK_MENIT.
     */
    public static function jalankanBilaPerlu(): void
    {
        try {
            if (! self::pengaturan()['aktif']) {
                return;
            }

            if (! Cache::add('validasi_otomatis_produksi:jeda', 1, now()->addMinutes(self::JEDA_CEK_MENIT))) {
                return;
            }

            self::jalankan();
        } catch (\Throwable $e) {
            Log::error('ValidasiOtomatisProduksi (fallback) gagal: ' . $e->getMessage());
        }
    }

    /**
     * Validasi semua produksi yang sudah jatuh tempo.
     *
     * @return array<int, array{menu:string,id:int|string,tanggal:string}> produksi yang divalidasi
     */
    public static function jalankan(bool $dry = false): array
    {
        $p = self::pengaturan();
        $hasil = [];

        // Nonaktif, atau belum ada menu yang dipilih -> tidak ada yang divalidasi.
        if (! $p['aktif'] || ! $p['menu_aktif']) {
            return $hasil;
        }

        $batas = self::batasTanggal($p);
        $mulai = $p['mulai_tanggal'];

        if ($mulai && $batas < $mulai) {
            return $hasil;
        }

        $asli = Auth::user();
        self::$sistemBerjalan = true;

        // Pakai "user sistem" supaya log/observer yang membaca Auth::user() tidak
        // mencatat orang yang kebetulan sedang membuka web sebagai validator.
        Auth::setUser((new User)->forceFill(['id' => null, 'name' => 'Sistem Otomatis']));

        try {
            foreach (self::daftar() as $kunci => $c) {
                // Produksi yang tidak dicentang di menu pengaturan tidak divalidasi otomatis.
                if (! in_array($kunci, $p['menu_aktif'], true)) {
                    continue;
                }

                try {
                    $jatuhTempo = self::cariJatuhTempo($c, $batas, $mulai);
                } catch (\Throwable $e) {
                    // Satu menu bermasalah (mis. kolom beda di database) tidak boleh menghentikan menu lain.
                    Log::error("ValidasiOtomatisProduksi [{$kunci}] gagal mencari data: " . $e->getMessage());

                    continue;
                }

                foreach ($jatuhTempo as $row) {
                    $id = $row->getKey();
                    $tanggal = (string) ($row->tgl_validasi_dasar ?? '');

                    if (! $dry) {
                        try {
                            self::validasiSatu($kunci, $c, $id);
                        } catch (\Throwable $e) {
                            Log::error("ValidasiOtomatisProduksi [{$kunci} #{$id}] gagal: " . $e->getMessage());

                            continue;
                        }
                    }

                    $hasil[] = ['menu' => $kunci, 'id' => $id, 'tanggal' => $tanggal];
                }
            }
        } finally {
            self::$sistemBerjalan = false;
            $asli ? Auth::setUser($asli) : Auth::guard()->forgetUser();
        }

        if ($hasil && ! $dry) {
            Log::info('ValidasiOtomatisProduksi: ' . count($hasil) . ' produksi divalidasi otomatis.');
        }

        return $hasil;
    }

    /** Tanggal produksi paling baru yang sudah jatuh tempo hari ini. */
    protected static function batasTanggal(array $p): string
    {
        $sekarang = now();
        $mundur = $p['hari_setelah'];

        if ($sekarang->format('H:i') < $p['jam']) {
            $mundur++; // jam validasi hari ini belum tiba
        }

        return $sekarang->copy()->subDays($mundur)->toDateString();
    }

    /**
     * Nama kolom tanggal di database bisa beda antar lingkungan (mis. Dempul: `tanggal`
     * atau `tanggal_produksi`). Bila kolom yang dikonfigurasi tidak ada, cari penggantinya.
     * Ekspresi rumit (COALESCE dst.) dibiarkan apa adanya.
     */
    protected static function ekspresiTanggal(string $expr, string $tabel): string
    {
        if (! preg_match('/^\w+$/', $expr)) {
            return $expr;
        }

        static $cache = [];

        return $cache[$tabel . '.' . $expr] ??= (function () use ($expr, $tabel) {
            foreach (array_unique([$expr, 'tanggal_produksi', 'tanggal', 'tgl_produksi']) as $kolom) {
                if (Schema::hasColumn($tabel, $kolom)) {
                    return $kolom;
                }
            }

            return $expr; // biarkan error asli muncul & tercatat di log
        })();
    }

    /** Produksi jatuh tempo yang belum divalidasi / belum pernah diproses sistem. */
    protected static function cariJatuhTempo(array $c, string $batas, ?string $mulai = null)
    {
        /** @var class-string<Model> $pm */
        $pm = $c['produksi'];
        $vm = $c['validasi'];
        $fk = $c['fk'];
        $tabel = (new $pm)->getTable();
        $expr = self::ekspresiTanggal($c['tanggal'], $tabel);

        $q = $pm::query()
            ->selectRaw("{$tabel}.*, DATE({$expr}) as tgl_validasi_dasar")
            ->whereRaw("DATE({$expr}) <= ?", [$batas]);

        if ($mulai) {
            $q->whereRaw("DATE({$expr}) >= ?", [$mulai]);
        }

        foreach ($c['where'] ?? [] as $kolom => $nilai) {
            $q->where($kolom, $nilai);
        }

        $kandidat = $q->get();
        if ($kandidat->isEmpty()) {
            return $kandidat;
        }

        $ids = $kandidat->map->getKey()->all();

        // a) Sudah punya validasi final / sengaja ditahan oleh validator.
        $vq = $vm::query()->whereIn($fk, $ids)
            ->whereIn(DB::raw('LOWER(status)'), array_merge(self::STATUS_FINAL, self::STATUS_TAHAN));
        foreach ($c['tambahan'] ?? [] as $kolom => $nilai) {
            $vq->where($kolom, $nilai);
        }

        if (! empty($c['peran'])) {
            // Rotary: dianggap lengkap hanya bila SEMUA peran sudah menyetujui.
            $sudah = [];
            foreach ($vq->get([$fk, 'role']) as $v) {
                foreach ($c['peran'] as $p) {
                    if (str_contains(strtolower((string) $v->role), $p)) {
                        $sudah[$v->{$fk}][$p] = true;
                    }
                }
            }
            $lengkap = collect($sudah)->filter(fn ($r) => count($r) >= count($c['peran']))->keys()->all();
        } else {
            $lengkap = $vq->pluck($fk)->all();
        }

        // b) Sudah pernah diproses sistem (penanda ini mencegah validasi ulang bila
        //    baris validasi dihapus super admin / dibatalkan).
        $kunciPenanda = fn ($id) => self::kunciPenanda($c, $id);
        $penanda = ScheduledNotification::whereIn('key', array_map($kunciPenanda, $ids))->pluck('key')->all();

        return $kandidat->reject(
            fn ($p) => in_array($p->getKey(), $lengkap) || in_array($kunciPenanda($p->getKey()), $penanda)
        )->values();
    }

    protected static function kunciPenanda(array $c, $id): string
    {
        return 'validasi_otomatis:' . $c['validasi'] . ':' . $id;
    }

    /** Validasi satu produksi — satu transaksi, rollback bila observer gagal. */
    protected static function validasiSatu(string $kunci, array $c, $id): void
    {
        DB::transaction(function () use ($c, $id) {
            $vm = $c['validasi'];
            $status = $c['status'] ?? 'divalidasi';

            $peran = $c['peran'] ?? [null];
            $adaPeran = collect();
            if (! empty($c['peran'])) {
                $adaPeran = $vm::query()->where($c['fk'], $id)->where('status', $status)->pluck('role');
            }

            foreach ($peran as $p) {
                // Rotary: lewati peran yang sudah menyetujui secara manual.
                if ($p && $adaPeran->contains(fn ($r) => str_contains(strtolower((string) $r), $p))) {
                    continue;
                }

                $baris = (new $vm)->forceFill(array_merge([
                    $c['fk'] => $id,
                    'role' => $p ? self::ROLE_SISTEM . '_' . $p : self::ROLE_SISTEM,
                    'status' => $status,
                ], $c['tambahan'] ?? []));
                $baris->save(); // observer validasi yang sudah ada ikut jalan di sini

                if (! empty($c['palet'])) {
                    ValidasiProduksiPaletService::prosesValidasi($baris);
                }
            }

            ScheduledNotification::create([
                'key' => self::kunciPenanda($c, $id),
                'scheduled_at' => now(),
                'last_run_at' => now(),
            ]);
        });
    }

    // ───────────────────────── KONDISI 2: SUPER ADMIN ─────────────────────────

    public static function adalahSuperAdmin(): bool
    {
        $user = Auth::user();

        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::ROLE_SUPER_ADMIN);
    }

    protected static function barisOtomatis(Model $m, bool $asli = false): bool
    {
        $role = (string) ($asli ? $m->getOriginal('role') : $m->getRawOriginal('role'));

        return str_starts_with($role, self::ROLE_SISTEM);
    }

    /**
     * Super admin melihat baris otomatis berstatus 'otomatis' (bukan 'divalidasi')
     * → semua pengecekan lock di Filament menganggap produksi belum divalidasi.
     * Hanya di memori: tidak pernah ditulis ke database (original ikut disinkronkan).
     */
    protected static function samarkan(Model $m): void
    {
        if (self::$sistemBerjalan || ! self::barisOtomatis($m) || ! self::adalahSuperAdmin()) {
            return;
        }

        $m->setRawAttributes(array_merge($m->getAttributes(), ['status' => 'otomatis']), true);
    }

    // ───────────────────────── KONDISI 1: SELAIN SUPER ADMIN ─────────────────────────

    /** Kunci keras: tulis ke produksi / tab-tabnya ditolak bila produksi sudah divalidasi. */
    protected static function kunciKeras(string $event, array $payload)
    {
        $model = $payload[0] ?? null;

        if (self::$sistemBerjalan || ! $model instanceof Model || ! Auth::check() || self::adalahSuperAdmin()) {
            return null;
        }

        $peta = self::petaKunci();
        $kelas = get_class($model);

        if (! isset($peta[$kelas])) {
            return null;
        }

        foreach ($peta[$kelas] as [$kunci, $fk]) {
            $c = self::daftar()[$kunci];
            $id = $fk === null
                ? $model->getKey()
                : ($model->getAttribute($fk) ?? $model->getOriginal($fk));

            if ($id !== null && self::sudahValidasi($c, $id)) {
                return self::tolak(
                    'Produksi sudah divalidasi',
                    'Data produksi yang sudah divalidasi (otomatis maupun manual) hanya dapat diubah oleh Super Admin.'
                );
            }
        }

        return null;
    }

    /** class model => [[kunci menu, kolom FK|null untuk model produksinya sendiri], ...] */
    protected static function petaKunci(): array
    {
        if (self::$peta !== null) {
            return self::$peta;
        }

        $peta = [];

        foreach (self::daftar() as $kunci => $c) {
            $peta[$c['produksi']][] = [$kunci, null];
            $induk = new $c['produksi'];

            foreach ($c['anak'] ?? [] as $nama) {
                if (! method_exists($induk, $nama)) {
                    continue;
                }

                $rel = $induk->{$nama}();
                if ($rel instanceof HasOneOrMany) {
                    $peta[get_class($rel->getRelated())][] = [$kunci, $rel->getForeignKeyName()];
                }
            }
        }

        return self::$peta = $peta;
    }

    /** Apakah produksi ini sudah punya validasi final? (Kedi: khusus tipe bongkar; Rotary: semua peran) */
    protected static function sudahValidasi(array $c, $id): bool
    {
        $q = $c['validasi']::query()
            ->where($c['fk'], $id)
            ->whereIn(DB::raw('LOWER(status)'), self::STATUS_FINAL);

        foreach ($c['tambahan'] ?? [] as $kolom => $nilai) {
            $q->where($kolom, $nilai);
        }

        if (empty($c['peran'])) {
            return $q->exists();
        }

        $roles = strtolower($q->pluck('role')->implode('|'));

        return collect($c['peran'])->every(fn ($p) => str_contains($roles, $p));
    }

    // ───────────────────────── PENJAGA BARIS VALIDASI ─────────────────────────

    protected static function cegahUbahOtomatis(Model $m)
    {
        if (self::$sistemBerjalan || ! self::barisOtomatis($m, true)) {
            return null;
        }

        return self::tolak(
            'Validasi otomatis tidak bisa diubah',
            'Mengubah status baris ini bisa memotong stok dua kali. Hapus baris ini bila memang perlu dibatalkan (hanya Super Admin).'
        );
    }

    protected static function cegahHapusOtomatis(Model $m)
    {
        if (self::$sistemBerjalan || ! self::barisOtomatis($m, true) || self::adalahSuperAdmin()) {
            return null;
        }

        return self::tolak('Validasi otomatis tidak bisa dihapus', 'Hanya Super Admin yang dapat menghapus validasi otomatis.');
    }

    /** Super admin melihat tombol "buat validasi" (karena disamarkan) — cegah validasi manual kedua. */
    protected static function cegahValidasiGanda(Model $m, array $c)
    {
        if (self::$sistemBerjalan) {
            return null;
        }

        $q = $m::query()
            ->where($c['fk'], $m->getAttribute($c['fk']))
            ->where('role', 'like', self::ROLE_SISTEM . '%')
            ->whereIn(DB::raw('LOWER(status)'), self::STATUS_FINAL);

        foreach ($c['tambahan'] ?? [] as $kolom => $nilai) {
            $q->where($kolom, $m->getAttribute($kolom) ?? $nilai);
        }

        if (! $q->exists()) {
            return null;
        }

        return self::tolak(
            'Produksi sudah divalidasi otomatis',
            'Validasi manual dibatalkan agar stok tidak terpotong dua kali.'
        );
    }

    /**
     * Tolak operasi: kirim notifikasi lalu batalkan.
     * - Lewat Livewire/Filament: lempar Halt supaya aksi berhenti tanpa notifikasi "berhasil".
     * - Selain itu: return false (event Eloquent membatalkan operasi).
     */
    protected static function tolak(string $judul, string $isi): bool
    {
        try {
            Notification::make()->title($judul)->body($isi)->danger()->send();
        } catch (\Throwable) {
            // di luar konteks Filament — abaikan
        }

        if (request()->hasHeader('X-Livewire') && class_exists(\Filament\Support\Exceptions\Halt::class)) {
            throw new \Filament\Support\Exceptions\Halt;
        }

        return false;
    }
}
