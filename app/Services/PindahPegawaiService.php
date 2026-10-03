<?php

namespace App\Services;

use App\Models\DetailLainLain;
use App\Models\DetailPegawai;
use App\Models\DetailPegawaiHp;
use App\Models\DetailPegawaiStik;
use App\Models\GrajiStik;
use App\Models\LainLain;
use App\Models\Mesin;
use App\Models\PegawaiGrajiBalken;
use App\Models\PegawaiGrajiStik;
use App\Models\PegawaiGrajiTriplek;
use App\Models\PegawaiJoint;
use App\Models\PegawaiNyusup;
use App\Models\PegawaiPalet;
use App\Models\PegawaiPilihPlywood;
use App\Models\PegawaiPilihVeneer;
use App\Models\PegawaiPotAfJoint;
use App\Models\PegawaiPotJelek;
use App\Models\PegawaiPotSiku;
use App\Models\PegawaiRotary;
use App\Models\PegawaiSanding;
use App\Models\PegawaiSandingJoint;
use App\Models\PegawaiTembeltriplek;
use App\Models\PegawaiTerimaGudangSatu;
use App\Models\PindahPegawaiLog;
use App\Models\ProduksiGrajiBalken;
use App\Models\ProduksiGrajitriplek;
use App\Models\ProduksiHp;
use App\Models\ProduksiJoint;
use App\Models\ProduksiNyusup;
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
use App\Models\ProduksiDempul;
use App\Models\ProduksiKedi;
use App\Models\DetailPegawaiKedi;
use App\Models\RencanaPegawai;
use App\Models\RencanaPegawaiDempul;
use App\Models\pegawai_guellotine;
use App\Models\produksi_guellotine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Memindahkan sebagian jam kerja pegawai dari satu lini produksi ke lini lain.
 *
 * Aturan: jam yang dipindah selalu diambil dari AKHIR shift.
 *   Sumber : 06:00-16:00, pindah 1 jam -> 06:00-15:00
 *   Tujuan : baris baru pegawai yang sama 15:00-16:00
 *
 * Bisa dipakai dari halaman produksi MANAPUN yang ada di tujuan() (lihat
 * App\Filament\Support\PindahPegawaiTableActions) — bukan cuma Repair.
 * Menambah lini baru = tambah satu entri di tujuan(). Otomatis jadi sumber
 * juga (lihat sumber()), tidak perlu didaftarkan dua kali.
 */
class PindahPegawaiService
{
    /**
     * Lini ASAL (baris pegawai yang jamnya dikurangi).
     *
     * 'repair' didefinisikan manual karena nama kolomnya beda sendiri
     * (jam_masuk/jam_pulang/keterangan). Lini lainnya diambil OTOMATIS dari
     * tujuan() — kolom yang sama dipakai baik sebagai asal maupun tujuan,
     * supaya menambah satu lini baru cukup di satu tempat saja.
     */
    public static function sumber(): array
    {
        $dariTujuan = collect(self::tujuan())
            ->except(['lain_lain']) // pindah DARI Lain-lain tidak masuk akal (tidak ada durasi kerja pasti)
            ->map(fn ($cfg) => [
                'label' => $cfg['label'],
                'model' => $cfg['model'],
                'masuk' => $cfg['masuk'],
                'pulang' => $cfg['pulang'],
                'ket' => $cfg['ket'],
            ])
            ->all();

        return [
            'repair' => [
                'label' => 'Repair',
                'model' => RencanaPegawai::class,
                'masuk' => 'jam_masuk',
                'pulang' => 'jam_pulang',
                'ket' => 'keterangan',
            ],
        ] + $dariTujuan;
    }

