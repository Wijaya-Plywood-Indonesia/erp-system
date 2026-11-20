<?php

namespace App\Support\Hpp;

/**
 * Rincian HPP per m³ + asal lahan + asal mesin.
 *
 * Objek ini murni (tanpa database) sehingga mudah diuji. Dipakai di semua tahap:
 * veneer kering → (repair / joint) → veneer jadi. Setiap tahap hanya:
 *   - menambah komponen baru   (tambah)
 *   - menyesuaikan susut       (skala)
 *   - merata-ratakan stok      (gabung)
 */
final class KomponenHpp
{
    /** kunci => label. Urutan = urutan tampil. */
    public const KUNCI = [
        'kayu'           => 'Kayu',
        'pekerja_rotary' => 'Pekerja rotary',
        'mesin_rotary'   => 'Mesin rotary',
        'bahan_rotary'   => 'Bahan penolong rotary',
        'pekerja_kering' => 'Pekerja dryer/kedi',
        'mesin_kering'   => 'Mesin dryer/kedi',
        'pekerja_proses' => 'Pekerja repair/joint',
        'mesin_proses'   => 'Mesin joint (hanya lewat joint)',
        'bahan_proses'   => 'Bahan penolong repair/joint (tepung, lem pai, dll)',
        'lainnya'        => 'Tanpa rincian (stok awal/opname/nota)',
    ];

    /** Pengelompokan untuk kolom ringkas di log (hpp_kayu, hpp_pekerja, hpp_mesin, hpp_bahan_penolong). */
    public const KELOMPOK = [
        'kayu'    => ['kayu'],
        'pekerja' => ['pekerja_rotary', 'pekerja_kering', 'pekerja_proses'],
        'mesin'   => ['mesin_rotary', 'mesin_kering', 'mesin_proses'],
        'bahan'   => ['bahan_rotary', 'bahan_proses'],
        'lainnya' => ['lainnya'],
    ];

    /** @var array<string,float> Rp per m³ */
    public array $komponen;

    /** @var array<string,float> porsi m³ per id_lahan (jumlah = 1) */
    public array $lahan;

    /** @var array<string,float> porsi m³ per jenis mesin kering: dryer | kedi (jumlah = 1) */
    public array $mesin;

    /** true = HPP masih sementara (modal/produksi belum selesai) — tampil "estimasi" seperti log basah. */
    public bool $sementara = false;

    public function __construct(array $komponen = [], array $lahan = [], array $mesin = [])
    {
        $this->komponen = [];
        foreach (array_keys(self::KUNCI) as $k) {
            $this->komponen[$k] = (float) ($komponen[$k] ?? 0);
        }
        $this->lahan = self::normalisasi($lahan);
        $this->mesin = self::normalisasi($mesin);
    }

    public static function kosong(): self
    {
        return new self();
    }

