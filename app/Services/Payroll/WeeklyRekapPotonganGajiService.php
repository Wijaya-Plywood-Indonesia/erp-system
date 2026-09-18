<?php

namespace App\Services\Payroll;

use App\Models\Pegawai;
use App\Services\RekapPotonganGajiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Layer AGREGASI di atas RekapPotonganGajiService::getDetailPotongan(),
 * yang sudah menjadi "resolver harian" per lini (target/hasil/potongan).
 *
 * Class ini TIDAK membuat formula potongan baru — hanya:
 *   1. Loop tiap tanggal dalam periode.
 *   2. Panggil RekapPotonganGajiService::getDetailPotongan($tanggal, $lini)
 *      apa adanya (behavior harian existing, tidak diubah).
 *   3. Kumpulkan semua baris detail harian.
 *   4. Group by (kodep + lini) — supaya data antar lini TIDAK tercampur
 *      untuk pegawai yang bekerja di lebih dari satu lini dalam periode.
 *   5. Sum target/hasil/kekurangan/potongan per grup.
 *   6. Filter hanya grup dengan potongan > 0 (default view Pengawas).
 *
 * Kenapa loop ke RekapPotonganGajiService (bukan langsung ke
 * TargetPotonganService): TargetPotonganService murni kalkulator
 * (menerima angka, tidak query DB, tidak tahu tanggal). Yang menyiapkan
 * input harian per lini dari database ADALAH RekapPotonganGajiService
 * (lewat HitungPotonganProduksiAction untuk Stik/Kedi, dan lewat
 * Load & DataMap untuk lini lain) — jadi service itulah "resolver harian"
 * yang dimaksud requirement, dan sudah cukup untuk di-reuse tanpa
 * menyentuh TargetPotonganService sama sekali.
 */
class WeeklyRekapPotonganGajiService
{
    public function __construct(
        protected RekapPotonganGajiService $harian,
    ) {}

    /**
     * @param  array<int,string>  $liniTerpilih  kosong = semua lini (lihat LiniProduksiResolver::keys())
     * @return Collection<int, array{
     *     kodep: string,
     *     nama_pegawai: string,
     *     lini: string,
     *     periode_mulai: string,
     *     periode_akhir: string,
     *     hari_kerja: int,
     *     target: float,
     *     hasil: float,
     *     persentase: float,
     *     kekurangan: float,
     *     gaji: float|null,
     *     potongan: int,
     *     detail: array<int, array{tanggal: string, lini: string, target: float, hasil: float, kekurangan: float, potongan: int}>,
     * }>
     */
    public function getRekap(string $tanggalMulai, string $tanggalAkhir, array $liniTerpilih = []): Collection
    {
        if ($tanggalMulai > $tanggalAkhir) {
            throw new \InvalidArgumentException('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.');
        }

        $semuaDetail = $this->kumpulkanDetailHarian($tanggalMulai, $tanggalAkhir, $liniTerpilih);

        if ($semuaDetail->isEmpty()) {
            return collect();
        }

        return $this->aggregate($semuaDetail, $tanggalMulai, $tanggalAkhir)
            // requirement #9: default hanya yang benar-benar menghasilkan potongan
            ->filter(fn (array $row) => $row['potongan'] > 0)
            ->sortByDesc('potongan')
            ->values();
    }

    /**
     * Lini apa saja yang PUNYA data pada periode ini (dipakai untuk
     * mengisi pilihan multi-select default di halaman Filament).
     *
     * @param  Collection  $rekap  hasil getRekap()
     * @return array<int,string>
     */
    public function liniRelevan(Collection $rekap): array
    {
        return $rekap->pluck('lini')->unique()->values()->all();
    }

    /**
     * Loop tanggal, panggil service harian existing, kumpulkan semua
     * baris detail (tanpa agregasi dulu).
     */
    protected function kumpulkanDetailHarian(string $tanggalMulai, string $tanggalAkhir, array $liniTerpilih): Collection
    {
        $semuaDetail = collect();

        $tanggal = Carbon::parse($tanggalMulai);
        $akhir = Carbon::parse($tanggalAkhir);

        while ($tanggal->lessThanOrEqualTo($akhir)) {
            $tglStr = $tanggal->toDateString();

            // Panggilan APA ADANYA ke service existing — tidak ada
            // perubahan behavior, tidak ada query tambahan di sini.
            $detailHarian = $this->harian->getDetailPotongan($tglStr, $liniTerpilih);

            foreach ($detailHarian as $row) {
                $semuaDetail->push($row);
            }

            $tanggal->addDay();
        }

        return $semuaDetail;
    }

    /**
     * Group semua baris detail by (kodep + lini), sum, dan lengkapi
     * nama_pegawai + gaji lewat SATU query (hindari N+1).
     */
    protected function aggregate(Collection $semuaDetail, string $tanggalMulai, string $tanggalAkhir): Collection
    {
        $grouped = $semuaDetail->groupBy(fn (array $row) => $row['kodep'].'|'.$row['lini']);

        $kodepList = $semuaDetail->pluck('kodep')->unique()->values();
        $pegawaiByKodep = Pegawai::query()
            ->whereIn('kode_pegawai', $kodepList)
            ->get(['kode_pegawai', 'nama_pegawai'])
            ->keyBy('kode_pegawai');

        return $grouped->map(function (Collection $rows, string $key) use ($tanggalMulai, $tanggalAkhir, $pegawaiByKodep) {
            [$kodep, $lini] = explode('|', $key, 2);

            $target = (float) $rows->sum('target');
            $hasil = (float) $rows->sum('hasil');
            $potongan = (int) $rows->sum('potongan');
            $kekurangan = (float) $rows->sum(fn ($r) => abs($r['selisih']));
            $pegawai = $pegawaiByKodep->get($kodep);

            return [
                'kodep' => $kodep,
                'nama_pegawai' => $pegawai?->nama_pegawai ?? "Kode: {$kodep} (tidak ditemukan)",
                'lini' => $lini,
                'periode_mulai' => $tanggalMulai,
                'periode_akhir' => $tanggalAkhir,
                'hari_kerja' => $rows->count(),
                'target' => $target,
                'hasil' => $hasil,
                'persentase' => $target > 0 ? round(($hasil / $target) * 100, 1) : 0.0,
                'kekurangan' => $kekurangan,
                'gaji' => $pegawai?->gaji !== null ? (float) $pegawai->gaji : null,
                'potongan' => $potongan,
                'detail' => $rows
                    ->map(fn (array $r) => [
                        'tanggal' => $r['tanggal'],
                        'lini' => $r['lini'],
                        'target' => $r['target'],
                        'hasil' => $r['hasil'],
                        'kekurangan' => abs($r['selisih']),
                        'potongan' => $r['potongan'],
                    ])
                    ->sortBy('tanggal')
                    ->values()
                    ->all(),
            ];
        })->values();
    }
}