    /**
     * Lini TUJUAN (baris pegawai baru yang dibuat, atau digabung/ditimpa bila sudah ada).
     * "Lain-lain" sengaja di paling atas.
     *
     * Kunci tiap entri:
     *  - model/fk/masuk/pulang/ijin/ket : baris pegawai di lini tujuan
     *  - produksi/tanggal               : produksi induk & kolom tanggalnya
     *  - tugas   : 'required' | 'optional' | null (lini tidak punya kolom tugas)
     *  - tugas_default : dipakai kalau tugas dikosongkan
     *  - ijin_kosong   : true bila kolom ijin NOT NULL (diisi string kosong)
     *  - mesin   : true bila baris pegawai butuh id_mesin (saat ini tidak ada lini yang memakai)
     *  - shift   : true bila produksi per tanggal + shift (form menampilkan pilihan Shift)
     *  - pilih   : label pilihan produksi yang SELALU tampil (lini per mesin: Rotary, Sanding, Kedi)
     *  - scope   : closure(query, Carbon $tgl) untuk aturan tanggal khusus (Kedi: tanggal bongkar)
     *  - auto_create   : buat produksi induk otomatis bila belum ada (Lain-lain)
     *  - label_produksi: closure penamaan pilihan produksi (shift / mesin)
     */
    public static function tujuan(): array
    {
        $shift = fn ($p) => $p->shift ? 'Shift '.ucfirst(strtolower($p->shift)) : 'Produksi';
        $mesin = fn ($p) => trim(($p->mesin?->nama_mesin ?? 'Mesin').($p->shift ? ' - Shift '.ucfirst(strtolower($p->shift)) : ''));

        $t = fn (array $o) => array_merge([
            'masuk' => 'masuk',
            'pulang' => 'pulang',
            'ijin' => 'ijin',
            'ket' => 'ket',
            'tanggal' => 'tanggal_produksi',
            'tugas' => 'required',
            // Default 'PINDAHAN' supaya field Nomor Meja/Tugas tidak lagi wajib ditanyakan
            // di form pindah pegawai, kecuali lini itu meng-override tugas_default sendiri.
            'tugas_default' => 'PINDAHAN',
            'ijin_kosong' => false,
            'mesin' => false,
            'shift' => false,
            'pilih' => null,
            'scope' => null,
            'auto_create' => false,
            'label_produksi' => fn ($p) => 'Produksi',
        ], $o);

        return [
            'lain_lain' => $t([
                'label' => 'Lain-lain',
                'model' => LainLain::class,
                'fk' => 'id_detail_lain_lain',
                'produksi' => DetailLainLain::class,
                'tanggal' => 'tanggal',
                'tugas' => null,
                'auto_create' => true,
            ]),
            'repair' => $t([
                'label' => 'Repair',
                'model' => RencanaPegawai::class,
                'fk' => 'id_produksi_repair',
                'masuk' => 'jam_masuk',
                'pulang' => 'jam_pulang',
                'ket' => 'keterangan',
                'produksi' => ProduksiRepair::class,
                'tanggal' => 'tanggal',
                'tugas' => null, // rencana_pegawais tidak punya kolom tugas
            ]),
            'press_dryer' => $t([
                'label' => 'Press Dryer',
                'model' => DetailPegawai::class,
                'fk' => 'id_produksi_dryer',
                'produksi' => ProduksiPressDryer::class,
                'shift' => true,
                'tugas' => 'optional',
                'label_produksi' => $shift,
            ]),
            'hotpress' => $t([
                'label' => 'Hotpress',
                'model' => DetailPegawaiHp::class,
                'fk' => 'id_produksi_hp',
                'produksi' => ProduksiHp::class,
                'shift' => true,
                // Mesin & nomor meja tidak ditanyakan lagi. Kolom `tugas` di detail_pegawai_hp
                // NOT NULL, jadi diisi otomatis; id_mesin dibiarkan kosong (kolom sudah nullable).
                'mesin' => false,
                'tugas_default' => 'PINDAHAN',
                'label_produksi' => $shift,
            ]),
            'kedi' => $t([
                'label' => 'Kedi',
                'model' => DetailPegawaiKedi::class,
                'fk' => 'id_produksi_kedi',
                'produksi' => ProduksiKedi::class,
                // Pegawai Kedi dicatat di hari bongkar (sama dengan sumber absensi Kedi)
                'scope' => fn ($q, Carbon $tgl) => $q->where(function ($w) use ($tgl) {
                    $w->whereDate('tanggal_actual_bongkar', $tgl)
                        ->orWhere(fn ($x) => $x->whereNull('tanggal_actual_bongkar')->whereDate('tanggal_bongkar', $tgl));
                }),
                'tugas_default' => 'PINDAHAN', // kolom tugas wajib diisi
                'pilih' => 'Kedi Tujuan',
                'label_produksi' => fn ($p) => trim(($p->mesin?->nama_mesin ?? 'Kedi').($p->kode_kedi ? ' - '.$p->kode_kedi : '')),
            ]),
            'rotary' => $t([
                'label' => 'Rotary',
                'model' => PegawaiRotary::class,
                'fk' => 'id_produksi',
                'masuk' => 'jam_masuk',
                'pulang' => 'jam_pulang',
                'ijin' => 'izin',
                'ket' => 'keterangan',
                'produksi' => ProduksiRotary::class,
                'tanggal' => 'tgl_produksi',
                'tugas' => null,
                'pilih' => 'Mesin Tujuan',
                'label_produksi' => $mesin,
            ]),
            'graji_balken' => $t([
                'label' => 'Graji Balken',
                'model' => PegawaiGrajiBalken::class,
                'fk' => 'id_produksi_graji_balken',
                'produksi' => ProduksiGrajiBalken::class,
            ]),
            'graji_triplek' => $t([
                'label' => 'Graji Triplek',
                'model' => PegawaiGrajiTriplek::class,
                'fk' => 'id_produksi_graji_triplek',
                'produksi' => ProduksiGrajitriplek::class,
                'shift' => true,
                'label_produksi' => $shift,
            ]),
            'graji_stik' => $t([
                'label' => 'Graji Stik',
                'model' => PegawaiGrajiStik::class,
                'fk' => 'id_graji_stiks',
                'masuk' => 'jam_masuk',
                'pulang' => 'jam_pulang',
                'ket' => 'keterangan',
                'produksi' => GrajiStik::class,
                'tanggal' => 'tanggal',
                'tugas' => null,
                'ijin_kosong' => true,
            ]),
            'stik' => $t([
                'label' => 'Stik',
                'model' => DetailPegawaiStik::class,
                'fk' => 'id_produksi_stik',
                'produksi' => ProduksiStik::class,
                'tugas' => 'optional',
            ]),
            'guellotine' => $t([
                'label' => 'Guellotine',
                'model' => pegawai_guellotine::class,
                'fk' => 'id_produksi_guellotine',
                'produksi' => produksi_guellotine::class,
            ]),
            'nyusup' => $t([
                'label' => 'Nyusup',
                'model' => PegawaiNyusup::class,
                'fk' => 'id_produksi_nyusup',
                'produksi' => ProduksiNyusup::class,
            ]),
            'tembel_triplek' => $t([
                'label' => 'Tembel Triplek',
                'model' => PegawaiTembeltriplek::class,
                'fk' => 'id_produksi_tembel_triplek',
                'masuk' => 'jam_masuk',
                'pulang' => 'jam_pulang',
                'ket' => 'keterangan',
                'produksi' => ProduksiTembeltriplek::class,
                'tanggal' => 'tanggal',
                'tugas' => null,
            ]),
            'dempul' => $t([
                'label' => 'Dempul',
                'model' => RencanaPegawaiDempul::class,
                'fk' => 'id_produksi_dempul',
                'masuk' => 'jam_masuk',
                'pulang' => 'jam_pulang',
                'ket' => 'keterangan',
                'produksi' => ProduksiDempul::class,
                'shift' => true,
                'tanggal' => \Illuminate\Support\Facades\Schema::hasColumn('produksi_dempuls', 'tanggal')
                    ? 'tanggal' : 'tanggal_produksi',
                'tugas' => null,
                'label_produksi' => $shift,
            ]),
            'pilih_veneer' => $t([
                'label' => 'Pilih Veneer',
                'model' => PegawaiPilihVeneer::class,
                'fk' => 'id_produksi_pilih_veneer',
                'produksi' => ProduksiPilihVeneer::class,
                'tugas_default' => 'PILIH VENEER',
            ]),
            'pilih_plywood' => $t([
                'label' => 'Pilih Plywood',
                'model' => PegawaiPilihPlywood::class,
                'fk' => 'id_produksi_pilih_plywood',
                'produksi' => ProduksiPilihPlywood::class,
                'tugas_default' => 'PILIH PLYWOOD',
            ]),
            'joint' => $t([
                'label' => 'Joint',
                'model' => PegawaiJoint::class,
                'fk' => 'id_produksi_joint',
                'produksi' => ProduksiJoint::class,
            ]),
            'sanding' => $t([
                'label' => 'Sanding',
                'model' => PegawaiSanding::class,
                'fk' => 'id_produksi_sanding',
                'produksi' => ProduksiSanding::class,
                'tanggal' => 'tanggal',
                'tugas' => 'optional',
                'pilih' => 'Mesin & Shift Tujuan',
                'label_produksi' => $mesin,
            ]),
            'sanding_joint' => $t([
                'label' => 'Sanding Joint',
                'model' => PegawaiSandingJoint::class,
                'fk' => 'id_produksi_sanding_joint',
                'produksi' => ProduksiSandingJoint::class,
            ]),
            'pot_af_joint' => $t([
                'label' => 'Pot Afalan Joint',
                'model' => PegawaiPotAfJoint::class,
                'fk' => 'id_produksi_pot_af_joint',
                'produksi' => ProduksiPotAfJoint::class,
            ]),
            'pot_jelek' => $t([
                'label' => 'Pot Jelek',
                'model' => PegawaiPotJelek::class,
                'fk' => 'id_produksi_pot_jelek',
                'produksi' => ProduksiPotJelek::class,
                'ijin_kosong' => true,
            ]),
            'pot_siku' => $t([
                'label' => 'Pot Siku',
                'model' => PegawaiPotSiku::class,
                'fk' => 'id_produksi_pot_siku',
                'produksi' => ProduksiPotSiku::class,
            ]),
            'palet' => $t([
                'label' => 'Produksi Palet',
                'model' => PegawaiPalet::class,
                'fk' => 'id_produksi_palet',
                'masuk' => 'jam_masuk',
                'pulang' => 'jam_pulang',
                'ijin' => 'izin',
                'ket' => 'keterangan',
                'produksi' => ProduksiPalet::class,
                'tanggal' => 'tanggal',
                'tugas' => null,
            ]),
            'terima_gudang_satu' => $t([
                'label' => 'Samping Plywood',
                'model' => PegawaiTerimaGudangSatu::class,
                'fk' => 'id_produksi_terima_gudang_satu',
                'produksi' => ProduksiTerimaGudangSatu::class,
            ]),
        ];
    }

