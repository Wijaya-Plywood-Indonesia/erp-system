<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

use App\Models\Ukuran;
use App\Models\JenisKayu;
use App\Models\JenisBarang;
use App\Models\Grade;
use App\Models\HppVeneerBasahSummary;
use App\Models\HppVeneerBasahLog;
use App\Models\StokVeneerJadi;
use App\Models\HppVeneerJadiLog;
use App\Models\StokVeneerKering;
use App\Models\StokPlatformMth;
use App\Models\HppPlatformMthLog;
use App\Models\StokTriplekMth;
use App\Models\HppTriplekMthLog;
use App\Models\StokPlywoodSiapJual;
use App\Models\HppPlywoodSiapJualLog;
use App\Models\StokPlatformJadi;
use App\Models\HppPlatformJadiLog;
use App\Models\StokTriplekJadi;
use App\Models\HppTriplekJadiLog;
use App\Models\StokGudangSatu;
use App\Models\GudangSatuLog;

class OpnameStokTable extends Component
{
    // Label mapping untuk tampil di blade
    const JENIS_STOK_LABELS = [
        'veneer_basah'  => 'Veneer Basah',
        'veneer_kering' => 'Veneer Kering',
        'veneer_jadi'   => 'Veneer Jadi',
        'platform_mth'  => 'Platform MTH',
        'triplek_mth'   => 'Triplek MTH',
        'plywood'       => 'Plywood Siap Jual',
        'platform_jadi' => 'Platform Jadi',
        'triplek_jadi'  => 'Triplek Jadi',
        'gudang_satu'   => 'Gudang Satu',
    ];

    /** Field kunci yang memicu refresh stok sistem */
    const FIELD_KUNCI = ['id_jenis_kayu', 'id_jenis_barang', 'id_ukuran', 'kw'];

    public string $jenisStok       = '';
    public bool   $headerCollapsed = false;
    public array  $rows            = [];
    public array  $originalRows    = [];
    public array  $jenisKayuOptions   = [];
    public array  $jenisBarangOptions = [];
    public array  $ukuranOptions      = [];
    public array  $gradeOptions       = [];

    public function mount(): void
    {
        $this->jenisKayuOptions   = JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id')->toArray();
        $this->jenisBarangOptions = JenisBarang::orderBy('nama_jenis_barang')->pluck('nama_jenis_barang', 'id')->toArray();
        $this->ukuranOptions      = Ukuran::all()->pluck('dimensi', 'id')->toArray();
        $this->gradeOptions       = Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade')->toArray();
    }

    public function toggleHeader(): void
    {
        $this->headerCollapsed = !$this->headerCollapsed;
    }

    public function updatedJenisStok(): void
    {
        $this->rows            = $this->loadRows($this->jenisStok);
        $this->originalRows    = $this->rows;
        $this->headerCollapsed = true;
    }

    // ────────────────────────────────────────────────────────────
    // MANIPULASI BARIS (berbasis _uid, bukan index)
    // ────────────────────────────────────────────────────────────
    public function tambahBaris(): void
    {
        $this->rows[] = $this->barisKosong();
    }

    public function hapusBaris(string $uid): void
    {
        $this->rows = array_values(array_filter(
            $this->rows,
            fn ($r) => ($r['_uid'] ?? null) !== $uid
        ));
    }

    /**
     * Dipakai oleh dropdown Alpine. Wajib berbasis _uid karena x-data
     * hanya dievaluasi sekali, sehingga index yang di-hardcode bisa basi
     * setelah ada baris yang dihapus.
     */
    public function setField(string $uid, string $field, $value): void
    {
        if (!in_array($field, self::FIELD_KUNCI, true)) return;

        $index = null;
        foreach ($this->rows as $i => $r) {
            if (($r['_uid'] ?? null) === $uid) { $index = $i; break; }
        }
        if ($index === null) return;

        $this->rows[$index][$field] = $this->kosongJadiNull($value);
        $this->refreshStokSistem($index);
    }

    /** Fallback bila ada field kunci yang masih memakai wire:model */
    public function updatedRows(mixed $value, string $path): void
    {
        $parts = explode('.', $path);
        if (count($parts) < 2) return;

        $index = (int) $parts[0];
        $field = $parts[1];

        if (!in_array($field, self::FIELD_KUNCI, true)) return;

        $this->rows[$index][$field] = $this->kosongJadiNull($value);
        $this->refreshStokSistem($index);
    }

    private function kosongJadiNull($value)
    {
        return ($value === '' || $value === 'null') ? null : $value;
    }

