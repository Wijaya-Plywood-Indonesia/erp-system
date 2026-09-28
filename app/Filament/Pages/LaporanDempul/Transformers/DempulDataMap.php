<?php

namespace App\Filament\Pages\LaporanDempul\Transformers;

use App\Filament\Pages\LaporanDempul\Queries\LoadLaporanDempul;
use App\Models\ProduksiDempul;
use App\Services\Target\Resolvers\DempulTargetResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Hitung target & potongan Dempul (nama lama: "Malik Platform") PER
 * PASANGAN pegawai.
 *
 * Pola sama seperti Pilih Plywood: hasil dicatat per pasangan spesifik
 * lewat relasi DetailDempul::pegawais() - satu hari bisa punya beberapa
 * pasangan berbeda, masing-masing dihitung sebagai unit target sendiri.
 *
 * - Target dari mesin "DEMPUL": FLAT, satu angka saja (tidak dibedakan
 *   per ukuran/tebal/jenis kayu/grade).
 * - Target DISKALAKAN ke jumlah orang & jam kerja pasangan itu sendiri
 *   (bukan seluruh tim hari itu). Jam kerja diambil dari rencana
 *   pegawai (rencana_pegawai_dempuls), bukan dari detail_dempuls.
 * - Baris hasil tanpa pasangan tercatat dikelompokkan terpisah sebagai
 *   "Tanpa pasangan tercatat" dan tidak dipotong.
 */
class DempulDataMap
{
    private const ISTIRAHAT_MENIT = 60;