    /** @param  string|null  $kecuali  kode lini yang dikecualikan dari daftar (biasanya lini asal sendiri) */
    public static function opsiTujuan(?string $kecuali = null): array
    {
        return collect(self::tujuan())
            ->except($kecuali ? [$kecuali] : [])
            ->map(fn ($t) => $t['label'])
            ->all();
    }

    public static function config(?string $kodeTujuan): ?array
    {
        return self::tujuan()[$kodeTujuan] ?? null;
    }

    /** Query produksi tujuan pada tanggal tertentu (memakai aturan khusus 'scope' bila ada). */
    private static function produksiPadaTanggal(array $cfg, Carbon $tanggal)
    {
        $q = $cfg['produksi']::query();

        return $cfg['scope']
            ? $cfg['scope']($q, $tanggal)
            : $q->whereDate($cfg['tanggal'], $tanggal->toDateString());
    }

    /** Pilihan produksi tujuan pada tanggal tertentu: [id => label]. */
    public static function opsiProduksi(?string $kodeTujuan, $tanggal): array
    {
        $cfg = self::config($kodeTujuan);
        if (! $cfg || ! $tanggal) {
            return [];
        }

        $query = self::produksiPadaTanggal($cfg, Carbon::parse($tanggal));

        // eager load mesin bila relasinya ada (Rotary, Sanding)
        if (method_exists($cfg['produksi'], 'mesin')) {
            $query->with('mesin');
        }

        return $query->get()
            ->mapWithKeys(fn ($p) => [$p->getKey() => $cfg['label_produksi']($p)])
            ->all();
    }