    private function refreshStokSistem(int $index): void
    {
        $row = $this->rows[$index] ?? null;
        if (!$row) return;

        $idUkuran      = $row['id_ukuran'] ?? null;
        $idJenisKayu   = $row['id_jenis_kayu'] ?? null;
        $idJenisBarang = $row['id_jenis_barang'] ?? null;
        $kw            = $this->kosongJadiNull($row['kw'] ?? null);
        $jenisStok     = $this->jenisStok;

        $idEntitas = $jenisStok === 'platform_jadi' ? $idJenisBarang : $idJenisKayu;
        $idEntitas = $idEntitas !== null && $idEntitas !== '' ? (int) $idEntitas : null;

        // Hanya ukuran yang wajib: jenis kayu / grade boleh kosong,
        // pencarian akan memakai whereNull.
        if (!$idUkuran) {
            $this->rows[$index]['stok_sistem']     = 0;
            $this->rows[$index]['kubikasi_sistem'] = 0;
            return;
        }

        $ukuran = Ukuran::find($idUkuran);
        if (!$ukuran) {
            $this->rows[$index]['stok_sistem']     = 0;
            $this->rows[$index]['kubikasi_sistem'] = 0;
            return;
        }

        $this->rows[$index]['panjang'] = (float) $ukuran->panjang;
        $this->rows[$index]['lebar']   = (float) $ukuran->lebar;
        $this->rows[$index]['tebal']   = (float) $ukuran->tebal;

        [$stok, $kubikasi] = match ($jenisStok) {
            'veneer_basah'  => $this->bacaBasah($idEntitas, $ukuran, $kw),
            'veneer_jadi'   => $this->bacaJadi($idEntitas, $ukuran, $kw),
            'veneer_kering' => $this->bacaKering($idEntitas, (int) $idUkuran, $kw),
            'platform_mth'  => $this->bacaPlatformMth($idEntitas, $ukuran, $kw),
            'triplek_mth'   => $this->bacaTriplekMth($idEntitas, $ukuran, $kw),
            'plywood'       => $this->bacaPlywood($idEntitas, $ukuran, $kw),
            'platform_jadi' => $this->bacaPlatformJadi($idEntitas, $ukuran, $kw),
            'triplek_jadi'  => $this->bacaTriplekJadi($idEntitas, $ukuran, $kw),
            'gudang_satu'   => $this->bacaGudangSatu($idEntitas, $ukuran, $kw),
            default         => [0, 0],
        };

        $this->rows[$index]['stok_sistem']     = $stok;
        $this->rows[$index]['kubikasi_sistem'] = round($kubikasi, 6);
    }

    // ────────────────────────────────────────────────────────────
    // QUERY HELPER: NULL-AWARE
    // "kolom = NULL" di SQL tidak pernah match, harus whereNull.
    // Untuk kolom teks (mis. kw / kw_grade) juga ditoleransi string kosong.
    // ────────────────────────────────────────────────────────────
    private function queryKunci(string $model, array $kunci)
    {
        $q = $model::query();

        foreach ($kunci as $kolom => $nilai) {
            if ($nilai === null || $nilai === '') {
                if (str_starts_with($kolom, 'id_')) {
                    $q->whereNull($kolom);
                } else {
                    $q->where(fn ($sub) => $sub->whereNull($kolom)->orWhere($kolom, ''));
                }
            } else {
                $q->where($kolom, $nilai);
            }
        }

        return $q;
    }

    /**
     * Cari summary dengan kunci yang boleh mengandung null.
     * Baris baru hanya dibuat kalau memang ada stok fisik yang mau dicatat,
     * supaya proses "penolan" baris terhapus tidak malah membuat data sampah.
     */
    private function cariSummary(string $model, array $kunci, array $default, bool $bolehBuat): ?object
    {
        $summary = $this->queryKunci($model, $kunci)->lockForUpdate()->first();

        if (!$summary && $bolehBuat) {
            $summary = $model::create(array_merge($kunci, $default));
        }

        return $summary;
    }

    private function bolehBuatBaru(array $row): bool
    {
        return (int) ($row['stok_fisik'] ?? 0) > 0
            || (float) ($row['kubikasi_fisik'] ?? 0) > 0;
    }

    private function defaultSummary(bool $pakaiHpp = true): array
    {
        $base = ['stok_lembar' => 0, 'stok_kubikasi' => 0];
        return $pakaiHpp ? array_merge($base, ['nilai_stok' => 0, 'hpp_average' => 0]) : $base;
    }