    public static function dari(null|string|array $data): self
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data)) {
            return new self();
        }

        $k = new self($data['komponen'] ?? [], $data['lahan'] ?? [], $data['mesin'] ?? []);
        $k->sementara = (bool) ($data['sementara'] ?? false);

        return $k;
    }

    public function toArray(): array
    {
        $hasil = [
            'komponen' => array_map(fn ($v) => round($v, 4), array_filter($this->komponen, fn ($v) => abs($v) > 0.00001)),
            'lahan'    => array_map(fn ($v) => round($v, 6), $this->lahan),
            'mesin'    => array_map(fn ($v) => round($v, 6), $this->mesin),
        ];

        if ($this->sementara) {
            $hasil['sementara'] = true;
        }

        return $hasil;
    }

    public function total(): float
    {
        return array_sum($this->komponen);
    }

    /** @return array{kayu: float, pekerja: float, mesin: float, bahan: float, lainnya: float} */
    public function kelompok(): array
    {
        $hasil = [];
        foreach (self::KELOMPOK as $nama => $daftar) {
            $hasil[$nama] = array_sum(array_map(fn ($k) => $this->komponen[$k], $daftar));
        }

        return $hasil;
    }

    /** Tambah biaya per m³ pada satu komponen. */
    public function tambah(string $kunci, float $nilaiPerM3): self
    {
        if (! array_key_exists($kunci, self::KUNCI)) {
            throw new \InvalidArgumentException("Komponen HPP tidak dikenal: {$kunci}");
        }

        $baru = clone $this;
        $baru->komponen[$kunci] += $nilaiPerM3;

        return $baru;
    }

    /**
     * Kalikan semua komponen (mis. susut: input 4 m³ jadi 3,6 m³ → faktor 4/3,6).
     * Porsi lahan & mesin tidak berubah.
     */
    public function skala(float $faktor): self
    {
        $baru = clone $this;
        foreach ($baru->komponen as $k => $v) {
            $baru->komponen[$k] = $v * $faktor;
        }

        return $baru;
    }

    public function tandaiSementara(bool $sementara = true): self
    {
        $baru = clone $this;
        $baru->sementara = $sementara;

        return $baru;
    }

    /** Catat tipe mesin kering (dryer|kedi) sebagai 100% asal mesin. */
    public function denganMesin(?string $tipe): self
    {
        $baru = clone $this;
        $baru->mesin = $tipe ? [$tipe => 1.0] : [];

        return $baru;
    }

    /** Set komposisi lahan (porsi m³ per id_lahan). */
    public function denganLahan(array $porsi): self
    {
        $baru = clone $this;
        $baru->lahan = self::normalisasi($porsi);

        return $baru;
    }

    /**
     * Paksa total = $target dengan menaruh selisihnya di komponen "lainnya".
     * Dipakai agar rincian tidak pernah bertentangan dengan HPP Average yang tersimpan
     * (stok awal / opname / nota tidak punya rincian, jadi selisihnya jatuh ke "lainnya").
     * Bila rincian lebih besar dari target, semua komponen diskalakan turun.
     */
    public function rekonsiliasi(float $target): self
    {
        $total = $this->total();
        $baru  = clone $this;

        if ($target <= 0) {
            foreach ($baru->komponen as $k => $v) {
                $baru->komponen[$k] = 0.0;
            }

            return $baru;
        }

        if ($total > $target + 0.0001) {
            return $this->skala($target / $total);
        }

        $baru->komponen['lainnya'] += ($target - $total);

        return $baru;
    }

    /**
     * Rata-rata tertimbang m³ dari beberapa bagian.
     *
     * @param  array<int, array{0: float, 1: KomponenHpp}>  $bagian  [[m3, KomponenHpp], ...]
     */
    public static function gabung(array $bagian): self
    {
        $totalM3 = 0.0;
        foreach ($bagian as [$m3]) {
            $totalM3 += max(0.0, (float) $m3);
        }

        if ($totalM3 <= 0) {
            return new self();
        }

        $komponen  = array_fill_keys(array_keys(self::KUNCI), 0.0);
        $lahan     = [];
        $mesin     = [];
        $sementara = false;

        foreach ($bagian as [$m3, $k]) {
            $m3 = max(0.0, (float) $m3);
            if ($m3 <= 0) {
                continue;
            }
            $bobot     = $m3 / $totalM3;
            $sementara = $sementara || $k->sementara;

            foreach ($k->komponen as $nama => $nilai) {
                $komponen[$nama] += $nilai * $bobot;
            }

            // bagian tanpa catatan lahan/mesin dihitung "tidak_diketahui" agar jujur, bukan dibuang
            foreach (($k->lahan ?: ['tidak_diketahui' => 1.0]) as $id => $porsi) {
                $lahan[$id] = ($lahan[$id] ?? 0.0) + $porsi * $bobot;
            }
            foreach (($k->mesin ?: ['tidak_diketahui' => 1.0]) as $id => $porsi) {
                $mesin[$id] = ($mesin[$id] ?? 0.0) + $porsi * $bobot;
            }
        }

        $hasil            = new self($komponen, $lahan, $mesin);
        $hasil->sementara = $sementara;

        return $hasil;
    }

    /** @return array<string,float> */
    private static function normalisasi(array $porsi): array
    {
        $porsi = array_filter(array_map('floatval', $porsi), fn ($v) => $v > 0);
        $total = array_sum($porsi);

        if ($total <= 0) {
            return [];
        }

        return array_map(fn ($v) => $v / $total, $porsi);
    }
}