    /**
     * Nama pegawai (dari daftar id) yang SUDAH punya baris di produksi tujuan pada tanggal ini.
     * Dipakai form pindah untuk menampilkan peringatan + pilihan Update/Timpa HANYA kalau
     * memang ada duplikat — kalau kosong, form tidak menanyakan apa-apa.
     *
     * @param  array<int>  $idPegawaiList
     * @return array<string> nama pegawai yang sudah ada
     */
    public static function pegawaiSudahAda(string $kodeTujuan, array $idPegawaiList, $tanggalSumber, ?string $shift, $idProduksi): array
    {
        $cfg = self::config($kodeTujuan);
        $idPegawaiList = array_values(array_filter($idPegawaiList));
        if (! $cfg || empty($idPegawaiList) || ! $tanggalSumber) {
            return [];
        }

        $tanggal = Carbon::parse($tanggalSumber)->startOfDay();

        if ($cfg['auto_create']) {
            $produksi = $cfg['produksi']::query()
                ->whereDate($cfg['tanggal'], $tanggal->toDateString())
                ->first();
        } else {
            $q = self::produksiPadaTanggal($cfg, $tanggal)
                ->when($cfg['shift'], fn ($qq) => $qq->whereRaw('LOWER(shift) = ?', [strtolower((string) $shift)]));

            if (filled($idProduksi)) {
                $produksi = (clone $q)->whereKey($idProduksi)->first();
            } else {
                $kandidat = $q->get();
                $produksi = $kandidat->count() === 1 ? $kandidat->first() : null;
            }
        }

        if (! $produksi) {
            return [];
        }

        return $cfg['model']::query()
            ->where($cfg['fk'], $produksi->getKey())
            ->whereIn('id_pegawai', $idPegawaiList)
            ->with('pegawai')
            ->get()
            ->map(fn ($row) => $row->pegawai?->nama_pegawai ?? "Pegawai #{$row->id_pegawai}")
            ->all();
    }