    // ────────────────────────────────────────────────────────────
    // SUBMIT
    // ────────────────────────────────────────────────────────────
    public function submit(): void
    {
        if (!$this->jenisStok) {
            Notification::make()->title('Pilih jenis stok terlebih dahulu')->warning()->send();
            return;
        }

        // Deteksi baris terhapus berbasis _uid. Cara lama (key komposit
        // kayu+dimensi+grade) selalu null untuk baris yang kayu / ukuran /
        // grade-nya kosong, sehingga baris tsb tidak pernah terdeteksi
        // dan muncul notif "Tidak ada perubahan".
        $uidSekarang = collect($this->rows)->pluck('_uid')->filter()->all();

        $deletedRows = collect($this->originalRows)
            ->reject(fn ($r) => in_array($r['_uid'] ?? null, $uidSekarang, true))
            ->values()
            ->all();

        $rowsDiisi = array_values(array_filter($this->rows, function ($r) {
            $adaLembar   = isset($r['stok_fisik'])     && $r['stok_fisik'] !== null     && $r['stok_fisik'] !== '';
            $adaKubikasi = isset($r['kubikasi_fisik']) && $r['kubikasi_fisik'] !== null && $r['kubikasi_fisik'] !== '';
            return $adaLembar || $adaKubikasi;
        }));

        if (empty($rowsDiisi) && empty($deletedRows)) {
            Notification::make()->title('Tidak ada perubahan')->warning()->send();
            return;
        }

        $berhasil = 0;
        $dilewati = 0;

        foreach ($rowsDiisi as $row) {
            $this->prosesRow($row) ? $berhasil++ : $dilewati++;
        }

        foreach ($deletedRows as $row) {
            if (!$this->bisaDinolkan($row)) { $dilewati++; continue; }

            $rowZero = array_merge($row, [
                'stok_fisik'     => 0,
                'kubikasi_fisik' => 0,
                'catatan'        => 'DIHAPUS DARI OPNAME - STOK DINOLKAN',
            ]);

            $this->prosesRow($rowZero) ? $berhasil++ : $dilewati++;
        }

        $this->rows         = $this->loadRows($this->jenisStok);
        $this->originalRows = $this->rows;

        Notification::make()
            ->title('Opname Selesai')
            ->body("{$berhasil} barang berhasil disesuaikan" . ($dilewati > 0 ? ", {$dilewati} dilewati (tidak ada perubahan)." : "."))
            ->success()
            ->send();
    }

    /**
     * Veneer kering butuh id_ukuran + id_jenis_kayu + kw karena berbentuk
     * buku besar (ledger) ber-FK. Jenis stok lain cukup punya dimensi.
     */
    private function bisaDinolkan(array $row): bool
    {
        if ($this->jenisStok === 'veneer_kering') {
            return !empty($row['id_ukuran'])
                && !empty($row['id_jenis_kayu'])
                && !empty($row['kw']);
        }

        return ($row['panjang'] ?? null) !== null
            && ($row['lebar'] ?? null) !== null
            && ($row['tebal'] ?? null) !== null;
    }

    private function prosesRow(array $row): bool
    {
        // Normalisasi: string kosong dari input dropdown → null,
        // supaya queryKunci bisa memilih whereNull dengan benar.
        foreach (self::FIELD_KUNCI as $f) {
            $row[$f] = $this->kosongJadiNull($row[$f] ?? null);
        }

        return match ($this->jenisStok) {
            'veneer_basah'  => $this->opnameVeneerBasah($row),
            'veneer_jadi'   => $this->opnameVeneerJadi($row),
            'veneer_kering' => $this->opnameVeneerKering($row),
            'platform_mth'  => $this->opnamePlatformMth($row),
            'triplek_mth'   => $this->opnameTriplekMth($row),
            'plywood'       => $this->opnamePlywood($row),
            'platform_jadi' => $this->opnamePlatformJadi($row),
            'triplek_jadi'  => $this->opnameTriplekJadi($row),
            'gudang_satu'   => $this->opnameGudangSatu($row),
            default         => false,
        };
    }

    // ────────────────────────────────────────────────────────────
    // LOAD ROWS
    // ────────────────────────────────────────────────────────────
    private function loadRows(string $jenisStok): array
    {
        $rows = match ($jenisStok) {
            'veneer_basah'  => $this->loadBasah(),
            'veneer_jadi'   => $this->loadJadi(),
            'veneer_kering' => $this->loadKering(),
            'platform_mth'  => $this->loadPlatformMth(),
            'triplek_mth'   => $this->loadTriplekMth(),
            'plywood'       => $this->loadPlywood(),
            'platform_jadi' => $this->loadPlatformJadi(),
            'triplek_jadi'  => $this->loadTriplekJadi(),
            'gudang_satu'   => $this->loadGudangSatu(),
            default         => [],
        };

        // Baris dengan stok sistem 0 tidak perlu tampil di daftar opname
        $rows = array_values(array_filter($rows, function ($row) {
            $stokNol = (int) ($row['stok_sistem'] ?? 0) === 0
                && round((float) ($row['kubikasi_sistem'] ?? 0), 6) === 0.0;
            return !$stokNol;
        }));

        return $this->sortirRows($rows);
    }

