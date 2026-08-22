<?php

namespace App\Services;

use App\Models\NewDataFinger;
use App\Models\Pegawai;
use App\Services\AbsensiSources\AbsensiSourceInterface;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NewRekapAbsensiPegawaiService
{
    /**
     * Jadwal standar shift PAGI, dipakai sebagai FALLBACK acuan di
     * resolveJamFingerNonMalam() kalau jam_masuk/jam_pulang produksi
     * kosong (row hasil lengkapiSemuaPegawai(), atau source yang gak
     * ngasih jam kerja). Supaya grouping raw finger tetap bisa nebak
     * "lebih deket ke masuk atau pulang" walau gak ada data produksi
     * sama sekali, bukan cuma nyerah balik ke perilaku lama.
     *
     * NEW: diubah dari 08:00-16:00 -> 06:00-16:00 supaya simulasi pagi &
     * simulasi malam SALING MIRROR (pagi 06:00-16:00, malam 16:00-06:00),
     * jadi panel "Simulasi pagi" / "Simulasi malam" di UI beneran
     * merepresentasikan simulasi jadwal yang berlawanan, bukan cuma jam
     * kerja kantor generik. TIDAK mengubah logic/algoritma
     * resolveJamFingerNonMalam() sama sekali — cuma nilai konstanta.
     */
    protected const JAM_MASUK_SHIFT_PAGI_DEFAULT = '06:00:00';

    protected const JAM_PULANG_SHIFT_PAGI_DEFAULT = '20:00:00';

    /**
     * NEW: Jadwal standar shift MALAM, dipakai sebagai FALLBACK acuan di
     * validasiJamMasukFingerMalam() kalau jam_masuk produksi kosong (row
     * hasil lengkapiSemuaPegawai(), atau source yang gak ngasih jam
     * kerja). Sebelumnya kalau jam_masuk produksi kosong,
     * validasiJamMasukFingerMalam() langsung "nyerah" (kandidat
     * dikembalikan apa adanya tanpa validasi apapun). Sekarang, supaya
     * panel "Simulasi malam" di preview BENERAN simulasi (bukan raw tanpa
     * saringan), dipakai jadwal default ini sebagai acuan pengganti —
     * pola sama persis dengan JAM_MASUK_SHIFT_PAGI_DEFAULT di atas.
     *
     * PENTING: ini TIDAK mengubah SUMBER field yang divalidasi (Haram #1
     * tetap utuh — kandidatnya tetap recordHariIni?->jam_pulang). Ini
     * cuma acuan pengganti kalau acuan asli (jam_masuk produksi) kosong.
     */
    protected const JAM_MASUK_SHIFT_MALAM_DEFAULT = '16:00:00';

    protected const JAM_PULANG_SHIFT_MALAM_DEFAULT = '06:00:00';

    protected const TOLERANSI_SESI_TUNGGAL_MENIT = 15;

    /**
     * Batas total durasi kerja gabungan (SEMUA lini produksi milik satu
     * pegawai di tanggal yang sama) dalam menit. Kalau totalnya di bawah
     * ini, jam_masuk_finger & jam_pulang_finger TIDAK ditampilkan sama
     * sekali untuk hari itu.
     *
     * Kasus yang di-fix: pegawai diinput "Izin" di lain-lain dengan
     * jam_masuk/jam_pulang 00:00:00-00:00:00, TAPI karena dia shift malam
     * KEMARIN, sisa scan subuhnya hari ini ketangkep enrichWithFinger dan
     * salah nempel seolah dia scan beneran hari ini padahal cuma izin.
     *
     * TIDAK memakai durasi per-lini saja, melainkan TOTAL semua lini —
     * supaya kasus pegawai yang beneran kerja 08:00-10:00 di satu lini
     * tapi juga diinput izin 00:00:00-00:00:00 di lini/lain-lain lain
     * TETAP tampil fingernya (total 120 menit, bukan 0).
     */
    protected const BATAS_TOTAL_DURASI_MENIT = 60;

    /**
     * NEW: Batas berapa menit kandidat jam_masuk_finger shift malam
     * (Haram #1: recordHariIni?->jam_pulang) BOLEH lebih CEPAT/awal
     * dibanding jadwal jam_masuk produksi, sebelum dianggap tidak valid.
     *
     * REVISI TOTAL dari pendekatan lama (validasi jarak absolut 1 arah
     * dengan threshold 7 jam lalu 15 jam) — pendekatan itu dibuang karena
     * threshold tunggal ke SATU acuan gampang salah di dua arah sekaligus:
     * kependekan bisa nge-hide checkout valid yang kebetulan jauh dari
     * jadwal masuk, kepanjangan bisa meloloskan scan yang jelas-jelas
     * jauh lebih dekat ke jadwal PULANG (lihat kasus Bayu Dewantoro:
     * jadwal masuk 17:00, kandidat 06:03 — 11 jam LEBIH CEPAT dari jadwal
     * masuk, ikut lolos di threshold 15 jam padahal itu jelas sisa scan
     * checkout shift kemarin).
     *
     * ATURAN BARU (lebih sederhana & terarah): kandidat HANYA digugurkan
     * kalau dia LEBIH CEPAT dari jadwal masuk lebih dari
     * TOLERANSI_MASUK_LEBIH_CEPAT_MALAM_MENIT. Kalau kandidat SAMA DENGAN
     * atau LEBIH TELAT dari jadwal masuk (berapa pun telatnya), TETAP
     * lolos apa adanya — TIDAK ada batas atas untuk keterlambatan, cuma
     * batas untuk "terlalu cepat/pagi".
     *
     * Kenapa cukup 1 arah (cuma soal "kecepetan") dan bukan 2 arah lagi:
     * kalau kandidat sama sekali bukan scan masuk (misal sisa scan
     * checkout shift kemarin), nilainya SELALU lebih kecil/lebih pagi
     * dari jadwal masuk shift malam yang biasanya sore/malam hari (mis.
     * 17:00, 22:00) — jadi cukup dicek "seberapa jauh dia di BELAKANG
     * jadwal masuk", tidak perlu bandingkan ke jadwal pulang segala.
     *
     * Dipakai untuk sisi MASUK (jam_masuk_finger). Sisi PULANG
     * (jam_pulang_finger, dari recordBesok?->jam_masuk) divalidasi
     * SIMETRIS lewat validasiJamPulangFingerMalam() & konstanta
     * TOLERANSI_PULANG_LEBIH_LAMBAT_MALAM_MENIT di bawah — keduanya
     * SAMA-SAMA divalidasi, tidak ada sisi yang dibiarkan mentah tanpa
     * saringan.
     */
    protected const TOLERANSI_MASUK_LEBIH_CEPAT_MALAM_MENIT = 300; // 5 jam

    /**
     * NEW: Batas berapa menit kandidat jam_pulang_finger shift malam
     * (Haram #1: recordBesok?->jam_masuk) BOLEH lebih TELAT/lambat
     * dibanding jadwal jam_pulang produksi, sebelum dianggap tidak valid.
     *
     * Simetris dengan TOLERANSI_MASUK_LEBIH_CEPAT_MALAM_MENIT di atas,
     * tapi arahnya kebalik: kandidat pulang = scan PERTAMA di tanggal
     * besok. Kalau scan itu jauh lebih TELAT dari jadwal pulang shift
     * malam, kemungkinan besar itu BUKAN scan checkout shift malam hari
     * ini — melainkan scan check-in shift BERIKUTNYA (mis. shift malam
     * berikutnya, atau shift lain) yang kebetulan jadi scan pertama di
     * hari itu. Kandidat yang SAMA atau LEBIH CEPAT dari jadwal pulang
     * (checkout lebih awal, berapa pun cepatnya) TETAP lolos apa adanya —
     * tidak ada batas bawah untuk checkout yang lebih cepat.
     *
     * Dipisah dari konstanta masuk (walau nilainya sama, 5 jam) supaya
     * bisa diubah independen kalau nanti ternyata butuh angka beda.
     */
    protected const TOLERANSI_PULANG_LEBIH_LAMBAT_MALAM_MENIT = 300; // 5 jam

    /** @var AbsensiSourceInterface[] */
    protected array $sources;

    public function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    public function getRekap(string $tanggal): Collection
    {
        // ⚠️ Urutan pipeline ini HARAM diacak (lihat README — Haram #4):
        // normalisasiJam() → gabungkanMultiSumber() → enrichWithFinger()
        // → urutkanByGrupKode(). enrichWithFinger butuh id_pegawai yang
        // sudah bersih dari gabungkanMultiSumber; normalisasiJam harus di
        // depan supaya format jam konsisten sebelum dipakai di step lain.
        $rekap = collect($this->sources)
            ->flatMap(fn ($source) => $source->fetch($tanggal))
            ->values();
        // Beberapa source (mis. Repair) return jam_masuk/jam_pulang dalam
        // format datetime penuh (Y-m-d H:i:s), sementara source lain sudah
        // H:i:s saja. Normalisasi semua ke H:i:s di sini supaya konsisten
        // sebelum diproses lebih lanjut (gabung sumber, sorting, dst).
        $rekap = $this->normalisasiJam($rekap);
        $rekap = $this->gabungkanMultiSumber($rekap);
        $rekap = $this->lengkapiSemuaPegawai($rekap);
        $rekap = $this->enrichWithFinger($rekap, $tanggal);

        return $this->urutkanByGrupKode($rekap);
    }

    /**
     * Normalisasi field jam_masuk & jam_pulang jadi format H:i:s saja,
     * apapun format aslinya dari source (bisa H:i:s murni atau
     * Y-m-d H:i:s / datetime penuh). Pakai Carbon::parse() karena bisa
     * handle kedua format itu sekaligus.
     */
    protected function normalisasiJam(Collection $rekap): Collection
    {
        return $rekap->map(function ($row) {
            foreach (['jam_masuk', 'jam_pulang'] as $field) {
                if (! empty($row[$field])) {
                    try {
                        $row[$field] = Carbon::parse($row[$field])->format('H:i:s');
                    } catch (\Throwable $e) {
                        // Kalau gagal parse (format aneh/tak terduga),
                        // biarkan apa adanya daripada bikin error.
                    }
                }
            }

            return $row;
        });
    }

    /**
     * Tentukan shift ('malam' / 'pagi' / null) HANYA berdasarkan
     * perbandingan jam_pulang vs jam_masuk PRODUKSI — bukan lagi dari
     * field 'shift' mentah yang dikirim source.
     *
     * Rule: jam_pulang < jam_masuk (strict, bukan <=) => malam, karena itu
     * berarti pulangnya "lewat tengah malam" (mis. masuk 22:00, pulang
     * 06:00 -> 06:00 < 22:00 -> malam).
     *
     * ⚠️ SENGAJA pakai '<' bukan '<=' — kalau jam_masuk === jam_pulang
     * persis sama (kasus "Izin" 00:00:00-00:00:00), itu BUKAN shift malam,
     * itu cuma durasi 0. Ini konsisten dengan hitungDurasiMenit() yang
     * sudah lebih dulu menangani kasus sama persis sebagai 0 menit (bukan
     * 1440 menit lintas hari). Kalau dipaksa '<=', semua row izin
     * 00:00-00:00 bakal salah kedeteksi sebagai shift malam.
     *
     * Return null kalau salah satu jam kosong / '-' / gagal parse — row
     * seperti ini (mis. hasil lengkapiSemuaPegawai(), pegawai tanpa data
     * produksi) diperlakukan sebagai NON-malam di semua pemanggil,
     * sama seperti perilaku lama waktu field 'shift' kosong.
     */
    protected function tentukanShiftDariJam(?string $jamMasuk, ?string $jamPulang): ?string
    {
        if (empty($jamMasuk) || empty($jamPulang) || $jamMasuk === '-' || $jamPulang === '-') {
            return null;
        }
        try {
            $masuk = Carbon::parse($jamMasuk)->format('H:i:s');
            $pulang = Carbon::parse($jamPulang)->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }

        return $pulang < $masuk ? 'malam' : 'pagi';
    }

    /**
     * NEW: Validasi khusus SISI MASUK shift malam. Menggantikan seluruh
     * pendekatan validasi jarak absolut yang lama (7 jam lalu 15 jam,
     * dua arah masuk & pulang) — lihat catatan panjang di
     * TOLERANSI_MASUK_LEBIH_CEPAT_MALAM_MENIT di atas untuk alasannya.
     *
     * $kandidatMasukFinger = recordHariIni?->jam_pulang (SUMBER-nya TETAP
     * sama persis dengan Haram #1 — fungsi ini TIDAK mengganti sumber
     * field, cuma menentukan apakah nilainya layak dipakai atau tidak).
     *
     * Logic: hitung selisih $jamMasukProduksi (acuan) dikurangi
     * $kandidatMasukFinger. Kalau kandidat LEBIH CEPAT dari acuan (nilai
     * positif) dan selisihnya > TOLERANSI_MASUK_LEBIH_CEPAT_MALAM_MENIT,
     * berarti kandidat ini kemungkinan besar sisa scan checkout shift
     * SEBELUMNYA yang nyangkut, bukan scan masuk beneran → null (jadi
     * '-'). Kalau kandidat SAMA atau LEBIH TELAT dari acuan (selisih <=
     * 0), TETAP lolos apa adanya — tidak ada batas atas untuk telat.
     *
     * Kalau jam_masuk produksi kosong/'-' (row hasil
     * lengkapiSemuaPegawai(), atau source yang gak ngasih jam kerja),
     * dipakai JAM_MASUK_SHIFT_MALAM_DEFAULT (16:00) sebagai acuan
     * pengganti — pola sama seperti fallback JAM_MASUK_SHIFT_PAGI_DEFAULT
     * di resolveJamFingerNonMalam().
     *
     * Perhitungan selisih TIDAK sirkular (tidak dibungkus ke rentang 24
     * jam) — kandidat & acuan sama-sama waktu di tanggal kalender yang
     * sama, jadi pengurangan langsung sudah benar tanpa perlu pembulatan
     * lintas tengah malam.
     *
     * ⚠️ FIX (whitebox review): sebelumnya selisih dihitung pakai
     * `$tAcuan->diffInMinutes($tKandidat, false) * -1`. Parameter kedua
     * `false` di Carbon memang bikin hasilnya signed, TAPI arah tanda
     * plus/minus-nya bergantung pada konvensi internal Carbon yang gak
     * selalu terdokumentasi jelas dan bisa beda perilaku antar versi —
     * persis kelas bug yang bisa bikin arah filter kebalik tanpa error
     * apapun, cuma datanya salah diam-diam. Sekarang dihitung manual
     * lewat selisih Unix timestamp (getTimestamp()), yang gak ambigu dan
     * gampang diaudit siapa pun yang baca kodenya nanti (lihat
     * README — Haram #7).
     */
    protected function validasiJamMasukFingerMalam(?string $kandidatMasukFinger, ?string $jamMasukProduksi): ?string
    {
        if (empty($kandidatMasukFinger) || $kandidatMasukFinger === '-') {
            return $kandidatMasukFinger;
        }

        $acuanJamMasuk = (! empty($jamMasukProduksi) && $jamMasukProduksi !== '-')
            ? $jamMasukProduksi
            : self::JAM_MASUK_SHIFT_MALAM_DEFAULT;

        try {
            $tAcuan = Carbon::parse($acuanJamMasuk);
            $tKandidat = Carbon::parse($kandidatMasukFinger);
        } catch (\Throwable $e) {
            // Gagal parse -> gak bisa divalidasi, pasang apa adanya
            // (fail-safe ke perilaku lama, konsisten dengan try-catch
            // di seluruh service ini).
            return $kandidatMasukFinger;
        }

        // Positif = kandidat lebih CEPAT/awal dari acuan (dalam menit).
        // Negatif atau 0 = kandidat sama/lebih TELAT dari acuan.
        // Dihitung manual lewat Unix timestamp — TIDAK pakai
        // diffInMinutes(..., false) (lihat Haram #7 di README).
        $menitLebihCepat = ($tAcuan->getTimestamp() - $tKandidat->getTimestamp()) / 60;

        return $menitLebihCepat > self::TOLERANSI_MASUK_LEBIH_CEPAT_MALAM_MENIT
            ? null
            : $kandidatMasukFinger;
    }

    /**
     * NEW: Validasi khusus SISI PULANG shift malam. Simetris dengan
     * validasiJamMasukFingerMalam() di atas, tapi arahnya kebalik.
     *
     * $kandidatPulangFinger = recordBesok?->jam_masuk (SUMBER-nya TETAP
     * sama persis dengan Haram #1 — fungsi ini TIDAK mengganti sumber
     * field, cuma menentukan apakah nilainya layak dipakai atau tidak).
     *
     * Logic: hitung selisih $kandidatPulangFinger dikurangi
     * $jamPulangProduksi (acuan). Kalau kandidat LEBIH TELAT dari acuan
     * (nilai positif) dan selisihnya >
     * TOLERANSI_PULANG_LEBIH_LAMBAT_MALAM_MENIT, kandidat ini kemungkinan
     * besar scan check-in shift BERIKUTNYA yang nyangkut jadi scan
     * pertama besok, bukan scan checkout shift malam hari ini → null
     * (jadi '-'). Kalau kandidat SAMA atau LEBIH CEPAT dari acuan
     * (selisih <= 0), TETAP lolos apa adanya — tidak ada batas bawah
     * untuk checkout yang lebih awal.
     *
     * Kalau jam_pulang produksi kosong/'-' (row hasil
     * lengkapiSemuaPegawai(), atau source yang gak ngasih jam kerja),
     * dipakai JAM_PULANG_SHIFT_MALAM_DEFAULT (06:00) sebagai acuan
     * pengganti — pola sama seperti fallback di
     * validasiJamMasukFingerMalam().
     *
     * ⚠️ FIX (whitebox review): sama seperti validasiJamMasukFingerMalam(),
     * selisih SEKARANG dihitung manual lewat getTimestamp(), BUKAN
     * `diffInMinutes(..., false) * -1` — lihat penjelasan lengkap di
     * validasiJamMasukFingerMalam() dan README Haram #7.
     */
    protected function validasiJamPulangFingerMalam(?string $kandidatPulangFinger, ?string $jamPulangProduksi): ?string
    {
        if (empty($kandidatPulangFinger) || $kandidatPulangFinger === '-') {
            return $kandidatPulangFinger;
        }

        $acuanJamPulang = (! empty($jamPulangProduksi) && $jamPulangProduksi !== '-')
            ? $jamPulangProduksi
            : self::JAM_PULANG_SHIFT_MALAM_DEFAULT;

        try {
            $tAcuan = Carbon::parse($acuanJamPulang);
            $tKandidat = Carbon::parse($kandidatPulangFinger);
        } catch (\Throwable $e) {
            // Gagal parse -> gak bisa divalidasi, pasang apa adanya
            // (fail-safe ke perilaku lama, konsisten dengan try-catch
            // di seluruh service ini).
            return $kandidatPulangFinger;
        }

        // Positif = kandidat lebih TELAT/lambat dari acuan (dalam menit).
        // Negatif atau 0 = kandidat sama/lebih CEPAT dari acuan.
        // Dihitung manual lewat Unix timestamp — TIDAK pakai
        // diffInMinutes(..., false) (lihat Haram #7 di README).
        $menitLebihLambat = ($tKandidat->getTimestamp() - $tAcuan->getTimestamp()) / 60;

        return $menitLebihLambat > self::TOLERANSI_PULANG_LEBIH_LAMBAT_MALAM_MENIT
            ? null
            : $kandidatPulangFinger;
    }

    /**
     * Pastikan SEMUA pegawai dari tabel `pegawais` muncul di rekap, bukan
     * cuma yang kebetulan ke-fetch dari source hari itu. Pegawai yang tidak
     * punya data dari source manapun akan ditambahkan sebagai row kosong
     * (jam_masuk, jam_pulang, shift = null) supaya tetap kelihatan di
     * laporan sebagai "tidak ada data" pada tanggal tersebut.
     *
     * Ditaruh SETELAH gabungkanMultiSumber (supaya key id_pegawai yang
     * dipakai untuk dedupe sudah bersih) dan SEBELUM enrichWithFinger
     * (supaya pegawai yang row-nya baru ditambahkan di sini tetap bisa
     * dapat jam_masuk_finger/jam_pulang_finger kalau ternyata dia ada
     * scan finger walau tidak ke-fetch dari source manapun).
     *
     * Row hasil method ini SENGAJA tidak diberi '_total_durasi_menit'
     * (field itu cuma dihitung di gabungkanMultiSumber, untuk row yang
     * benar-benar berasal dari source produksi). enrichWithFinger
     * memperlakukan absennya field ini sebagai "tidak perlu di-suppress",
     * supaya pegawai yang sama sekali tidak ada data produksi tetap bisa
     * ke-enrich finger seperti perilaku sebelumnya.
     */
    protected function lengkapiSemuaPegawai(Collection $rekap): Collection
    {
        $idPegawaiSudahAda = $rekap
            ->pluck('id_pegawai')
            ->filter()
            ->unique();
        $pegawaiBelumAda = Pegawai::query()
            ->whereNotIn('id', $idPegawaiSudahAda)
            ->get(['id', 'kode_pegawai', 'nama_pegawai']);
        if ($pegawaiBelumAda->isEmpty()) {
            return $rekap;
        }
        $rowKosong = $pegawaiBelumAda->map(fn ($pegawai) => [
            'id_pegawai' => $pegawai->id,
            'kode_pegawai' => $pegawai->kode_pegawai,
            'nama_pegawai' => $pegawai->nama_pegawai,
            'shift' => null,
            'jam_masuk' => null,
            'jam_pulang' => null,
            'sumber_label' => [],
        ]);

        return $rekap->concat($rowKosong)->values();
    }

    protected function enrichWithFinger(Collection $rekap, string $tanggal): Collection
    {
        if ($rekap->isEmpty()) {
            return $rekap;
        }
        $idPegawaiList = $rekap->pluck('id_pegawai')->filter()->unique();
        $kodeByIdPegawai = Pegawai::query()
            ->whereIn('id', $idPegawaiList)
            ->pluck('kode_pegawai', 'id');
        $kodePegawaiList = $kodeByIdPegawai->values()->unique();
        $tanggalBerikutnya = Carbon::parse($tanggal)->addDay()->format('Y-m-d');
        // ⚠️ HARAM diubah jadi query 1 tanggal saja (lihat README — Haram
        // #2). jam_pulang_finger shift malam SELALU null kalau ini
        // dihapus, karena datanya emang ada di tanggal besok.
        $fingerHariIni = NewDataFinger::query()
            ->whereDate('tanggal', $tanggal)
            ->whereIn('kode_pegawai', $kodePegawaiList)
            ->get()
            ->keyBy('kode_pegawai');
        $fingerBesok = NewDataFinger::query()
            ->whereDate('tanggal', $tanggalBerikutnya)
            ->whereIn('kode_pegawai', $kodePegawaiList)
            ->get()
            ->keyBy('kode_pegawai');

        return $rekap->map(function ($row) use ($kodeByIdPegawai, $fingerHariIni, $fingerBesok) {
            $kode = $kodeByIdPegawai->get($row['id_pegawai']);
            $row['kode_pegawai'] = $kode;
            if (! $kode) {
                $row['jam_masuk_finger'] = null;
                $row['jam_pulang_finger'] = null;
                // NEW: preview kosong buat row yang gak punya kode pegawai
                // sama sekali, supaya key ini selalu konsisten ada di tiap
                // row (blade tinggal cek null-nya, gak perlu isset()).
                $row['_finger_preview'] = null;
                unset($row['_total_durasi_menit']);

                return $row;
            }
            // Total durasi kerja gabungan SEMUA lini produksi milik pegawai
            // ini di tanggal yang sama (dihitung di gabungkanMultiSumber).
            // Kalau totalnya di bawah BATAS_TOTAL_DURASI_MENIT -> jangan
            // tampilkan finger sama sekali untuk hari ini.
            //
            // Field ini SENGAJA absen (null-coalesce ke null lewat ??)
            // untuk row hasil lengkapiSemuaPegawai() — pegawai tanpa data
            // produksi sama sekali TIDAK di-suppress oleh rule ini, supaya
            // perilaku lama untuk kasus itu tetap terjaga.
            $totalDurasi = $row['_total_durasi_menit'] ?? null;
            unset($row['_total_durasi_menit']);
            if ($totalDurasi !== null && $totalDurasi < self::BATAS_TOTAL_DURASI_MENIT) {
                $row['jam_masuk_finger'] = null;
                $row['jam_pulang_finger'] = null;
                // NEW: kalau finger di-suppress karena durasi kurang dari
                // batas, preview juga gak usah nampilin apa-apa (biar
                // tombol expand di blade otomatis gak muncul untuk row ini).
                $row['_finger_preview'] = null;

                return $row;
            }
            $recordHariIni = $fingerHariIni->get($kode);
            // NEW: $recordBesok DIPINDAH ke atas (sebelumnya cuma di-fetch
            // di dalam blok `if ($shift === 'malam')`). Ini TIDAK mengubah
            // hasil jam_masuk_finger/jam_pulang_finger sama sekali — untuk
            // row non-malam nilainya tetap tidak dipakai untuk field itu
            // (Haram #1 utuh). Satu-satunya alasan dipindah: dipakai buat
            // isi _finger_preview di bawah, tanpa nambah query baru (cuma
            // lookup ke collection $fingerBesok yang memang sudah di-fetch
            // unconditional sesuai Haram #2).
            $recordBesok = $fingerBesok->get($kode);
            // ⚠️ Shift SEKARANG ditentukan dari perbandingan jam produksi
            // (jam_pulang < jam_masuk => malam), BUKAN dari field 'shift'
            // mentah yang dikirim source. Lihat tentukanShiftDariJam().
            // Ini TIDAK mengubah logic swap field di bawah (Haram #1) —
            // hanya mengubah CARA menentukan apakah row ini "malam" atau
            // bukan.
            $shift = $this->tentukanShiftDariJam($row['jam_masuk'] ?? null, $row['jam_pulang'] ?? null);
            $row['shift'] = $shift;
            if ($shift === 'malam') {
                // ⚠️ HARAM diubah (lihat README — Haram #1). Mesin finger
                // nyatet berdasarkan tanggal kalender scan terjadi, bukan
                // berdasarkan sesi shift. Scan malam hari H (jam masuk
                // kerja) tercatat sebagai jam_pulang device di tanggal H
                // (scan terakhir hari itu). Scan subuh H+1 (jam pulang
                // kerja) tercatat sebagai jam_masuk device di tanggal H+1
                // (scan pertama hari itu). JANGAN disederhanakan jadi
                // jam_masuk -> jam_masuk_finger.
                //
                // Hasil swap Haram #1 untuk SISI MASUK
                // (recordHariIni?->jam_pulang) divalidasi dulu lewat
                // validasiJamMasukFingerMalam() sebelum dipasang — kalau
                // kandidat itu LEBIH CEPAT dari jadwal jam_masuk produksi
                // dengan selisih > TOLERANSI_MASUK_LEBIH_CEPAT_MALAM_MENIT
                // (5 jam), dianggap sisa scan pulang shift malam
                // SEBELUMNYA yang nyangkut, bukan scan masuk beneran, jadi
                // di-null-kan jadi '-'. SUMBER field-nya
                // (recordHariIni?->jam_pulang) TETAP 100% sama dengan
                // Haram #1 — ini cuma filter tambahan setelahnya.
                //
                // Sisi PULANG (recordBesok?->jam_masuk) SAMA-SAMA
                // divalidasi lewat validasiJamPulangFingerMalam() — kalau
                // kandidat itu LEBIH TELAT dari jadwal jam_pulang produksi
                // dengan selisih > TOLERANSI_PULANG_LEBIH_LAMBAT_MALAM_MENIT
                // (5 jam), dianggap scan check-in shift BERIKUTNYA yang
                // nyangkut, bukan scan checkout beneran, jadi di-null-kan
                // jadi '-'. SUMBER field-nya (recordBesok?->jam_masuk)
                // TETAP 100% sama dengan Haram #1 — ini cuma filter
                // tambahan setelahnya, simetris dengan sisi masuk di atas.
                $row['jam_masuk_finger'] = $this->validasiJamMasukFingerMalam(
                    $recordHariIni?->jam_pulang,
                    $row['jam_masuk'] ?? null
                );
                $row['jam_pulang_finger'] = $this->validasiJamPulangFingerMalam(
                    $recordBesok?->jam_masuk,
                    $row['jam_pulang'] ?? null
                );
            } else {
                [$row['jam_masuk_finger'], $row['jam_pulang_finger']] = $this->resolveJamFingerNonMalam(
                    $recordHariIni?->jam_masuk,
                    $recordHariIni?->jam_pulang,
                    $row['jam_masuk'] ?? null,
                    $row['jam_pulang'] ?? null
                );
            }
            // NEW: preview data mentah finger untuk expandable row di UI —
            // supaya user bisa lihat raw scan yang jadi dasar
            // jam_masuk_finger / jam_pulang_finger tanpa perlu buka data
            // finger terpisah. Field ini PURELY ADDITIVE — tidak dibaca
            // oleh logic manapun di service ini, cuma dikonsumsi blade.
            //
            // UPDATE: 'besok' SEKARANG diisi kapanpun $recordBesok ada,
            // TIDAK lagi digantung ke ($shift === 'malam'). Ini murni
            // preview/simulasi tambahan supaya admin tetap bisa cross-
            // check raw finger besok walau row-nya kedeteksi non-malam
            // (mis. shift salah kedeteksi, atau memang cuma mau
            // memastikan gak ada scan nyangkut). TIDAK mempengaruhi
            // jam_masuk_finger/jam_pulang_finger yang beneran dipakai —
            // itu tetap 100% ikut Haram #1 (cuma di-assign utk shift
            // malam di atas).
            $row['_finger_preview'] = [
                'hari_ini' => $recordHariIni ? [
                    'tanggal' => $recordHariIni->tanggal,
                    'jam_masuk' => $recordHariIni->jam_masuk,
                    'jam_pulang' => $recordHariIni->jam_pulang,
                ] : null,
                'besok' => $recordBesok ? [
                    'tanggal' => $recordBesok->tanggal,
                    'jam_masuk' => $recordBesok->jam_masuk,
                    'jam_pulang' => $recordBesok->jam_pulang,
                ] : null,
                // NEW: simulasi HASIL HITUNGAN (bukan cuma raw) seandainya
                // row ini diperlakukan lewat cabang shift malam (Haram
                // #1): jam_masuk_finger diambil dari jam_pulang hari ini,
                // jam_pulang_finger diambil dari jam_masuk besok. Dihitung
                // SELALU, terlepas dari $shift row ini sebenarnya apa —
                // supaya admin bisa cross-check "seandainya ini malam,
                // hasilnya bakal begini". PURELY ADDITIVE untuk preview,
                // TIDAK PERNAH dipakai untuk mengisi jam_masuk_finger /
                // jam_pulang_finger yang asli (itu tetap murni ikut Haram
                // #1 di percabangan if/else di atas).
                //
                // NEW: preview simulasi_malam SEKARANG memvalidasi sisi
                // MASUK-nya juga lewat validasiJamMasukFingerMalam(),
                // TAPI acuannya SELALU JAM_MASUK_SHIFT_MALAM_DEFAULT
                // (16:00) tetap, BUKAN jadwal produksi asli row ini. Row
                // non-malam (mis. shift pagi) punya $row['jam_masuk']
                // berisi jadwal PAGI-nya (mis. 06:00), yang salah dipakai
                // sebagai acuan "seandainya dia malam" — makanya di sini
                // parameter acuan SENGAJA dikosongkan (null) supaya
                // validasiJamMasukFingerMalam() otomatis fallback ke
                // JAM_MASUK_SHIFT_MALAM_DEFAULT (lihat isi fungsinya).
                // Sisi pulang tetap raw apa adanya (gak difilter), sama
                // seperti branch REAL di atas. TIDAK menyentuh branch REAL
                // shift malam sama sekali.
                // Sisi pulang SEKARANG juga divalidasi lewat
                // validasiJamPulangFingerMalam(), acuannya SELALU
                // JAM_PULANG_SHIFT_MALAM_DEFAULT (06:00) dipaksa (parameter
                // acuan produksi dikosongkan/null), simetris dengan sisi
                // masuk di atas. TIDAK menyentuh branch REAL shift malam
                // sama sekali.
                'simulasi_malam' => [
                    'jam_masuk_finger' => $this->validasiJamMasukFingerMalam(
                        $recordHariIni?->jam_pulang,
                        null
                    ),
                    'jam_pulang_finger' => $this->validasiJamPulangFingerMalam(
                        $recordBesok?->jam_masuk,
                        null
                    ),
                ],
                // NEW: simulasi HASIL HITUNGAN seandainya row ini
                // diperlakukan lewat cabang NON-malam (resolveJamFingerNonMalam()
                // — Haram #6: toleransi 15 menit, dedupe arah per-pasangan,
                // fallback jadwal default shift pagi). Dihitung
                // dengan MEMANGGIL ULANG resolveJamFingerNonMalam() apa
                // adanya (tidak ada logic baru / duplikat di luar fungsi
                // itu), supaya 100% konsisten dengan hasil asli untuk row
                // yang memang non-malam. Dihitung SELALU, terlepas dari
                // $shift row ini sebenarnya apa — sama seperti
                // 'simulasi_malam' di atas, PURELY ADDITIVE untuk preview.
                // TIDAK PERNAH dipakai untuk mengisi jam_masuk_finger /
                // jam_pulang_finger yang asli (itu tetap murni ikut
                // percabangan if/else di atas, Haram #1 & #6 utuh tidak
                // tersentuh).
                // FIX: simulasi_pagi SEKARANG selalu dipaksa pakai jadwal
                // default PAGI (JAM_MASUK_SHIFT_PAGI_DEFAULT /
                // JAM_PULANG_SHIFT_PAGI_DEFAULT), BUKAN jadwal produksi
                // asli row ($row['jam_masuk']/$row['jam_pulang']) —
                // konsisten dengan simulasi_malam yang juga selalu paksa
                // default malam. Sebelumnya row shift malam (mis. jadwal
                // 17:00-06:00) ikut dipakai sebagai acuan "seandainya dia
                // pagi", padahal itu jelas BUKAN jadwal pagi — cuma
                // kebetulan hasilnya sering terlihat benar. Parameter
                // acuan produksi SENGAJA dikosongkan (null) di sini
                // supaya resolveJamFingerNonMalam() otomatis fallback ke
                // JAM_MASUK_SHIFT_PAGI_DEFAULT / JAM_PULANG_SHIFT_PAGI_DEFAULT.
                'simulasi_pagi' => (function () use ($recordHariIni) {
                    [$simMasuk, $simPulang] = $this->resolveJamFingerNonMalam(
                        $recordHariIni?->jam_masuk,
                        $recordHariIni?->jam_pulang,
                        null,
                        null
                    );

                    return [
                        'jam_masuk_finger' => $simMasuk,
                        'jam_pulang_finger' => $simPulang,
                    ];
                })(),
                // NEW: sama persis dengan 'simulasi_pagi' di atas —
                // memanggil ULANG resolveJamFingerNonMalam() apa adanya
                // (tidak ada logic baru/duplikat di luar fungsi itu), jadi
                // otomatis kena toleransi 15 menit TOLERANSI_SESI_TUNGGAL_MENIT
                // untuk deteksi 1-sesi-vs-2-sesi, dedupe arah berdasarkan
                // diff terkecil per-pasangan (Haram #6), dan fallback ke
                // JAM_MASUK_SHIFT_PAGI_DEFAULT / JAM_PULANG_SHIFT_PAGI_DEFAULT
                // karena parameter jam produksi di sini SENGAJA
                // dikosongkan/null — persis alasan yang sama dengan
                // 'simulasi_pagi' di atas (dipaksa selalu pakai default
                // shift pagi, BUKAN jadwal produksi asli row).
                //
                // BEDANYA HANYA SATU: raw yang dipakai di sini adalah raw
                // finger BESOK ($recordBesok->jam_masuk /
                // $recordBesok->jam_pulang), bukan raw finger hari ini
                // ($recordHariIni). Tujuannya supaya kolom "Finger Masuk
                // (Besok)" / "Finger Pulang (Besok)" di
                // NewRekapAbsensiExport (kolom L-M, sumbernya dari
                // simulasi_pagi_besok) ikut kena logic dedupe 1-sesi-vs-
                // 2-sesi yang sama seperti kolom "(Hari Ini)" (kolom J-K,
                // sumbernya dari simulasi_pagi), alih-alih menampilkan
                // raw finger besok mentah apa adanya.
                //
                // Dihitung SELALU, terlepas dari $shift row ini sebenarnya
                // apa — sama seperti 'simulasi_pagi' & 'simulasi_malam' di
                // atas, PURELY ADDITIVE untuk preview. TIDAK PERNAH dipakai
                // untuk mengisi jam_masuk_finger/jam_pulang_finger yang
                // asli (itu tetap murni ikut percabangan if/else di atas).
                // TIDAK menyentuh Haram #1, #6, atau #7 manapun.
                'simulasi_pagi_besok' => (function () use ($recordBesok) {
                    [$simMasuk, $simPulang] = $this->resolveJamFingerNonMalam(
                        $recordBesok?->jam_masuk,
                        $recordBesok?->jam_pulang,
                        null,
                        null
                    );

                    return [
                        'jam_masuk_finger' => $simMasuk,
                        'jam_pulang_finger' => $simPulang,
                    ];
                })(),
            ];

            return $row;
        });
    }

    /**
     * Khusus shift NON-malam. Kalau finger cuma di-upload untuk satu sesi
     * scan (mis. upload pagi doang), raw jam_masuk & jam_pulang dari mesin
     * finger jadi hampir sama persis (selisih beberapa detik), karena
     * dua-duanya diambil dari scan yang sama. Kalau dibiarkan apa adanya,
     * jam_masuk_finger & jam_pulang_finger jadi kembar padahal cuma 1 tap.
     *
     * Deteksi "1 sesi vs 2 sesi" TETAP pakai toleransi 15 menit antara raw
     * jam_masuk & jam_pulang finger, seperti sebelumnya:
     * - > toleransi -> 2 sesi scan beneran (masuk pagi, pulang sore/malam).
     *   Biarkan apa adanya.
     * - <= toleransi -> SUDAH PASTI 1 sesi scan, harus didedupe ke salah
     *   satu kolom (jam_masuk_finger ATAU jam_pulang_finger, gak dua-duanya).
     *
     * PENENTUAN ARAH (versi baru, per-pasangan, bukan titik scan tunggal):
     * - diffKePulang = selisih raw jam_pulang finger ke jam_pulang PRODUKSI
     *   (dari $row, hasil getRekap sebelum di-enrich).
     * - diffKeMasuk  = selisih raw jam_masuk finger ke jam_masuk PRODUKSI.
     * - Kedua selisih dihitung pakai Carbon::diffInMinutes(), yang SUDAH
     *   otomatis nilai absolut (kalau hasil pengurangan minus, otomatis
     *   dijadikan plus) — jadi TIDAK bandingkan satu titik scan tunggal ke
     *   dua acuan sekaligus seperti versi lama, tapi bandingkan tiap raw ke
     *   acuan PASANGANNYA SENDIRI.
     * - diffKePulang < diffKeMasuk -> scan ini lebih "mirip" jam pulang ->
     *   JANGAN tampilkan jam_masuk_finger (return [null, rawPulang]).
     * - Selain itu (diffKePulang >= diffKeMasuk) -> JANGAN tampilkan
     *   jam_pulang_finger (return [rawMasuk, null]).
     * - TIDAK ADA syarat "harus di dalam toleransi" untuk assign ini —
     *   begitu terbukti 1 sesi (lolos pengecekan toleransi di atas), harus
     *   tetap didedupe ke salah satu kolom, seberapa pun jauhnya jarak scan
     *   dari kedua acuan.
     *
     * Kalau jam_masuk/jam_pulang produksi kosong (row hasil
     * lengkapiSemuaPegawai(), atau source yang gak ngasih jam kerja),
     * TETAP dicoba di-grouping — pakai jadwal standar shift pagi
     * (JAM_MASUK_SHIFT_PAGI_DEFAULT / JAM_PULANG_SHIFT_PAGI_DEFAULT)
     * sebagai acuan pengganti, bukan langsung nyerah ke perilaku lama.
     *
     * Fallback ke perilaku lama (pasang jam_masuk_finger & jam_pulang_finger
     * apa adanya dari record finger) HANYA kalau: raw masuk/pulang finger
     * beneran berjauhan (bukan 1 sesi), atau parsing gagal.
     *
     * @return array{0: ?string, 1: ?string} [jam_masuk_finger, jam_pulang_finger]
     */
    protected function resolveJamFingerNonMalam(
        ?string $rawMasuk,
        ?string $rawPulang,
        ?string $jamMasukProduksi,
        ?string $jamPulangProduksi
    ): array {
        // Kalau salah satu raw kosong, gak ada apa-apa buat dibandingkan —
        // pasang apa adanya seperti perilaku lama.
        if (! $rawMasuk || ! $rawPulang || $rawMasuk === '-' || $rawPulang === '-') {
            return [$rawMasuk, $rawPulang];
        }
        try {
            $tRawMasuk = Carbon::parse($rawMasuk);
            $tRawPulang = Carbon::parse($rawPulang);
        } catch (\Throwable $e) {
            return [$rawMasuk, $rawPulang];
        }
        // Raw masuk & pulang finger berjauhan (> toleransi) -> memang 2 sesi
        // scan beneran (masuk pagi, pulang sore/malam). Biarkan seperti biasa.
        if (abs($tRawMasuk->diffInMinutes($tRawPulang)) > self::TOLERANSI_SESI_TUNGGAL_MENIT) {
            return [$rawMasuk, $rawPulang];
        }
        // Sampai sini berarti raw masuk & pulang SUDAH PASTI 1 sesi scan.
        // Coba parse jam masuk/pulang produksi. Jika kosong atau tidak valid,
        // pakai jadwal standar shift pagi sebagai acuan.
        $tJamMasukProduksi = null;
        if (! empty($jamMasukProduksi) && $jamMasukProduksi !== '-') {
            try {
                $tJamMasukProduksi = Carbon::parse($jamMasukProduksi);
            } catch (\Throwable $e) {
            }
        }
        $tJamMasukProduksi ??= Carbon::parse(self::JAM_MASUK_SHIFT_PAGI_DEFAULT);
        $tJamPulangProduksi = null;
        if (! empty($jamPulangProduksi) && $jamPulangProduksi !== '-') {
            try {
                $tJamPulangProduksi = Carbon::parse($jamPulangProduksi);
            } catch (\Throwable $e) {
            }
        }
        $tJamPulangProduksi ??= Carbon::parse(self::JAM_PULANG_SHIFT_PAGI_DEFAULT);
        // Bandingkan tiap raw ke acuan PASANGANNYA SENDIRI.
        $diffKePulang = abs($tRawPulang->diffInMinutes($tJamPulangProduksi));
        $diffKeMasuk = abs($tRawMasuk->diffInMinutes($tJamMasukProduksi));

        // diffKePulang < diffKeMasuk -> scan ini lebih dekat ke pulang ->
        // jangan tampilkan jam_masuk_finger. Selain itu -> jangan tampilkan
        // jam_pulang_finger.
        return $diffKePulang < $diffKeMasuk
            ? [null, $rawPulang]
            : [$rawMasuk, null];
    }

    public function availableSources(): Collection
    {
        return collect($this->sources)->map(fn ($s) => [
            'key' => $s->key(),
            'label' => $s->label(),
        ]);
    }

    public function getAbsensiLainLain(string $tanggal): Collection
    {
        $rekap = $this->getRekap($tanggal);
        $kodeSudahAdaProduksi = $rekap->pluck('kode_pegawai')->filter()->unique();
        // ⚠️ HARAM dihapus (lihat README — Haram #3). Pegawai shift malam
        // yang scan-nya "nyangkut" ke tanggal besok (lihat catatan di
        // enrichWithFinger) akan muncul lagi di data mesin finger hari
        // ini (sisa scan subuhnya), padahal dia sudah sah tercatat shift
        // malam KEMARIN. Tanpa exclusion ini, dia akan salah kedeteksi
        // sebagai anomali "absen finger tapi gak ada di rekap produksi".
        $kodeShiftMalamKemarin = $this->getKodePegawaiShiftMalam(
            Carbon::parse($tanggal)->subDay()->format('Y-m-d')
        );
        $kodeDikecualikan = $kodeSudahAdaProduksi->merge($kodeShiftMalamKemarin)->unique();
        $semuaFinger = NewDataFinger::query()
            ->whereDate('tanggal', $tanggal)
            ->get();
        $fingerTanpaProduksi = $semuaFinger->filter(
            fn ($item) => ! $kodeDikecualikan->contains($item->kode_pegawai)
        );
        if ($fingerTanpaProduksi->isEmpty()) {
            return collect();
        }
        $kodeList = $fingerTanpaProduksi->pluck('kode_pegawai')->unique();
        $pegawaiByKode = Pegawai::query()
            ->whereIn('kode_pegawai', $kodeList)
            ->get()
            ->keyBy('kode_pegawai');

        return $fingerTanpaProduksi->map(function ($item) use ($pegawaiByKode) {
            $pegawai = $pegawaiByKode->get($item->kode_pegawai);

            return [
                'kode_pegawai' => $item->kode_pegawai,
                'nama_pegawai' => $pegawai?->nama_pegawai ?? "Kode: {$item->kode_pegawai} (tidak ditemukan)",
                'jam_masuk' => $item->jam_masuk,
                'jam_pulang' => $item->jam_pulang,
                'tanggal' => $item->tanggal,
            ];
        })->sortBy('nama_pegawai')->values();
    }

    /**
     * Gabung row milik pegawai yang sama dari >1 lini produksi (source)
     * jadi 1 row.
     *
     * Representasi utama yang dipakai (jam_masuk, jam_pulang, shift, dst)
     * adalah row dengan DURASI KERJA TERPANJANG di antara semua lini
     * (bukan lagi row pertama berdasar urutan source seperti sebelumnya).
     * Ini supaya kalau pegawai kebetulan "Izin" (00:00:00-00:00:00) di
     * satu lini tapi punya jam kerja beneran di lini lain, jam kerja
     * beneran itu yang tampil sebagai representasi, bukan jam izinnya.
     *
     * '_total_durasi_menit' = jumlah durasi kerja SEMUA lini (bukan cuma
     * yang jadi representasi). Dipakai oleh enrichWithFinger() untuk
     * memutuskan apakah jam_masuk_finger/jam_pulang_finger perlu
     * disembunyikan (total < BATAS_TOTAL_DURASI_MENIT), supaya kasus
     * "kerja 08:00-10:00 di lini A + izin 00:00-00:00 di lini B" tetap
     * dihitung total 120 menit dan fingernya TETAP tampil.
     */
    protected function gabungkanMultiSumber(Collection $rekap): Collection
    {
        return $rekap
            ->groupBy(fn ($row) => $row['id_pegawai'] ?? $row['nama_pegawai'])
            ->map(function ($rows) {
                $rowsWithDurasi = $rows->map(function ($row) {
                    $row['_durasi_menit'] = $this->hitungDurasiMenit(
                        $row['jam_masuk'] ?? null,
                        $row['jam_pulang'] ?? null
                    );

                    return $row;
                });
                // Representasi utama = durasi kerja terpanjang. Kalau semua
                // durasi sama (termasuk semua 0), sortByDesc tetap stabil
                // ambil yang pertama muncul, jadi untuk kasus tanpa
                // perbedaan durasi perilakunya sama seperti sebelumnya.
                $utama = $rowsWithDurasi->sortByDesc('_durasi_menit')->first();
                $utama['sumber_label'] = $rowsWithDurasi
                    ->pluck('sumber_label')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $utama['_total_durasi_menit'] = $rowsWithDurasi->sum('_durasi_menit');
                unset($utama['_durasi_menit']);

                return $utama;
            })
            ->values();
    }

    /**
     * Hitung durasi kerja dalam menit dari jam_masuk & jam_pulang (format
     * H:i:s, sudah dinormalisasi oleh normalisasiJam() sebelum method ini
     * dipanggil).
     *
     * Return 0 kalau:
     * - salah satu jam kosong / '-' / gagal parse.
     * - jam_masuk === jam_pulang persis sama (kasus izin
     *   "00:00:00-00:00:00" HARUS dianggap 0 menit, BUKAN 24 jam —
     *   makanya dicek eksplisit duluan sebelum logic lintas-hari di
     *   bawah, supaya gak ketriger addDay() dan jadi 1440 menit).
     *
     * Kalau jam_pulang <= jam_masuk (dan bukan kasus sama persis di atas),
     * dianggap lintas tengah malam (+1 hari) supaya durasi shift malam
     * tetap positif dan masuk akal.
     */
    protected function hitungDurasiMenit(?string $jamMasuk, ?string $jamPulang): int
    {
        if (empty($jamMasuk) || empty($jamPulang) || $jamMasuk === '-' || $jamPulang === '-') {
            return 0;
        }
        if ($jamMasuk === $jamPulang) {
            return 0;
        }
        try {
            $masuk = Carbon::parse($jamMasuk);
            $pulang = Carbon::parse($jamPulang);
        } catch (\Throwable $e) {
            return 0;
        }
        if ($pulang->lessThanOrEqualTo($masuk)) {
            $pulang->addDay();
        }

        return (int) $masuk->diffInMinutes($pulang);
    }

    /**
     * Urutkan hasil rekap. Untuk brand WAHANA, pakai grup prioritas kode:
     *   1. Kode 8000-8999
     *   2. Kode 9000-9999
     *   3. Kode 7000-7999
     *   4. Sisanya (0-6999 dan kode di luar rentang manapun)
     * Di dalam SETIAP grup (termasuk grup 8000/9000/7000), diurutkan
     * kode_pegawai ASCENDING (kecil ke besar) — BUKAN nama_pegawai.
     * Untuk brand LAIN (Wijaya, dst), urutan balik ke simpel: kode_pegawai
     * ascending seperti biasa (tanpa grouping).
     */
    protected function urutkanByGrupKode(Collection $rekap): Collection
    {
        if (! $this->isBrandWahana()) {
            return $rekap
                ->sortBy(function ($row) {
                    $kode = $row['kode_pegawai'] ?? null;

                    return $kode && is_numeric($kode) ? (int) $kode : PHP_INT_MAX;
                })
                ->values();
        }
        // Hitung dulu nomor grup + kunci urutan kedua sebagai field biasa
        // di tiap row, baru sortBy pakai nama field. Ini supaya sortBy
        // multi-kolom Laravel bisa membandingkan lewat data_get() secara
        // langsung, alih-alih lewat closure kustom yang gampang salah pakai.
        //
        // ⚠️ HARAM diganti ke nama_pegawai (lihat README — Haram #5). Kunci
        // urutan kedua SAMA untuk semua grup (0,1,2,3): kode_pegawai
        // ascending (zero-padded supaya string-compare tetap benar secara
        // numerik). Kode kosong/invalid ditaruh paling belakang dalam
        // grupnya masing-masing.
        $rekap = $rekap->map(function ($row) {
            $grup = $this->grupKode($row['kode_pegawai'] ?? null);
            $row['_grup_urutan'] = $grup;
            $kode = $row['kode_pegawai'] ?? null;
            $row['_sort_kedua'] = ($kode && is_numeric($kode))
                ? str_pad((string) (int) $kode, 10, '0', STR_PAD_LEFT)
                : str_repeat('9', 10); // kode kosong/invalid ditaruh paling belakang dalam grupnya

            return $row;
        });

        return $rekap
            ->sortBy(['_grup_urutan', '_sort_kedua'])
            ->values()
            ->map(function ($row) {
                // Field internal, gak perlu ikut ke view
                unset($row['_grup_urutan'], $row['_sort_kedua']);

                return $row;
            });
    }

    protected function isBrandWahana(): bool
    {
        $panel = Filament::getCurrentPanel()
            ?? Filament::getPanel('admin');

        return $panel?->getBrandName() === 'Wahana';
    }

    protected function grupKode(?string $kodePegawai): int
    {
        if (! $kodePegawai || ! is_numeric($kodePegawai)) {
            return 4; // kode kosong/tidak valid ditaruh paling belakang
        }
        $kode = (int) $kodePegawai;

        return match (true) {
            $kode >= 8000 && $kode <= 8999 => 0,
            $kode >= 9000 && $kode <= 9999 => 1,
            $kode >= 7000 && $kode <= 7999 => 2,
            default => 3, // sisanya: 0-6999 dan kode di luar rentang manapun
        };
    }

    /**
     * Ambil semua kode_pegawai yang shift-nya 'malam' di tanggal tertentu.
     * Fetch langsung dari sources (bukan dari hasil getRekap() yang sudah
     * diproses), sama seperti sebelumnya.
     *
     * ⚠️ Deteksi shift malam SEKARANG pakai tentukanShiftDariJam() (bandingkan
     * jam_pulang vs jam_masuk mentah dari source), BUKAN field 'shift'
     * mentah lagi. Row mentah di sini belum lewat normalisasiJam(), tapi
     * itu aman karena tentukanShiftDariJam() sudah parse pakai Carbon::parse()
     * sendiri (bisa handle H:i:s maupun datetime penuh).
     */
    protected function getKodePegawaiShiftMalam(string $tanggal): Collection
    {
        $rekap = collect($this->sources)
            ->flatMap(fn ($source) => $source->fetch($tanggal))
            ->values();
        if ($rekap->isEmpty()) {
            return collect();
        }
        $idPegawaiShiftMalam = $rekap
            ->filter(fn ($row) => $this->tentukanShiftDariJam(
                $row['jam_masuk'] ?? null,
                $row['jam_pulang'] ?? null
            ) === 'malam')
            ->pluck('id_pegawai')
            ->filter()
            ->unique();
        if ($idPegawaiShiftMalam->isEmpty()) {
            return collect();
        }

        return Pegawai::query()
            ->whereIn('id', $idPegawaiShiftMalam)
            ->pluck('kode_pegawai');
    }
}