    /** Pilihan mesin Hotpress. Tidak dipakai form pindah lagi, dibiarkan untuk kebutuhan lain. */
    public static function opsiMesinHotpress(): array
    {
        return Mesin::query()->where('kategori_mesin_id', 9)->orderBy('nama_mesin')->pluck('nama_mesin', 'id')->all();
    }

    /**
     * Pindahkan jam beberapa pegawai sekaligus. All-or-nothing: kalau satu gagal, semua dibatalkan.
     *
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @param  \Carbon\Carbon|string  $tanggalSumber  tanggal produksi ASAL yang sedang dibuka (dipakai
     *                                                 untuk mencari/membuat produksi tujuan pada tanggal yang sama)
     * @param  string  $modeDuplikat  'update' (gabung jam dengan baris yang sudah ada) atau
     *                                'timpa' (ganti baris yang sudah ada dengan data baru)
     * @return array{baru: int, digabung: int, ditimpa: int} rincian jumlah pegawai yang diproses
     *
     * @throws RuntimeException pesan siap tampil ke user
     */
    public static function pindahkan(
        string $kodeSumber,
        Collection $rows,
        string $kodeTujuan,
        float $durasiJam,
        $tanggalSumber,
        $idProduksi = null,
        ?string $shift = null,
        ?string $tugas = null,
        $idMesin = null,
        ?string $keterangan = null,
        string $modeDuplikat = 'update',
    ): array {
        $sumber = self::sumber()[$kodeSumber] ?? throw new RuntimeException('Lini asal tidak dikenal.');
        $tujuan = self::config($kodeTujuan) ?? throw new RuntimeException('Lini tujuan tidak dikenal.');

        if (! in_array($modeDuplikat, ['update', 'timpa'], true)) {
            $modeDuplikat = 'update';
        }

        $durasiMenit = (int) round($durasiJam * 60);
        if ($durasiMenit <= 0) {
            throw new RuntimeException('Durasi harus lebih dari 0 jam.');
        }
        if (! $tanggalSumber) {
            throw new RuntimeException('Tanggal produksi asal tidak ditemukan.');
        }
        $tanggal = Carbon::parse($tanggalSumber)->startOfDay();

        if ($tujuan['shift'] && blank($shift)) {
            throw new RuntimeException('Shift tujuan harus dipilih.');
        }
        if ($tujuan['mesin'] && blank($idMesin)) {
            throw new RuntimeException('Mesin tujuan harus dipilih.');
        }
        if ($tujuan['tugas'] === 'required' && blank($tugas) && blank($tujuan['tugas_default'])) {
            throw new RuntimeException("Tugas / meja di {$tujuan['label']} harus diisi.");
        }

        return DB::transaction(function () use ($kodeSumber, $sumber, $kodeTujuan, $tujuan, $rows, $durasiJam, $durasiMenit, $tanggal, $idProduksi, $shift, $tugas, $idMesin, $keterangan, $modeDuplikat) {
            $hasil = ['baru' => 0, 'digabung' => 0, 'ditimpa' => 0];

            foreach ($rows as $row) {
                $status = self::pindahSatu($kodeSumber, $sumber, $kodeTujuan, $tujuan, $row, $durasiJam, $durasiMenit, $tanggal, $idProduksi, $shift, $tugas, $idMesin, $keterangan, $modeDuplikat);
                $hasil[$status]++;
            }

            return $hasil;
        });
    }