    // Sortir: Sengon → Meranti → sisanya A-Z, lalu tebal, panjang, lebar, grade
    private function sortirRows(array $rows): array
    {
        $ukuranMap     = Ukuran::all()->keyBy('id');
        $prioritasKayu = ['sengon' => 0, 'meranti' => 1];

        $bobotKayu = function (array $row) use ($prioritasKayu): array {
            $nama = $this->jenisStok === 'platform_jadi'
                ? ($this->jenisBarangOptions[$row['id_jenis_barang']] ?? 'zzz')
                : ($this->jenisKayuOptions[$row['id_jenis_kayu']] ?? 'zzz');

            $prio = $prioritasKayu[strtolower(trim($nama))] ?? 99;
            return [$prio, strtolower($nama)];
        };

        usort($rows, function ($a, $b) use ($ukuranMap, $bobotKayu) {
            [$prioA, $namaA] = $bobotKayu($a);
            [$prioB, $namaB] = $bobotKayu($b);
            if ($prioA !== $prioB) return $prioA <=> $prioB;
            $cmp = strcmp($namaA, $namaB);
            if ($cmp !== 0) return $cmp;

            $ua = $ukuranMap->get($a['id_ukuran']);
            $ub = $ukuranMap->get($b['id_ukuran']);

            $tebalA = $ua ? (float) $ua->tebal : PHP_FLOAT_MAX;
            $tebalB = $ub ? (float) $ub->tebal : PHP_FLOAT_MAX;
            if ($tebalA !== $tebalB) return $tebalA <=> $tebalB;

            $pA = $ua ? (float) $ua->panjang : 0; $pB = $ub ? (float) $ub->panjang : 0;
            if ($pA !== $pB) return $pA <=> $pB;
            $lA = $ua ? (float) $ua->lebar : 0;   $lB = $ub ? (float) $ub->lebar : 0;
            if ($lA !== $lB) return $lA <=> $lB;

            return strnatcasecmp((string) ($a['kw'] ?? ''), (string) ($b['kw'] ?? ''));
        });

        return array_values($rows);
    }

    private function barisKosong(): array
    {
        return [
            '_uid'            => (string) Str::uuid(),
            'id_jenis_kayu'   => null,
            'id_jenis_barang' => null,
            'id_ukuran'       => null,
            'panjang'         => null,
            'lebar'           => null,
            'tebal'           => null,
            'kw'              => null,
            'stok_sistem'     => 0,
            'kubikasi_sistem' => 0,
            'stok_fisik'      => null,
            'kubikasi_fisik'  => null,
            'catatan'         => null,
        ];
    }

    private function rowDariSummary(object $s, string $idField = 'id_jenis_kayu'): array
    {
        $ukuran = Ukuran::where(['panjang' => $s->panjang, 'lebar' => $s->lebar, 'tebal' => $s->tebal])->first();

        return [
            '_uid'            => (string) Str::uuid(),
            'id_jenis_kayu'   => $idField === 'id_jenis_kayu' ? $s->id_jenis_kayu : null,
            'id_jenis_barang' => $idField === 'id_jenis_barang' ? $s->id_jenis_barang : null,
            'id_ukuran'       => $ukuran?->id,
            'panjang'         => (float) $s->panjang,
            'lebar'           => (float) $s->lebar,
            'tebal'           => (float) $s->tebal,
            'kw'              => $this->kosongJadiNull($s->kw_grade ?? $s->kw ?? null),
            'stok_sistem'     => (int) $s->stok_lembar,
            'kubikasi_sistem' => round((float) $s->stok_kubikasi, 6),
            'stok_fisik'      => null,
            'kubikasi_fisik'  => null,
            'catatan'         => null,
        ];
    }

    private function loadBasah(): array
    {
        return HppVeneerBasahSummary::all()->map(function ($s) {
            $ukuran = Ukuran::where(['panjang' => $s->panjang, 'lebar' => $s->lebar, 'tebal' => $s->tebal])->first();
            return [
                '_uid'            => (string) Str::uuid(),
                'id_jenis_kayu'   => $s->id_jenis_kayu,
                'id_jenis_barang' => null,
                'id_ukuran'       => $ukuran?->id,
                'panjang'         => (float) $s->panjang,
                'lebar'           => (float) $s->lebar,
                'tebal'           => (float) $s->tebal,
                'kw'              => $this->kosongJadiNull($s->kw),
                'stok_sistem'     => (int) $s->stok_lembar,
                'kubikasi_sistem' => round((float) $s->stok_kubikasi, 6),
                'stok_fisik'      => null,
                'kubikasi_fisik'  => null,
                'catatan'         => null,
            ];
        })->toArray();
    }

