<?php

namespace App\Services;

use App\Models\Pegawai;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rekap bulanan (rentang tanggal BEBAS, tidak harus awal-akhir bulan
 * kalender — bisa mis. 15 Agustus s/d 15 September) per pegawai, format
 * mirip contoh "8_Agustus_2026 - WAHANA.xlsx": satu baris per pegawai,
 * kolom = tiap tanggal (jam kerja + poin), plus Total Poin.
 *
 * SENGAJA dibuat sebagai service TERPISAH dari NewRekapAbsensiPegawaiService
 * (bukan modifikasi) — service itu tetap fokus rekap HARIAN (satu
 * tanggal), service ini memakainya sebagai building block dan mengulang
 * pemanggilannya untuk setiap tanggal dalam rentang.
 *
 * CATATAN PERFORMA: untuk rentang 1 bulan penuh (~31 hari), ini artinya
 * NewRekapAbsensiPegawaiService::getRekap() dipanggil 31x (masing-masing
 * query ke semua source + NewDataFinger 2x). Untuk v1 ini diterima demi
 * konsistensi logic (Haram #1-#4 di service itu tidak diduplikasi ulang
 * di sini) — kalau nanti kerasa lambat, pertimbangkan cache per-tanggal
 * atau proses via queued job.
 */
class RekapBulananService
{
    /**
     * Ambang jam kerja (dalam JAM, bukan menit) supaya 1 hari dihitung
     * 1 poin (hadir penuh). Di bawah ambang ini — TAPI tetap ada jam
     * kerja tercatat (bukan 0 sama sekali) — dianggap 0 poin. Contoh
     * nyata dari data WAHANA: 6 jam kerja tetap 0 poin, 9/10/11 jam = 1
     * poin. Nilai ini dikonfirmasi user: 7 jam.
     */
    protected const AMBANG_POIN_JAM = 7;

    public function __construct(
        protected NewRekapAbsensiPegawaiService $rekapHarianService,
    ) {
    }

    /**
     * @param  array<int>|null  $pegawaiIds  null/kosong = semua pegawai
     * @return Collection<int, array{
     *     id_pegawai: int,
     *     kode_pegawai: ?string,
     *     nama_pegawai: string,
     *     harian: array<string, array{jam_kerja: float, poin: int}>,
     *     total_poin: int,
     * }>
     */
    public function getRekap(string $tanggalMulai, string $tanggalSelesai, ?array $pegawaiIds = null): Collection
    {
        $periode = $this->buildPeriode($tanggalMulai, $tanggalSelesai);

        $pegawaiQuery = Pegawai::query();
        if (!empty($pegawaiIds)) {
            $pegawaiQuery->whereIn('id', $pegawaiIds);
        }

        $pegawaiList = $pegawaiQuery
            ->orderBy('kode_pegawai')
            ->get(['id', 'kode_pegawai', 'nama_pegawai']);

        if ($pegawaiList->isEmpty()) {
            return collect();
        }

        // Inisialisasi struktur hasil per pegawai dulu (supaya pegawai yang
        // tidak muncul di rekap harian manapun -- mis. tidak pernah masuk
        // sepanjang periode -- tetap tampil dengan jam kerja/poin 0, bukan
        // hilang dari tabel).
        //
        // SENGAJA plain array (bukan Collection) di sini -- Collection
        // pakai ArrayAccess, dan PHP tidak bisa modifikasi elemen array
        // BERSARANG lewat ArrayAccess ($hasil[$id]['harian'][$tgl] = ...
        // akan gagal dengan "Indirect modification of overloaded element").
        // Dibungkus jadi Collection lagi di akhir method.
        $hasil = [];
        foreach ($pegawaiList as $pegawai) {
            $hasil[$pegawai->id] = [
                'id_pegawai' => $pegawai->id,
                'kode_pegawai' => $pegawai->kode_pegawai,
                'nama_pegawai' => $pegawai->nama_pegawai,
                'harian' => array_fill_keys($periode, ['jam_kerja' => 0.0, 'poin' => 0]),
                'total_poin' => 0,
            ];
        }

        $idSet = array_keys($hasil);

        foreach ($periode as $tanggal) {
            $rekapHarian = $this->rekapHarianService->getRekap($tanggal);
            $byIdPegawai = $rekapHarian->keyBy('id_pegawai');

            foreach ($idSet as $idPegawai) {
                $row = $byIdPegawai->get($idPegawai);

                [$jamKerja, $poin] = $this->hitungJamKerjaDanPoin($row);

                $hasil[$idPegawai]['harian'][$tanggal] = [
                    'jam_kerja' => $jamKerja,
                    'poin' => $poin,
                ];
                $hasil[$idPegawai]['total_poin'] += $poin;
            }
        }

        return collect(array_values($hasil));
    }