    /** @return string 'baru' | 'digabung' | 'ditimpa' */
    private static function pindahSatu(
        string $kodeSumber,
        array $sumber,
        string $kodeTujuan,
        array $tujuan,
        $row,
        float $durasiJam,
        int $durasiMenit,
        Carbon $tanggal,
        $idProduksi,
        ?string $shift,
        ?string $tugas,
        $idMesin,
        ?string $keterangan,
        string $modeDuplikat,
    ): string {
        // Ambil ulang + kunci baris supaya tidak bentrok dengan edit lain
        $row = $sumber['model']::query()->lockForUpdate()->findOrFail($row->getKey());
        $nama = $row->pegawai?->nama_pegawai ?? "Pegawai #{$row->id_pegawai}";

        $masuk = self::waktu($row->{$sumber['masuk']});
        $pulangAsli = self::waktu($row->{$sumber['pulang']});
        if (! $masuk || ! $pulangAsli) {
            throw new RuntimeException("{$nama}: jam masuk/pulang belum terisi.");
        }

        $pulang = $pulangAsli->copy();
        if ($pulang->lessThanOrEqualTo($masuk)) {
            $pulang->addDay(); // shift lewat tengah malam
        }

        $totalMenit = (int) $masuk->diffInMinutes($pulang, true);
        if ($durasiMenit >= $totalMenit) {
            $jamKerja = round($totalMenit / 60, 2);
            throw new RuntimeException("{$nama}: durasi pindah ({$durasiJam} jam) harus lebih kecil dari jam kerja saat ini ({$jamKerja} jam).");
        }

        // Produksi tujuan: dipilih user (harus di tanggal yang sama), atau dibuat otomatis (Lain-lain)
        if ($tujuan['auto_create']) {
            $produksiTujuan = $tujuan['produksi']::query()
                ->whereDate($tujuan['tanggal'], $tanggal->toDateString())
                ->first()
                ?? $tujuan['produksi']::create([$tujuan['tanggal'] => $tanggal->toDateString()]);
        } else {
            $kandidat = self::produksiPadaTanggal($tujuan, $tanggal)
                ->when($tujuan['shift'], fn ($q) => $q->whereRaw('LOWER(shift) = ?', [strtolower((string) $shift)]))
                ->get();

            if (filled($idProduksi)) {
                $produksiTujuan = $kandidat->firstWhere('id', $idProduksi);
            } elseif ($kandidat->count() === 1) {
                // Hanya ada satu produksi di tanggal itu: langsung dipakai, user tidak perlu memilih
                $produksiTujuan = $kandidat->first();
            } elseif ($kandidat->count() > 1) {
                throw new RuntimeException("Ada lebih dari satu produksi {$tujuan['label']} pada tanggal ini, pilih produksi tujuannya.");
            } else {
                $produksiTujuan = null;
            }

            if (! $produksiTujuan) {
                $infoShift = $tujuan['shift'] ? ' shift '.ucfirst(strtolower((string) $shift)) : '';
                throw new RuntimeException("Produksi {$tujuan['label']} tanggal {$tanggal->format('d/m/Y')}{$infoShift} belum dibuat. Buat dulu produksi tujuan.");
            }
        }

        $pulangBaru = $pulang->copy()->subMinutes($durasiMenit);
        $jamPulangBaru = $pulangBaru->format('H:i:s');
        $jamPulangLama = $pulangAsli->format('H:i:s');

        // Cek duplikat: pegawai yang sama sudah ada baris di produksi tujuan ini?
        // (Mencegah dobel kalau tombol "Pindahkan" ke-klik 2x atau pegawai sudah pernah dipindah ke sini.)
        $existing = $tujuan['model']::query()
            ->where($tujuan['fk'], $produksiTujuan->getKey())
            ->where('id_pegawai', $row->id_pegawai)
            ->lockForUpdate()
            ->first();

        $ketPindah = $keterangan ?: "Pindahan dari {$sumber['label']} {$durasiJam} jam";

        if ($existing) {
            if ($modeDuplikat === 'timpa') {
                // TIMPA: baris lama diganti total dengan jam pindahan yang baru ini
                $masukFinal = $jamPulangBaru;
                $pulangFinal = $jamPulangLama;
                $ketFinal = $ketPindah;
                $status = 'ditimpa';
            } else {
                // UPDATE (default): gabungkan, jam kerja jadi rentang gabungan lama + baru
                $masukLama = self::waktu($existing->{$tujuan['masuk']});
                $pulangLama = self::waktu($existing->{$tujuan['pulang']});
                $masukBaruC = self::waktu($jamPulangBaru);
                $pulangBaruC = self::waktu($jamPulangLama);

                $masukFinal = ($masukLama && $masukLama->lessThan($masukBaruC) ? $masukLama : $masukBaruC)->format('H:i:s');
                $pulangFinal = ($pulangLama && $pulangLama->greaterThan($pulangBaruC) ? $pulangLama : $pulangBaruC)->format('H:i:s');

                $ketLamaTujuan = $existing->{$tujuan['ket']};
                $ketFinal = Str::limit(trim(($ketLamaTujuan ? $ketLamaTujuan.' | ' : '').$ketPindah), 250, '');
                $status = 'digabung';
            }

            $dataUpdate = [
                $tujuan['masuk'] => $masukFinal,
                $tujuan['pulang'] => $pulangFinal,
                $tujuan['ket'] => $ketFinal,
            ];
            if ($tujuan['tugas'] !== null && filled($tugas)) {
                $dataUpdate['tugas'] = $tugas;
            }
            $existing->update($dataUpdate);
            $baris = $existing;
        } else {
            $data = [
                $tujuan['fk'] => $produksiTujuan->getKey(),
                'id_pegawai' => $row->id_pegawai,
                $tujuan['masuk'] => $jamPulangBaru,
                $tujuan['pulang'] => $jamPulangLama,
                $tujuan['ket'] => $ketPindah,
            ];
            if ($tujuan['ijin_kosong']) {
                $data[$tujuan['ijin']] = '';
            }
            if ($tujuan['tugas'] !== null) {
                $data['tugas'] = filled($tugas) ? $tugas : $tujuan['tugas_default'];
            }
            if ($tujuan['mesin']) {
                $data['id_mesin'] = $idMesin;
            }
            if ($tujuan['model'] === LainLain::class) {
                $data['created_by'] = Auth::id();
            }
            $baris = $tujuan['model']::create($data);
            $status = 'baru';
        }

        // Kurangi jam pulang di produksi asal + catat keterangan.
        // Kalau pegawai ini sudah pernah dipindah ke tujuan YANG SAMA sebelumnya (belum dibatalkan),
        // catatannya digabung jadi satu baris dengan total jam terbaru, tidak ditambah dobel.
        $ketLama = $row->{$sumber['ket']};
        $totalMenitKeTujuanSebelumnya = (int) PindahPegawaiLog::query()
            ->where('sumber', $kodeSumber)
            ->where('id_sumber', $row->getKey())
            ->where('tujuan', $kodeTujuan)
            ->whereNull('dibatalkan_at')
            ->sum('durasi_menit');
        $totalJamKeTujuan = ($totalMenitKeTujuanSebelumnya + $durasiMenit) / 60;

        $row->{$sumber['pulang']} = $jamPulangBaru;
        $row->{$sumber['ket']} = self::gabungKeteranganPindah($ketLama, $tujuan['label'], $totalJamKeTujuan);
        $row->save();

        // Log, dipakai untuk fitur Batal Pindah + audit
        PindahPegawaiLog::create([
            'sumber' => $kodeSumber,
            'id_sumber' => $row->getKey(),
            'tujuan' => $kodeTujuan,
            'model_tujuan' => $tujuan['model'],
            'id_tujuan' => $baris->getKey(),
            'id_pegawai' => $row->id_pegawai,
            'tanggal' => $tanggal->toDateString(),
            'durasi_menit' => $durasiMenit,
            'pulang_lama' => $jamPulangLama,
            'pulang_baru' => $jamPulangBaru,
            'ket_sumber_lama' => $ketLama,
            'created_by' => Auth::id(),
        ]);

        return $status;
    }