    private function loadJadi(): array         { return StokVeneerJadi::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }
    private function loadPlatformMth(): array  { return StokPlatformMth::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }
    private function loadTriplekMth(): array   { return StokTriplekMth::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }
    private function loadPlywood(): array      { return StokPlywoodSiapJual::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }
    private function loadPlatformJadi(): array { return StokPlatformJadi::all()->map(fn($s) => $this->rowDariSummary($s, 'id_jenis_barang'))->toArray(); }
    private function loadTriplekJadi(): array  { return StokTriplekJadi::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }
    private function loadGudangSatu(): array   { return StokGudangSatu::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }

    private function loadKering(): array
    {
        return StokVeneerKering::selectRaw('id_ukuran, id_jenis_kayu, kw')
            ->groupBy('id_ukuran', 'id_jenis_kayu', 'kw')
            ->get()
            ->map(function ($s) {
                $stok     = StokVeneerKering::saldoLembarTerakhir($s->id_ukuran, $s->id_jenis_kayu, $s->kw);
                $snapshot = StokVeneerKering::snapshotTerakhir($s->id_ukuran, $s->id_jenis_kayu, $s->kw);
                $ukuran   = Ukuran::find($s->id_ukuran);
                return [
                    '_uid'            => (string) Str::uuid(),
                    'id_jenis_kayu'   => $s->id_jenis_kayu,
                    'id_jenis_barang' => null,
                    'id_ukuran'       => $s->id_ukuran,
                    'panjang'         => $ukuran ? (float) $ukuran->panjang : null,
                    'lebar'           => $ukuran ? (float) $ukuran->lebar : null,
                    'tebal'           => $ukuran ? (float) $ukuran->tebal : null,
                    'kw'              => $this->kosongJadiNull($s->kw),
                    'stok_sistem'     => $stok,
                    'kubikasi_sistem' => round((float) $snapshot['stok_m3'], 6),
                    'stok_fisik'      => null,
                    'kubikasi_fisik'  => null,
                    'catatan'         => null,
                ];
            })->toArray();
    }

