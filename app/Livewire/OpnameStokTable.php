<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
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
    public string $jenisStok = '';
    public bool $headerCollapsed = false;
    public array $rows = [];
    public array $jenisKayuOptions = [];
    public array $jenisBarangOptions = [];
    public array $ukuranOptions = [];
    public array $gradeOptions = [];

    public const JENIS_STOK_LABELS = [
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

    public function mount(): void
    {
        $this->jenisKayuOptions   = JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id')->toArray();
        $this->jenisBarangOptions = JenisBarang::orderBy('nama_jenis_barang')->pluck('nama_jenis_barang', 'id')->toArray();
        $this->ukuranOptions      = Ukuran::all()->pluck('dimensi', 'id')->toArray();
        $this->gradeOptions       = Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade')->toArray();
    }

    public function updatedJenisStok(): void
    {
        $this->rows = $this->loadRows($this->jenisStok);
        $this->headerCollapsed = true; // auto-collapse setelah pilih jenis stok
    }

    public function toggleHeader(): void
    {
        $this->headerCollapsed = !$this->headerCollapsed;
    }

    public function tambahBaris(): void
    {
        $this->rows[] = $this->barisKosong();
    }

    public function hapusBaris(int $index): void
    {
        array_splice($this->rows, $index, 1);
        $this->rows = array_values($this->rows);
    }

    public function updatedRows(mixed $value, string $path): void
    {
        // path contoh: "0.id_jenis_kayu" atau "0.id_ukuran"
        $parts = explode('.', $path);
        if (count($parts) < 2) return;

        $index = (int) $parts[0];
        $field = $parts[1];

        $triggerFields = ['id_jenis_kayu', 'id_jenis_barang', 'id_ukuran', 'kw'];
        if (!in_array($field, $triggerFields)) return;

        $this->refreshStokSistem($index);

        // Peringatan dini jika kombinasi baris ini duplikat dengan baris lain
        $dupIndex = $this->cariDuplikat($index);
        if ($dupIndex !== null) {
            Notification::make()
                ->title('Kombinasi sudah ada')
                ->body('Baris ' . ($index + 1) . ' memiliki barang, ukuran, dan grade yang sama dengan baris ' . ($dupIndex + 1) . '.')
                ->warning()
                ->send();
        }
    }

    // ────────────────────────────────────────────────────────────
    // DUPLIKAT HELPERS
    // ────────────────────────────────────────────────────────────
    private function kunciBaris(array $row): ?string
    {
        $idEntitas = $this->jenisStok === 'platform_jadi'
            ? ($row['id_jenis_barang'] ?? null)
            : ($row['id_jenis_kayu'] ?? null);

        $idUkuran = $row['id_ukuran'] ?? null;
        $kw       = $row['kw'] ?? null;

        if (!$idEntitas || !$idUkuran || !$kw) return null; // belum lengkap, tidak dihitung

        return $idEntitas . '|' . $idUkuran . '|' . $kw;
    }

    /** Cari index baris lain yang kombinasi-nya sama dengan baris $index. */
    private function cariDuplikat(int $index): ?int
    {
        $key = $this->kunciBaris($this->rows[$index] ?? []);
        if ($key === null) return null;

        foreach ($this->rows as $i => $row) {
            if ($i === $index) continue;
            if ($this->kunciBaris($row) === $key) return $i;
        }
        return null;
    }

    private function refreshStokSistem(int $index): void
    {
        $row = $this->rows[$index] ?? null;
        if (!$row) return;

        $idUkuran      = $row['id_ukuran'] ?? null;
        $idJenisKayu   = $row['id_jenis_kayu'] ?? null;
        $idJenisBarang = $row['id_jenis_barang'] ?? null;
        $kw            = $row['kw'] ?? null;
        $jenisStok     = $this->jenisStok;

        $idEntitas = $jenisStok === 'platform_jadi' ? $idJenisBarang : $idJenisKayu;

        if (!$idUkuran || !$idEntitas || !$kw) {
            $this->rows[$index]['stok_sistem']     = 0;
            $this->rows[$index]['kubikasi_sistem'] = 0;
            return;
        }

        $ukuran = Ukuran::find($idUkuran);
        if (!$ukuran) return;

        [$stok, $kubikasi] = match ($jenisStok) {
            'veneer_basah'  => $this->bacaBasah((int) $idEntitas, $ukuran, (string) $kw),
            'veneer_jadi'   => $this->bacaJadi((int) $idEntitas, $ukuran, (string) $kw),
            'veneer_kering' => $this->bacaKering((int) $idEntitas, (int) $idUkuran, (string) $kw),
            'platform_mth'  => $this->bacaPlatformMth((int) $idEntitas, $ukuran, (string) $kw),
            'triplek_mth'   => $this->bacaTriplekMth((int) $idEntitas, $ukuran, (string) $kw),
            'plywood'       => $this->bacaPlywood((int) $idEntitas, $ukuran, (string) $kw),
            'platform_jadi' => $this->bacaPlatformJadi((int) $idEntitas, $ukuran, (string) $kw),
            'triplek_jadi'  => $this->bacaTriplekJadi((int) $idEntitas, $ukuran, (string) $kw),
            'gudang_satu'   => $this->bacaGudangSatu((int) $idEntitas, $ukuran, (string) $kw),
            default         => [0, 0],
        };

        $this->rows[$index]['stok_sistem']     = $stok;
        $this->rows[$index]['kubikasi_sistem'] = round($kubikasi, 6);
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

        $rowsDiisi = array_filter(
            $this->rows,
            fn($r) => isset($r['stok_fisik']) && $r['stok_fisik'] !== null && $r['stok_fisik'] !== ''
        );

        if (empty($rowsDiisi)) {
            Notification::make()->title('Tidak ada stok fisik yang diisi')->warning()->send();
            return;
        }

        // ── Validasi baris tidak lengkap ──
        foreach ($rowsDiisi as $index => $row) {
            if ($this->kunciBaris($row) === null) {
                Notification::make()
                    ->title('Data belum lengkap')
                    ->body('Baris ' . ($index + 1) . ' sudah diisi stok fisik tetapi barang/ukuran/grade belum lengkap.')
                    ->danger()
                    ->send();
                return;
            }
        }

        // ── Validasi duplikat: tolak submit jika ada kombinasi yang sama ──
        $seen = [];
        foreach ($rowsDiisi as $index => $row) {
            $key = $this->kunciBaris($row);
            if (isset($seen[$key])) {
                Notification::make()
                    ->title('Ada baris duplikat')
                    ->body('Baris ' . ($seen[$key] + 1) . ' dan baris ' . ($index + 1) .
                           ' memiliki kombinasi barang, ukuran, dan grade yang sama. Hapus salah satu sebelum submit.')
                    ->danger()
                    ->send();
                return;
            }
            $seen[$key] = $index;
        }

        $berhasil = 0;
        $dilewati = 0;

        foreach ($rowsDiisi as $row) {
            $result = match ($this->jenisStok) {
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

            $result ? $berhasil++ : $dilewati++;
        }

        // Reload rows dari DB setelah submit
        $this->rows = $this->loadRows($this->jenisStok);

        Notification::make()
            ->title('Opname Selesai')
            ->body("{$berhasil} barang berhasil disesuaikan" . ($dilewati > 0 ? ", {$dilewati} dilewati." : "."))
            ->success()
            ->send();
    }

    // ────────────────────────────────────────────────────────────
    // LOAD ROWS DARI DB
    // ────────────────────────────────────────────────────────────
    private function loadRows(string $jenisStok): array
    {
        return match ($jenisStok) {
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
    }

    private function barisKosong(): array
    {
        return [
            'id_jenis_kayu'   => null,
            'id_jenis_barang' => null,
            'id_ukuran'       => null,
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
            'id_jenis_kayu'   => $idField === 'id_jenis_kayu' ? $s->id_jenis_kayu : null,
            'id_jenis_barang' => $idField === 'id_jenis_barang' ? $s->id_jenis_barang : null,
            'id_ukuran'       => $ukuran?->id,
            'kw'              => $s->kw_grade ?? $s->kw ?? null,
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
                'id_jenis_kayu'   => $s->id_jenis_kayu,
                'id_jenis_barang' => null,
                'id_ukuran'       => $ukuran?->id,
                'kw'              => $s->kw,
                'stok_sistem'     => (int) $s->stok_lembar,
                'kubikasi_sistem' => round((float) $s->stok_kubikasi, 6),
                'stok_fisik'      => null,
                'kubikasi_fisik'  => null,
                'catatan'         => null,
            ];
        })->toArray();
    }

    private function loadJadi(): array
    {
        return StokVeneerJadi::all()->map(fn($s) => $this->rowDariSummary($s))->toArray();
    }

    private function loadKering(): array
    {
        return StokVeneerKering::selectRaw('id_ukuran, id_jenis_kayu, kw')
            ->groupBy('id_ukuran', 'id_jenis_kayu', 'kw')
            ->get()
            ->map(function ($s) {
                $stok     = StokVeneerKering::saldoLembarTerakhir($s->id_ukuran, $s->id_jenis_kayu, $s->kw);
                $snapshot = StokVeneerKering::snapshotTerakhir($s->id_ukuran, $s->id_jenis_kayu, $s->kw);
                return [
                    'id_jenis_kayu'   => $s->id_jenis_kayu,
                    'id_jenis_barang' => null,
                    'id_ukuran'       => $s->id_ukuran,
                    'kw'              => $s->kw,
                    'stok_sistem'     => $stok,
                    'kubikasi_sistem' => round((float) $snapshot['stok_m3'], 6),
                    'stok_fisik'      => null,
                    'kubikasi_fisik'  => null,
                    'catatan'         => null,
                ];
            })->toArray();
    }

    private function loadPlatformMth(): array  { return StokPlatformMth::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }
    private function loadTriplekMth(): array   { return StokTriplekMth::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }
    private function loadPlywood(): array      { return StokPlywoodSiapJual::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }
    private function loadPlatformJadi(): array { return StokPlatformJadi::all()->map(fn($s) => $this->rowDariSummary($s, 'id_jenis_barang'))->toArray(); }
    private function loadTriplekJadi(): array  { return StokTriplekJadi::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }
    private function loadGudangSatu(): array   { return StokGudangSatu::all()->map(fn($s) => $this->rowDariSummary($s))->toArray(); }

    // ────────────────────────────────────────────────────────────
    // BACA STOK SISTEM
    // ────────────────────────────────────────────────────────────
    private function bacaBasah(int $id, Ukuran $u, string $kw): array
    {
        $s = HppVeneerBasahSummary::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private function bacaJadi(int $id, Ukuran $u, string $kw): array
    {
        $s = StokVeneerJadi::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private function bacaKering(int $id, int $idUkuran, string $kw): array
    {
        $stok     = StokVeneerKering::saldoLembarTerakhir($idUkuran, $id, $kw);
        $snapshot = StokVeneerKering::snapshotTerakhir($idUkuran, $id, $kw);
        return [$stok, (float) $snapshot['stok_m3']];
    }

    private function bacaPlatformMth(int $id, Ukuran $u, string $kw): array
    {
        $s = StokPlatformMth::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private function bacaTriplekMth(int $id, Ukuran $u, string $kw): array
    {
        $s = StokTriplekMth::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private function bacaPlywood(int $id, Ukuran $u, string $kw): array
    {
        $s = StokPlywoodSiapJual::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private function bacaPlatformJadi(int $id, Ukuran $u, string $kw): array
    {
        $s = StokPlatformJadi::where(['id_jenis_barang' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private function bacaTriplekJadi(int $id, Ukuran $u, string $kw): array
    {
        $s = StokTriplekJadi::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
    }

    private function bacaGudangSatu(int $id, Ukuran $u, string $kw): array
    {
        $s = StokGudangSatu::where(['id_jenis_kayu' => $id, 'panjang' => $u->panjang, 'lebar' => $u->lebar, 'tebal' => $u->tebal, 'kw_grade' => $kw])->first();
        return [$s ? (int) $s->stok_lembar : 0, $s ? (float) $s->stok_kubikasi : 0.0];
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

    // ────────────────────────────────────────────────────────────
    // HELPER OPNAME DENGAN SUMMARY (pola umum)
    // ────────────────────────────────────────────────────────────
    private function opnameDenganSummary(
        array $row,
        object $summary,
        string $label,
        string $logClass,
        string $idField = 'id_jenis_kayu'
    ): bool {
        $stokSistem      = (int) $summary->stok_lembar;
        $stokFisik       = (int) $row['stok_fisik'];
        $kubikasiFisik   = (float) ($row['kubikasi_fisik'] ?? 0);
        $kubikasiSistem  = (float) $summary->stok_kubikasi;
        $selisihLembar   = $stokFisik - $stokSistem;
        $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

        if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

        $tipe = $selisihLembar !== 0 ? ($selisihLembar > 0 ? 'masuk' : 'keluar') : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');
        $ket  = $this->buatKeterangan($label, $row);

        $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);
        $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
        $nilaiStokBefore = $summary->nilai_stok;

        $summary->update(['stok_lembar' => $stokFisik, 'stok_kubikasi' => $kubikasiFisik, 'nilai_stok' => $nilaiStokBaru]);

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
            $ukuran  = Ukuran::findOrFail($row['id_ukuran']);
            $summary = HppVeneerBasahSummary::where(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw' => $row['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = HppVeneerBasahSummary::create(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw' => $row['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);

            $stokSistem      = (int) $summary->stok_lembar;
            $stokFisik       = (int) $row['stok_fisik'];
            $kubikasiFisik   = (float) ($row['kubikasi_fisik'] ?? 0);
            $kubikasiSistem  = (float) $summary->stok_kubikasi;
            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

            $tipe = $selisihLembar !== 0 ? ($selisihLembar > 0 ? 'masuk' : 'keluar') : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');
            $ket  = $this->buatKeterangan('OPNAME VENEER BASAH', $row);

            $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);
            $nilaiStokBaru   = round($kubikasiFisik * $summary->hpp_average, 2);
            $nilaiStokBefore = $summary->nilai_stok;

            $summary->update(['stok_lembar' => $stokFisik, 'stok_kubikasi' => $kubikasiFisik, 'nilai_stok' => $nilaiStokBaru]);

            HppVeneerBasahLog::create([
                'id_jenis_kayu'        => $summary->id_jenis_kayu,
                'panjang'              => $summary->panjang,
                'lebar'                => $summary->lebar,
                'tebal'                => $summary->tebal,
                'kw'                   => $summary->kw,
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
                'nilai_stok_before'    => $nilaiStokBefore,
                'nilai_stok_after'     => $nilaiStokBaru,
            ]);

            return true;
        });
    }

    private function opnameVeneerJadi(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            $ukuran  = Ukuran::findOrFail($row['id_ukuran']);
            $summary = StokVeneerJadi::where(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokVeneerJadi::create(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);

            return $this->opnameDenganSummary($row, $summary, 'OPNAME VENEER JADI', HppVeneerJadiLog::class);
        });
    }

    private function opnameVeneerKering(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            $idUkuran    = (int) $row['id_ukuran'];
            $idJenisKayu = (int) $row['id_jenis_kayu'];
            $kw          = (string) $row['kw'];

            $stokSistem   = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);
            $snapshot     = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kw);
            $stokFisik    = (int) $row['stok_fisik'];
            $kubikasiFisik   = (float) ($row['kubikasi_fisik'] ?? 0);
            $kubikasiSistem  = (float) $snapshot['stok_m3'];
            $hppAverage      = (float) $snapshot['hpp_average'];
            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

            $tipe = $selisihLembar !== 0 ? ($selisihLembar > 0 ? 'masuk' : 'keluar') : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');

            StokVeneerKering::create([
                'id_ukuran'           => $idUkuran,
                'id_jenis_kayu'       => $idJenisKayu,
                'kw'                  => $kw,
                'jenis_transaksi'     => $tipe,
                'tanggal_transaksi'   => now(),
                'qty'                 => abs($selisihLembar),
                'm3'                  => round(abs($kubikasiFisik - $kubikasiSistem), 6),
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
            $ukuran  = Ukuran::findOrFail($row['id_ukuran']);
            $summary = StokPlatformMth::where(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokPlatformMth::create(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);
            return $this->opnameDenganSummary($row, $summary, 'OPNAME PLATFORM MTH', HppPlatformMthLog::class);
        });
    }

    private function opnameTriplekMth(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            $ukuran  = Ukuran::findOrFail($row['id_ukuran']);
            $summary = StokTriplekMth::where(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokTriplekMth::create(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);
            return $this->opnameDenganSummary($row, $summary, 'OPNAME TRIPLEK MTH', HppTriplekMthLog::class);
        });
    }

    private function opnamePlywood(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            $ukuran  = Ukuran::findOrFail($row['id_ukuran']);
            $summary = StokPlywoodSiapJual::where(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokPlywoodSiapJual::create(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0]);

            $stokSistem      = (int) $summary->stok_lembar;
            $stokFisik       = (int) $row['stok_fisik'];
            $kubikasiFisik   = (float) ($row['kubikasi_fisik'] ?? 0);
            $kubikasiSistem  = (float) $summary->stok_kubikasi;
            $selisihLembar   = $stokFisik - $stokSistem;
            $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

            if ($selisihLembar === 0 && round($selisihKubikasi, 6) === 0.0) return false;

            $tipe = $selisihLembar !== 0 ? ($selisihLembar > 0 ? 'masuk' : 'keluar') : ($selisihKubikasi > 0 ? 'masuk' : 'keluar');
            $kubikasiSelisih = round(abs($kubikasiFisik - $kubikasiSistem), 6);

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
                'total_kubikasi'       => $kubikasiSelisih,
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
            $ukuran  = Ukuran::findOrFail($row['id_ukuran']);
            $summary = StokPlatformJadi::where(['id_jenis_barang' => $row['id_jenis_barang'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokPlatformJadi::create(['id_jenis_barang' => $row['id_jenis_barang'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);
            return $this->opnameDenganSummary($row, $summary, 'OPNAME PLATFORM JADI', HppPlatformJadiLog::class, 'id_jenis_barang');
        });
    }

    private function opnameTriplekJadi(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            $ukuran  = Ukuran::findOrFail($row['id_ukuran']);
            $summary = StokTriplekJadi::where(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokTriplekJadi::create(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);
            return $this->opnameDenganSummary($row, $summary, 'OPNAME TRIPLEK JADI', HppTriplekJadiLog::class);
        });
    }

    private function opnameGudangSatu(array $row): bool
    {
        return DB::transaction(function () use ($row) {
            $ukuran  = Ukuran::findOrFail($row['id_ukuran']);
            $summary = StokGudangSatu::where(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw']])->lockForUpdate()->first();
            if (!$summary) $summary = StokGudangSatu::create(['id_jenis_kayu' => $row['id_jenis_kayu'], 'panjang' => (float) $ukuran->panjang, 'lebar' => (float) $ukuran->lebar, 'tebal' => (float) $ukuran->tebal, 'kw_grade' => $row['kw'], 'stok_lembar' => 0, 'stok_kubikasi' => 0, 'nilai_stok' => 0, 'hpp_average' => 0]);
            return $this->opnameDenganSummary($row, $summary, 'OPNAME GUDANG SATU', GudangSatuLog::class);
        });
    }

    public function render()
    {
        return view('livewire.opname-stok-table');
    }
}