    /**
     * @return array<int, array> satu elemen per pasangan (bisa lebih dari
     *                           satu per tanggal kalau ada beberapa pasangan)
     */
    public static function make($collection): array
    {
        $out = [];
        foreach ($collection as $produksi) {
            foreach (self::makeProduksi($produksi) as $blok) {
                $out[] = $blok;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array> satu elemen per pasangan dalam produksi ini
     */
    public static function makeProduksi(ProduksiDempul $produksi): array
    {
        $produksi->loadMissing(LoadLaporanDempul::RELASI);

        $resolver = new DempulTargetResolver;
        $kolomTanggal = ProduksiDempul::kolomTanggalAktif();
        $tanggal = Carbon::parse($produksi->{$kolomTanggal})->format('d/m/Y');

        // Data absen (jam masuk/pulang) per id_pegawai, untuk dicocokkan
        // ke pasangan yang mengerjakan barang.
        $absenPerPegawai = [];
        foreach ($produksi->rencanaPegawaiDempuls as $rp) {
            if (! $rp->pegawai) {
                continue;
            }
            $idPegawai = (string) ($rp->id_pegawai ?? $rp->pegawai->id);
            $absenPerPegawai[$idPegawai] = [
                'id' => $rp->pegawai->kode_pegawai ?? '-',
                'nama' => $rp->pegawai->nama_pegawai ?? '-',
                'gaji' => (float) ($rp->pegawai->gaji ?? 0),
                'jam_masuk' => $rp->jam_masuk ? Carbon::parse($rp->jam_masuk)->format('H:i') : '-',
                'jam_pulang' => $rp->jam_pulang ? Carbon::parse($rp->jam_pulang)->format('H:i') : '-',
                'ijin' => $rp->ijin ?? '-',
                'keterangan' => $rp->keterangan ?? '-',
                'menit' => self::hitungMenitBersih($rp->jam_masuk, $rp->jam_pulang),
            ];
        }

        // Kelompokkan hasil per PASANGAN (kombinasi id pegawai yang sama).
        $pasanganMap = [];
        foreach ($produksi->detailDempuls as $detail) {
            $idsPasangan = $detail->pegawais->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all();
            $key = empty($idsPasangan) ? '__tanpa_pasangan__' : implode(',', $idsPasangan);

            if (! isset($pasanganMap[$key])) {
                $pasanganMap[$key] = [
                    'ids' => $idsPasangan,
                    'detail' => [],
                ];
            }
            $pasanganMap[$key]['detail'][] = $detail;
        }

        $blokList = [];

        foreach ($pasanganMap as $key => $pasangan) {
            $blokList[] = self::hitungBlokPasangan(
                $produksi,
                $tanggal,
                $pasangan['ids'],
                $pasangan['detail'],
                $absenPerPegawai,
                $resolver,
                $key === '__tanpa_pasangan__'
            );
        }

        return $blokList;
    }

    private static function hitungBlokPasangan(
        ProduksiDempul $produksi,
        string $tanggal,
        array $idsPasangan,
        array $detailList,
        array $absenPerPegawai,
        DempulTargetResolver $resolver,
        bool $tanpaPasangan
    ): array {
        /* 1. Pekerja pasangan ini + menit kerja */
        $daftarPekerja = [];
        $totalMenit = 0.0;
        $totalGajiPasangan = 0.0;

        foreach ($idsPasangan as $idPegawai) {
            $a = $absenPerPegawai[$idPegawai] ?? null;
            if (! $a) {
                continue;
            }
            $totalGajiPasangan += $a['gaji'];
            if ($a['menit'] !== null) {
                $totalMenit += $a['menit'];
            }
            $daftarPekerja[$idPegawai] = [
                'id' => $a['id'],
                'nama' => $a['nama'],
                'jam_masuk' => $a['jam_masuk'],
                'jam_pulang' => $a['jam_pulang'],
                'ijin' => $a['ijin'],
                'keterangan' => $a['keterangan'],
            ];
        }

        $jumlahPekerja = count($daftarPekerja);

        /* 2. Kumpulkan barang yang dikerjakan pasangan ini (untuk tampilan
         * rincian saja - target dihitung TOTAL, bukan per barang, karena
         * target Dempul cuma satu angka flat). */
        $perBarang = [];
        $hasilTotal = 0.0;

        foreach ($detailList as $detail) {
            $b = $detail->barangSetengahJadi;
            $u = $b?->ukuran;
            $hasil = (float) ($detail->hasil ?? 0);
            $hasilTotal += $hasil;

            $perBarang[] = [
                'ukuran' => $u ? self::formatUkuran($u) : '-',
                'jenis' => $b?->jenisBarang?->nama_jenis_barang ?? '-',
                'grade' => $b?->grade?->nama_grade ?? '-',
                'nomor_palet' => $detail->nomor_palet ?? '-',
                'hasil' => $hasil,
            ];
        }

        /* 3. Target flat, diskalakan ke jumlah orang & jam PASANGAN INI */
        $target = $tanpaPasangan ? null : $resolver->resolve();
        $punyaTarget = false;
        $targetAdj = 0.0;
        $targetNormal = null;
        $orangNormalTarget = null;
        $jamNormalTarget = null;
        $capaian = 0.0;
        $potonganTotalPasangan = 0.0;
        $potonganPerOrang = 0.0;

        if (! $target) {
            if (! $tanpaPasangan) {
                Log::warning('Target Dempul tidak ditemukan', [
                    'id_produksi' => $produksi->id,
                ]);
            }
        } else {
            $menitNormal = ((float) $target->jam) * 60;
            $orangNormal = max(1, (int) $target->orang);
            $ratePerOrgPerMenit = $menitNormal > 0
                ? ((float) $target->target / $menitNormal) / $orangNormal
                : 0.0;

            // Diskalakan ke total menit kerja PASANGAN INI SAJA.
            $targetAdj = $ratePerOrgPerMenit * $totalMenit;
            $capaian = $targetAdj > 0 ? ($hasilTotal / $targetAdj) * 100 : 0.0;
            $biayaPerUnit = (float) $target->potongan;
            $nilaiSatuHariPenuh = $targetAdj * $biayaPerUnit;

            $targetNormal = (float) $target->target;
            $orangNormalTarget = $orangNormal;
            $jamNormalTarget = (float) $target->jam;
            $punyaTarget = true;

            /* 4. Potongan pasangan -> dibagi rata ke anggota pasangan,
             * kelipatan 500. Per orang dibulatkan dulu, baru total
             * dihitung ULANG dari angka yang sudah dibulatkan supaya
             * konsisten antara header dan baris per pekerja. */
            if ($jumlahPekerja > 0) {
                $kekuranganPersen = max(0, 100 - $capaian) / 100;
                $potonganMentah = $kekuranganPersen * $nilaiSatuHariPenuh;
                $potonganPerOrang = round(($potonganMentah / $jumlahPekerja) / 500) * 500;
                $potonganTotalPasangan = $potonganPerOrang * $jumlahPekerja;
            }
        }

        foreach ($daftarPekerja as $idPegawai => &$p) {
            $p['pot_target'] = (int) $potonganPerOrang;
        }
        unset($p);

        return [
            'id_produksi' => $produksi->id,
            'tanggal' => $tanggal,
            'tanpa_pasangan' => $tanpaPasangan,
            'jumlah_pekerja' => $jumlahPekerja,
            'jam_aktual_rata' => $jumlahPekerja > 0 ? round($totalMenit / $jumlahPekerja / 60, 2) : 0,
            'per_barang' => $perBarang,
            'punya_target' => $punyaTarget,
            'capaian_global' => $capaian,
            'target_total' => $targetAdj,
            'target_normal' => $targetNormal,
            'target_normal_orang' => $orangNormalTarget,
            'target_normal_jam' => $jamNormalTarget,
            'hasil_total' => $hasilTotal,
            'selisih_total' => $hasilTotal - $targetAdj,
            'potongan_total_tim' => $potonganTotalPasangan,
            'potongan_per_orang' => (int) $potonganPerOrang,
            'potongan_melebihi_gaji' => $totalGajiPasangan > 0 && $potonganTotalPasangan > $totalGajiPasangan,
            'total_gaji_tim' => $totalGajiPasangan,
            'pekerja' => array_values($daftarPekerja),
        ];
    }

    private static function hitungMenitBersih($masuk, $pulang): ?float
    {
        if (! $masuk || ! $pulang) {
            return null;
        }

        $m = Carbon::parse(Carbon::parse($masuk)->format('H:i'));
        $p = Carbon::parse(Carbon::parse($pulang)->format('H:i'));
        if ($p->lessThan($m)) {
            $p->addDay();
        }

        return (float) max(0, $m->diffInMinutes($p) - self::ISTIRAHAT_MENIT);
    }

    private static function formatUkuran($u): string
    {
        return rtrim(rtrim(number_format((float) $u->panjang, 2, '.', ''), '0'), '.')
            .' x '.rtrim(rtrim(number_format((float) $u->lebar, 2, '.', ''), '0'), '.')
            .' x '.rtrim(rtrim(number_format((float) $u->tebal, 2, '.', ''), '0'), '.');
    }
}