    // ────────────────────────────────────────────────────────────
    // BACA STOK SISTEM (null-aware)
    // ────────────────────────────────────────────────────────────
    private function hasilBaca(?object $s): array
    {
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private function bacaBasah(?int $id, Ukuran $u, ?string $kw): array
    {
        return $this->hasilBaca($this->queryKunci(HppVeneerBasahSummary::class, [
            'id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw' => $kw,
        ])->first());
    }

    private function bacaJadi(?int $id, Ukuran $u, ?string $kw): array
    {
        return $this->hasilBaca($this->queryKunci(StokVeneerJadi::class, [
            'id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw,
        ])->first());
    }

    private function bacaKering(?int $id, int $idUkuran, ?string $kw): array
    {
        if (!$id || !$kw) return [0, 0.0];

        $stok     = StokVeneerKering::saldoLembarTerakhir($idUkuran, $id, $kw);
        $snapshot = StokVeneerKering::snapshotTerakhir($idUkuran, $id, $kw);
        return [$stok, (float) $snapshot['stok_m3']];
    }

    private function bacaPlatformMth(?int $id, Ukuran $u, ?string $kw): array
    {
        return $this->hasilBaca($this->queryKunci(StokPlatformMth::class, [
            'id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw,
        ])->first());
    }

    private function bacaTriplekMth(?int $id, Ukuran $u, ?string $kw): array
    {
        return $this->hasilBaca($this->queryKunci(StokTriplekMth::class, [
            'id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw,
        ])->first());
    }

    private function bacaPlywood(?int $id, Ukuran $u, ?string $kw): array
    {
        return $this->hasilBaca($this->queryKunci(StokPlywoodSiapJual::class, [
            'id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw,
        ])->first());
    }

    private function bacaPlatformJadi(?int $id, Ukuran $u, ?string $kw): array
    {
        return $this->hasilBaca($this->queryKunci(StokPlatformJadi::class, [
            'id_jenis_barang' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw,
        ])->first());
    }

    private function bacaTriplekJadi(?int $id, Ukuran $u, ?string $kw): array
    {
        return $this->hasilBaca($this->queryKunci(StokTriplekJadi::class, [
            'id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw,
        ])->first());
    }

    private function bacaGudangSatu(?int $id, Ukuran $u, ?string $kw): array
    {
        return $this->hasilBaca($this->queryKunci(StokGudangSatu::class, [
            'id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw,
        ])->first());
    }

    // ────────────────────────────────────────────────────────────
    // HELPER KETERANGAN
    // ────────────────────────────────────────────────────────────
    private function buatKeterangan(string $label, array $row): string
    {
        $tgl      = now()->format('d/m/Y');
        $namaUser = auth()->user()?->name ?? 'SISTEM';
        $ket      = "{$label} TANGGAL {$tgl} OLEH {$namaUser}";
        if (!empty($row['catatan'])) {
            $ket .= ". CATATAN: " . strtoupper($row['catatan']);
        }
        return $ket;
    }

    private function dimensi(array $row): array
    {
        return [
            (float) $row['panjang'],
            (float) $row['lebar'],
            (float) $row['tebal'],
        ];
    }

    // ────────────────────────────────────────────────────────────
    // HELPER OPNAME DENGAN SUMMARY (pakai hpp_average & nilai_stok)
    // ────────────────────────────────────────────────────────────
    private function opnameDenganSummary(
        array $row,
        object $summary,
        string $label,
        string $logClass,
        string $idField = 'id_jenis_kayu'
    ): bool {
        $stokSistem      = (int) $summary->stok_lembar;
        $stokFisik       = (int) ($row['stok_fisik'] ?? 0);
        $kubikasiFisik   = (float) ($row['kubikasi_fisik'] ?? 0);
        $kubikasiSistem  = (float) $summary->stok_kubikasi;
        $selisihLembar   = $stokFisik - $stokSistem;
        $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

        if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

        $tipe = $selisihLembar !== 0
            ? ($selisihLembar > 0 ? 'masuk' : 'keluar')
            : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');
        $ket = $this->buatKeterangan($label, $row);

        $kubikasiSelisih = round(abs($selisihKubikasi), 6);
        $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
        $nilaiStokBefore = $summary->nilai_stok;

        $summary->update([
            'stok_lembar'   => $stokFisik,
            'stok_kubikasi' => $kubikasiFisik,
            'nilai_stok'    => $nilaiStokBaru,
        ]);

        $log = $logClass::create([
            $idField               => $summary->{$idField},
            'panjang'              => $summary->panjang,
            'lebar'                => $summary->lebar,
            'tebal'                => $summary->tebal,
            'kw_grade'             => $summary->kw_grade,
            'tanggal'              => now(),
            'tipe_transaksi'       => $tipe,
            'keterangan'           => $ket,
            'total_lembar'         => abs($selisihLembar),
            'total_kubikasi'       => $kubikasiSelisih,
            'stok_lembar_before'   => $stokSistem,
            'stok_lembar_after'    => $stokFisik,
            'stok_kubikasi_before' => $kubikasiSistem,
            'stok_kubikasi_after'  => $kubikasiFisik,
            'hpp_average'          => $summary->hpp_average,
            'nilai_stok'           => $nilaiStokBaru,
            'nilai_stok_before'    => $nilaiStokBefore,
            'nilai_stok_after'     => $nilaiStokBaru,
        ]);

        $summary->update(['id_last_log' => $log->id]);
        return true;
    }

    // ────────────────────────────────────────────────────────────
    // OPNAME METHODS
    // ────────────────────────────────────────────────────────────
    private function opnameVeneerBasah(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            [$panjang, $lebar, $tebal] = $this->dimensi($row);

            $kunci = [
                'id_jenis_kayu' => $row['id_jenis_kayu'],
                'panjang'       => $panjang,
                'lebar'         => $lebar,
                'tebal'         => $tebal,
                'kw'            => $row['kw'],
            ];

            $summary = $this->cariSummary(
                HppVeneerBasahSummary::class,
                $kunci,
                $this->defaultSummary(),
                $this->bolehBuatBaru($row)
            );

            if (!$summary) return false;

            $stokSistem      = (int) $summary->stok_lembar;
            $stokFisik       = (int) ($row['stok_fisik'] ?? 0);
            $kubikasiFisik   = (float) ($row['kubikasi_fisik'] ?? 0);
            $kubikasiSistem  = (float) $summary->stok_kubikasi;
            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

            $tipe = $selisihLembar !== 0
                ? ($selisihLembar > 0 ? 'masuk' : 'keluar')
                : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');

            $kubikasiSelisih = round(abs($selisihKubikasi), 6);
            $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
            $nilaiStokBefore = $summary->nilai_stok;

            $summary->update([
                'stok_lembar'   => $stokFisik,
                'stok_kubikasi' => $kubikasiFisik,
                'nilai_stok'    => $nilaiStokBaru,
            ]);

            HppVeneerBasahLog::create([
                'id_jenis_kayu'        => $summary->id_jenis_kayu,
                'panjang'              => $summary->panjang,
                'lebar'                => $summary->lebar,
                'tebal'                => $summary->tebal,
                'kw'                   => $summary->kw,
                'tanggal'              => now(),
                'tipe_transaksi'       => $tipe,
                'keterangan'           => $this->buatKeterangan('OPNAME VENEER BASAH', $row),
                'total_lembar'         => abs($selisihLembar),
                'total_kubikasi'       => $kubikasiSelisih,
                'stok_lembar_before'   => $stokSistem,
                'stok_lembar_after'    => $stokFisik,
                'stok_kubikasi_before' => $kubikasiSistem,
                'stok_kubikasi_after'  => $kubikasiFisik,
                'hpp_average'          => $summary->hpp_average,
                'nilai_stok_before'    => $nilaiStokBefore,
                'nilai_stok_after'     => $nilaiStokBaru,
            ]);

            return true;
        });
    }

    private function opnameVeneerJadi(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            [$panjang, $lebar, $tebal] = $this->dimensi($row);

            $summary = $this->cariSummary(
                StokVeneerJadi::class,
                [
                    'id_jenis_kayu' => $row['id_jenis_kayu'],
                    'panjang'       => $panjang,
                    'lebar'         => $lebar,
                    'tebal'         => $tebal,
                    'kw_grade'      => $row['kw'],
                ],
                $this->defaultSummary(),
                $this->bolehBuatBaru($row)
            );

            if (!$summary) return false;

            return $this->opnameDenganSummary($row, $summary, 'OPNAME VENEER JADI', HppVeneerJadiLog::class);
        });
    }

    private function opnameVeneerKering(array $row): bool
    {
        // Ledger ber-FK: butuh id_ukuran, id_jenis_kayu, dan kw
        if (empty($row['id_ukuran']) || empty($row['id_jenis_kayu']) || empty($row['kw'])) return false;

        return DB::transaction(function () use ($row) {
            $idUkuran    = (int) $row['id_ukuran'];
            $idJenisKayu = (int) $row['id_jenis_kayu'];
            $kw          = (string) $row['kw'];

            $stokSistem      = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);
            $snapshot        = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);
            $stokFisik       = (int) ($row['stok_fisik'] ?? 0);
            $kubikasiFisik   = (float) ($row['kubikasi_fisik'] ?? 0);
            $kubikasiSistem  = (float) $snapshot['stok_m3'];
            $hppAverage      = (float) $snapshot['hpp_average'];
            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

            $tipe = $selisihLembar !== 0
                ? ($selisihLembar > 0 ? 'masuk' : 'keluar')
                : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');

            StokVeneerKering::create([
                'id_ukuran'           => $idUkuran,
                'id_jenis_kayu'       => $idJenisKayu,
                'kw'                  => $kw,
                'jenis_transaksi'     => $tipe,
                'tanggal_transaksi'   => now(),
                'qty'                 => abs($selisihLembar),
                'm3'                  => round(abs($selisihKubikasi), 6),
                'stok_lembar_sebelum' => $stokSistem,
                'stok_lembar_sesudah' => $stokFisik,
                'hpp_kering_per_m3'   => $hppAverage,
                'nilai_transaksi'     => round(abs(($kubikasiFisik * $hppAverage) - ($kubikasiSistem * $hppAverage)), 2),
                'stok_m3_sebelum'     => $kubikasiSistem,
                'nilai_stok_sebelum'  => round($kubikasiSistem * $hppAverage, 2),
                'stok_m3_sesudah'     => $kubikasiFisik,
                'nilai_stok_sesudah'  => round($kubikasiFisik * $hppAverage, 2),
                'hpp_average'         => $hppAverage,
                'keterangan'          => $this->buatKeterangan('OPNAME VENEER KERING', $row),
            ]);

            return true;
        });
    }

    private function opnamePlatformMth(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            [$panjang, $lebar, $tebal] = $this->dimensi($row);

            $summary = $this->cariSummary(
                StokPlatformMth::class,
                [
                    'id_jenis_kayu' => $row['id_jenis_kayu'],
                    'panjang'       => $panjang,
                    'lebar'         => $lebar,
                    'tebal'         => $tebal,
                    'kw_grade'      => $row['kw'],
                ],
                $this->defaultSummary(),
                $this->bolehBuatBaru($row)
            );

            if (!$summary) return false;

            return $this->opnameDenganSummary($row, $summary, 'OPNAME PLATFORM MTH', HppPlatformMthLog::class);
        });
    }

    private function opnameTriplekMth(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            [$panjang, $lebar, $tebal] = $this->dimensi($row);

            $summary = $this->cariSummary(
                StokTriplekMth::class,
                [
                    'id_jenis_kayu' => $row['id_jenis_kayu'],
                    'panjang'       => $panjang,
                    'lebar'         => $lebar,
                    'tebal'         => $tebal,
                    'kw_grade'      => $row['kw'],
                ],
                $this->defaultSummary(),
                $this->bolehBuatBaru($row)
            );

            if (!$summary) return false;

            return $this->opnameDenganSummary($row, $summary, 'OPNAME TRIPLEK MTH', HppTriplekMthLog::class);
        });
    }

    private function opnamePlywood(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            [$panjang, $lebar, $tebal] = $this->dimensi($row);

            $summary = $this->cariSummary(
                StokPlywoodSiapJual::class,
                [
                    'id_jenis_kayu' => $row['id_jenis_kayu'],
                    'panjang'       => $panjang,
                    'lebar'         => $lebar,
                    'tebal'         => $tebal,
                    'kw_grade'      => $row['kw'],
                ],
                $this->defaultSummary(false),
                $this->bolehBuatBaru($row)
            );

            if (!$summary) return false;

            $stokSistem      = (int) $summary->stok_lembar;
            $stokFisik       = (int) ($row['stok_fisik'] ?? 0);
            $kubikasiFisik   = (float) ($row['kubikasi_fisik'] ?? 0);
            $kubikasiSistem  = (float) $summary->stok_kubikasi;
            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

            $tipe = $selisihLembar !== 0
                ? ($selisihLembar > 0 ? 'masuk' : 'keluar')
                : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');

            $summary->update(['stok_lembar' => $stokFisik, 'stok_kubikasi' => $kubikasiFisik]);

            $log = HppPlywoodSiapJualLog::create([
                'id_jenis_kayu'        => $summary->id_jenis_kayu,
                'panjang'              => $summary->panjang,
                'lebar'                => $summary->lebar,
                'tebal'                => $summary->tebal,
                'kw_grade'             => $summary->kw_grade,
                'tanggal'              => now(),
                'tipe_transaksi'       => $tipe,
                'keterangan'           => $this->buatKeterangan('OPNAME PLYWOOD SIAP JUAL', $row),
                'total_lembar'         => abs($selisihLembar),
                'total_kubikasi'       => round(abs($selisihKubikasi), 6),
                'stok_lembar_before'   => $stokSistem,
                'stok_lembar_after'    => $stokFisik,
                'stok_kubikasi_before' => $kubikasiSistem,
                'stok_kubikasi_after'  => $kubikasiFisik,
            ]);

            $summary->update(['id_last_log' => $log->id]);
            return true;
        });
    }

    private function opnamePlatformJadi(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            [$panjang, $lebar, $tebal] = $this->dimensi($row);

            $summary = $this->cariSummary(
                StokPlatformJadi::class,
                [
                    'id_jenis_barang' => $row['id_jenis_barang'],
                    'panjang'         => $panjang,
                    'lebar'           => $lebar,
                    'tebal'           => $tebal,
                    'kw_grade'        => $row['kw'],
                ],
                $this->defaultSummary(),
                $this->bolehBuatBaru($row)
            );

            if (!$summary) return false;

            return $this->opnameDenganSummary($row, $summary, 'OPNAME PLATFORM JADI', HppPlatformJadiLog::class, 'id_jenis_barang');
        });
    }

    private function opnameTriplekJadi(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            [$panjang, $lebar, $tebal] = $this->dimensi($row);

            $summary = $this->cariSummary(
                StokTriplekJadi::class,
                [
                    'id_jenis_kayu' => $row['id_jenis_kayu'],
                    'panjang'       => $panjang,
                    'lebar'         => $lebar,
                    'tebal'         => $tebal,
                    'kw_grade'      => $row['kw'],
                ],
                $this->defaultSummary(),
                $this->bolehBuatBaru($row)
            );

            if (!$summary) return false;

            return $this->opnameDenganSummary($row, $summary, 'OPNAME TRIPLEK JADI', HppTriplekJadiLog::class);
        });
    }

    private function opnameGudangSatu(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            [$panjang, $lebar, $tebal] = $this->dimensi($row);

            $summary = $this->cariSummary(
                StokGudangSatu::class,
                [
                    'id_jenis_kayu' => $row['id_jenis_kayu'],
                    'panjang'       => $panjang,
                    'lebar'         => $lebar,
                    'tebal'         => $tebal,
                    'kw_grade'      => $row['kw'],
                ],
                $this->defaultSummary(),
                $this->bolehBuatBaru($row)
            );

            if (!$summary) return false;

            return $this->opnameDenganSummary($row, $summary, 'OPNAME GUDANG SATU', GudangSatuLog::class);
        });
    }

    public function render()
    {
        return view('livewire.opname-stok-table');
    }
}