    /** Log pindah terakhir yang masih aktif untuk baris sumber ini (null jika tidak ada). */
    public static function logAktif(string $kodeSumber, $idSumber): ?PindahPegawaiLog
    {
        return PindahPegawaiLog::query()
            ->where('sumber', $kodeSumber)
            ->where('id_sumber', $idSumber)
            ->whereNull('dibatalkan_at')
            ->latest('id')
            ->first();
    }

    /**
     * Batalkan pindah TERAKHIR: jam pulang asal dikembalikan, baris tujuan dihapus.
     *
     * PENTING: kalau pindahan tadi digabung ("update") ke baris yang sudah ada milik
     * pindahan lain sebelumnya, membatalkan ini akan MENGHAPUS seluruh baris gabungan
     * itu (bukan cuma mengurangi jamnya) — cek dulu datanya sebelum membatalkan.
     *
     * @throws RuntimeException
     */
    public static function batalkan(PindahPegawaiLog $log): void
    {
        DB::transaction(function () use ($log) {
            $log = PindahPegawaiLog::query()->lockForUpdate()->findOrFail($log->getKey());
            if ($log->dibatalkan_at) {
                throw new RuntimeException('Pindah ini sudah dibatalkan.');
            }

            $sumber = self::sumber()[$log->sumber] ?? throw new RuntimeException('Lini asal tidak dikenal.');
            $row = $sumber['model']::query()->lockForUpdate()->find($log->id_sumber);
            if (! $row) {
                throw new RuntimeException('Baris pegawai asal sudah dihapus, batalkan manual.');
            }

            $pulangSekarang = self::waktu($row->{$sumber['pulang']})?->format('H:i:s');
            if ($pulangSekarang !== Carbon::parse($log->pulang_baru)->format('H:i:s')) {
                throw new RuntimeException('Jam pulang pegawai ini sudah diubah setelah dipindah, sesuaikan manual.');
            }

            $log->model_tujuan::query()->whereKey($log->id_tujuan)->first()?->delete();

            $row->{$sumber['pulang']} = Carbon::parse($log->pulang_lama)->format('H:i:s');
            $row->{$sumber['ket']} = $log->ket_sumber_lama;
            $row->save();

            $log->update(['dibatalkan_at' => now()]);
        });
    }