    /**
     * Daftar tanggal (Y-m-d) dari $tanggalMulai s/d $tanggalSelesai
     * inklusif. Kalau urutannya kebalik (selesai < mulai), otomatis
     * ditukar supaya tidak menghasilkan array kosong.
     *
     * @return array<int, string>
     */
    public function buildPeriode(string $tanggalMulai, string $tanggalSelesai): array
    {
        $mulai = Carbon::parse($tanggalMulai)->startOfDay();
        $selesai = Carbon::parse($tanggalSelesai)->startOfDay();

        if ($selesai->lessThan($mulai)) {
            [$mulai, $selesai] = [$selesai, $mulai];
        }

        $list = [];
        for ($t = $mulai->copy(); $t->lessThanOrEqualTo($selesai); $t->addDay()) {
            $list[] = $t->format('Y-m-d');
        }

        return $list;
    }

    /**
     * Hitung jam kerja (jam, float, 2 desimal) & poin (0/1) untuk SATU
     * baris hasil NewRekapAbsensiPegawaiService::getRekap() di SATU
     * tanggal.
     *
     * Prioritas sumber jam kerja (dikonfirmasi user):
     *   1. jam_masuk & jam_pulang PRODUKSI (input pengawas) -- dipakai
     *      kalau durasinya > 0.
     *   2. Fallback ke jam_masuk_finger & jam_pulang_finger (hasil scan
     *      mesin) -- dipakai kalau data produksi kosong/durasinya 0.
     *      Menutup kasus pegawai (termasuk pengawas sendiri) yang tidak
     *      diinput oleh pengawas tapi tetap finger masuk & pulang.
     *
     * Poin = 1 kalau jam kerja >= AMBANG_POIN_JAM, selain itu 0
     * (termasuk kalau row null / tidak ada data sama sekali hari itu).
     *
     * @return array{0: float, 1: int}
     */
    protected function hitungJamKerjaDanPoin(?array $row): array
    {
        if (!$row) {
            return [0.0, 0];
        }

        $menit = $this->hitungDurasiMenit($row['jam_masuk'] ?? null, $row['jam_pulang'] ?? null);

        if ($menit <= 0) {
            $menit = $this->hitungDurasiMenit($row['jam_masuk_finger'] ?? null, $row['jam_pulang_finger'] ?? null);
        }

        $jamKerja = round($menit / 60, 2);
        $poin = $jamKerja >= self::AMBANG_POIN_JAM ? 1 : 0;

        return [$jamKerja, $poin];
    }

    /**
     * Duplikat SENGAJA dari NewRekapAbsensiPegawaiService::hitungDurasiMenit()
     * -- method aslinya `protected` di class lain jadi tidak bisa dipakai
     * lintas class. Logic-nya disamakan PERSIS supaya hasilnya konsisten:
     * durasi 0 kalau salah satu jam kosong/'-'/sama persis (kasus izin
     * 00:00-00:00), lintas tengah malam dihitung +1 hari.
     *
     * Kalau nanti method aslinya diubah, method ini WAJIB disamakan juga.
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
}