    /** "1.50" -> "1.5", "2.00" -> "2" — angka jam yang enak dibaca di keterangan. */
    private static function formatJam(float $jam): string
    {
        return rtrim(rtrim(number_format($jam, 2, '.', ''), '0'), '.');
    }

    /**
     * Gabungkan catatan "Pindah X jam ke Y" ke keterangan lama, TANPA menduplikasi baris
     * kalau pegawai ini memang sudah pernah dipindah ke tujuan yang sama sebelumnya — baris
     * lama untuk tujuan itu diganti (bukan ditambah lagi) dengan total jam yang terbaru.
     */
    private static function gabungKeteranganPindah(?string $ketLama, string $labelTujuan, float $totalJamKeTujuan): string
    {
        $segmenBaru = 'Pindah '.self::formatJam($totalJamKeTujuan)." jam ke {$labelTujuan}";

        $bagian = collect(explode('|', (string) $ketLama))
            ->map(fn ($b) => trim($b))
            ->filter(fn ($b) => $b !== '' && ! preg_match('/^Pindah\s+[\d.,]+\s+jam\s+ke\s+'.preg_quote($labelTujuan, '/').'$/i', $b))
            ->push($segmenBaru);

        return Str::limit($bagian->implode(' | '), 250, '');
    }

    /** Waktu dijadikan Carbon di tanggal patokan, supaya aman dibandingkan. */
    private static function waktu($nilai): ?Carbon
    {
        if (blank($nilai)) {
            return null;
        }
        $t = Carbon::parse($nilai);

        return Carbon::create(2000, 1, 1, $t->hour, $t->minute, $t->second);
    }
}