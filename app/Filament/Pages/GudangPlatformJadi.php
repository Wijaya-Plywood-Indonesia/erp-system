<?php

namespace App\Filament\Pages;

use App\Models\HasilSanding;
use App\Models\HppPlatformJadiLog;
use App\Models\PlatformJadiMutasiKeluar;
use App\Models\PlatformJadiMutasiKeluarPalet;
use App\Models\SerahTerimaPlatformJadi;
use App\Models\StokPlatformJadi;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GudangPlatformJadi extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.gudang-platform-jadi';

    protected static ?string $navigationLabel = 'Gudang Platform Jadi';
    protected static string|UnitEnum|null $navigationGroup = 'Gudang';
    protected static ?string $title          = 'Gudang Platform Jadi';
    protected static ?int    $navigationSort = 22;

    // Tab aktif: 'masuk' (Serah Terima) / 'keluar'
    public string $activeTab = 'masuk';

    public string $search = '';             // search detail stok
    public string $antreanSearch = '';      // search antrean serah terima
    public string $keluarSearchQuery = '';  // search riwayat keluar

    // ── Form Barang Keluar ──
    public bool $showFormKeluarModal = false;
    public ?int $selectedStokId = null;     // id baris stok_platform_jadi
    public $jumlahPalet = 1;
    public array $paletQuantities = [0 => ''];
    public string $tujuanKeluar = 'Hotpress';
    public string $keteranganKeluar = '';

    protected $queryString = ['activeTab'];

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        return ($p * $l * $t * ($lembar ?? 0)) / 10000000;
    }

    // ─── DETAIL STOK ──────────────────────────────────────────────────────────

    public function getStokListProperty(): Collection
    {
        return StokPlatformJadi::with(['jenisBarang'])
            ->where('stok_lembar', '>', 0)
            ->get()
            ->filter(function ($item) {
                if (trim($this->search) === '') {
                    return true;
                }
                $q = strtolower(trim($this->search));
                $barang  = strtolower((string) $item->jenisBarang?->nama_jenis_barang);
                $grade   = strtolower((string) $item->kw_grade);
                $dimensi = strtolower(($item->panjang + 0) . 'x' . ($item->lebar + 0) . 'x' . ($item->tebal + 0));

                return str_contains($barang, $q)
                    || str_contains($grade, $q)
                    || str_contains($dimensi, $q);
            })
            ->values();
    }

    // ─── SERAH TERIMA (dari Hasil Sanding) ───────────────────────────────────

    /**
     * Gabungan antrean: HasilSanding yang BELUM diterima (paling atas,
     * terbaru dulu) + yang SUDAH diterima (di bawah, dari tabel serah terima).
     */
    public function getAntreanProperty(): Collection
    {
        $diterimaIds = SerahTerimaPlatformJadi::pluck('id_hasil_sanding');

        $rows = HasilSanding::with([
                'barangSetengahJadi.ukuran',
                'barangSetengahJadi.jenisBarang',
                'barangSetengahJadi.grade',
                'produksiSanding',
            ])
            ->get()
            ->map(function (HasilSanding $hs) use ($diterimaIds) {
                $bsj    = $hs->barangSetengahJadi;
                $ukuran = $bsj?->ukuran;

                $st = $diterimaIds->contains($hs->id)
                    ? SerahTerimaPlatformJadi::with('penerima')->where('id_hasil_sanding', $hs->id)->first()
                    : null;

                return (object) [
                    'id'            => $hs->id,
                    'no_palet'      => $hs->no_palet,
                    'jenis_barang'  => $bsj?->jenisBarang?->nama_jenis_barang ?? '-',
                    'panjang'       => (float) ($ukuran?->panjang ?? 0),
                    'lebar'         => (float) ($ukuran?->lebar ?? 0),
                    'tebal'         => (float) ($ukuran?->tebal ?? 0),
                    'grade'         => $bsj?->grade?->nama_grade ?? '-',
                    'jumlah'        => (int) $hs->kuantitas,
                    'created_at'    => $hs->created_at,
                    'sudah'         => $st !== null,
                    'penerima'      => $st?->penerima?->name,
                    'diterima_at'   => $st?->diterima_at,
                ];
            });

        if (trim($this->antreanSearch) !== '') {
            $q = strtolower(trim($this->antreanSearch));
            $rows = $rows->filter(fn ($r) =>
                str_contains(strtolower((string) $r->jenis_barang), $q)
                || str_contains(strtolower((string) $r->grade), $q)
                || str_contains(strtolower(($r->panjang + 0) . 'x' . ($r->lebar + 0) . 'x' . ($r->tebal + 0)), $q)
                || str_contains(strtolower('palet ' . $r->no_palet), $q)
            );
        }

        // Belum diterima di atas (terbaru dulu), sudah diterima di bawah.
        return $rows
            ->sortByDesc('created_at')
            ->sortBy(fn ($r) => $r->sudah ? 1 : 0)
            ->values();
    }

    /**
     * Terima satu palet hasil sanding ke Gudang Platform Jadi:
     * upsert stok + tulis log + catat serah terima. HPP sementara diabaikan (0).
     */
    public function terima(int $idHasilSanding): void
    {
        try {
            DB::transaction(function () use ($idHasilSanding) {
                $hs = HasilSanding::with([
                    'barangSetengahJadi.ukuran',
                    'barangSetengahJadi.grade',
                ])->lockForUpdate()->findOrFail($idHasilSanding);

                if (SerahTerimaPlatformJadi::where('id_hasil_sanding', $hs->id)->exists()) {
                    throw new \Exception('Palet ini sudah pernah diterima.');
                }

                $bsj    = $hs->barangSetengahJadi;
                $ukuran = $bsj?->ukuran;
                if (! $bsj || ! $ukuran) {
                    throw new \Exception('Data barang setengah jadi / ukuran tidak lengkap.');
                }

                $idJenisBarang = (int) $bsj->id_jenis_barang;
                $p  = (float) $ukuran->panjang;
                $l  = (float) $ukuran->lebar;
                $t  = (float) $ukuran->tebal;
                $kw = $bsj->grade?->nama_grade;

                $qty      = (int) $hs->kuantitas;
                $kubikasi = $this->hitungKubikasi($p, $l, $t, $qty);

                $user     = Auth::user();
                $userName = $user?->name ?? 'System';

                $stok = StokPlatformJadi::where('id_jenis_barang', $idJenisBarang)
                    ->where('panjang', $p)->where('lebar', $l)->where('tebal', $t)
                    ->where('kw_grade', $kw)
                    ->lockForUpdate()
                    ->first();

                if (! $stok) {
                    $stok = StokPlatformJadi::create([
                        'id_jenis_barang' => $idJenisBarang,
                        'panjang'         => $p,
                        'lebar'           => $l,
                        'tebal'           => $t,
                        'kw_grade'        => $kw,
                        'stok_lembar'     => 0,
                        'stok_kubikasi'   => 0,
                        'nilai_stok'      => 0,
                        'hpp_average'     => 0,
                    ]);
                }

                $before = [
                    'lembar'   => (int) $stok->stok_lembar,
                    'kubikasi' => (float) $stok->stok_kubikasi,
                    'nilai'    => (float) $stok->nilai_stok,
                ];

                $after = [
                    'lembar'   => $before['lembar'] + $qty,
                    'kubikasi' => round($before['kubikasi'] + $kubikasi, 6),
                    'nilai'    => $before['nilai'], // HPP diabaikan dulu -> nilai tidak berubah
                ];

                $log = HppPlatformJadiLog::create([
                    'id_jenis_barang'      => $idJenisBarang,
                    'panjang'              => $p,
                    'lebar'                => $l,
                    'tebal'                => $t,
                    'kw_grade'             => $kw,
                    'tanggal'              => now(),
                    'tipe_transaksi'       => 'masuk',
                    'referensi_type'       => HasilSanding::class,
                    'referensi_id'         => $hs->id,
                    'total_lembar'         => $qty,
                    'total_kubikasi'       => $kubikasi,
                    'hpp_pekerja'          => 0,
                    'hpp_bahan_penolong'   => 0,
                    'hpp_average'          => (float) $stok->hpp_average,
                    'nilai_stok'           => 0,
                    'stok_lembar_before'   => $before['lembar'],
                    'stok_kubikasi_before' => $before['kubikasi'],
                    'nilai_stok_before'    => $before['nilai'],
                    'stok_lembar_after'    => $after['lembar'],
                    'stok_kubikasi_after'  => $after['kubikasi'],
                    'nilai_stok_after'     => $after['nilai'],
                    'keterangan'           => 'Serah terima Hasil Sanding Palet ' . $hs->no_palet
                        . ' | Diterima: ' . $userName,
                ]);

                $stok->update([
                    'stok_lembar'   => $after['lembar'],
                    'stok_kubikasi' => $after['kubikasi'],
                    'nilai_stok'    => $after['nilai'],
                    'id_last_log'   => $log->id,
                ]);

                SerahTerimaPlatformJadi::create([
                    'id_hasil_sanding' => $hs->id,
                    'diterima_by'      => $user?->id,
                    'diterima_at'      => now(),
                ]);
            });

            Notification::make()->success()
                ->title('Barang diterima ke Gudang Platform Jadi.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()->danger()
                ->title('Gagal menerima barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    // ─── VENEER/PLATFORM KELUAR ──────────────────────────────────────────────

    public function updatedJumlahPalet($value): void
    {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            return;
        }

        $count = max(1, intval($value));
        $this->paletQuantities = array_slice($this->paletQuantities, 0, $count);

        while (count($this->paletQuantities) < $count) {
            $this->paletQuantities[] = '';
        }
    }

    public function hapusPalet(int $index): void
    {
        if (isset($this->paletQuantities[$index])) {
            unset($this->paletQuantities[$index]);
            $this->paletQuantities = array_values($this->paletQuantities);
            $this->jumlahPalet = count($this->paletQuantities);
        }
    }

    /**
     * Keluarkan barang dari stok: header mutasi + rincian palet, lalu
     * SATU BARIS LOG PER PALET (snapshot berantai), potong stok.
     */
    public function prosesKeluar(): void
    {
        $totalLembar = array_sum(array_map('intval', $this->paletQuantities));

        if (! $this->selectedStokId || $totalLembar <= 0 || trim($this->tujuanKeluar) === '') {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Pilih stok, isi kuantitas palet, dan tujuan keluar wajib diisi.')
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                $stok = StokPlatformJadi::lockForUpdate()->findOrFail($this->selectedStokId);

                if ($totalLembar > (int) $stok->stok_lembar) {
                    throw new \Exception('Sisa stok tidak mencukupi. Tersedia: ' . $stok->stok_lembar . ' lembar.');
                }

                $user     = Auth::user();
                $userName = $user?->name ?? 'System';

                $mutasi = PlatformJadiMutasiKeluar::create([
                    'id_jenis_barang' => $stok->id_jenis_barang,
                    'panjang'         => $stok->panjang,
                    'lebar'           => $stok->lebar,
                    'tebal'           => $stok->tebal,
                    'kw_grade'        => $stok->kw_grade,
                    'jumlah_palet'    => count($this->paletQuantities),
                    'stok_lembar'     => $totalLembar,
                    'stok_kubikasi'   => $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $totalLembar),
                    'tujuan'          => trim($this->tujuanKeluar),
                    'dikeluarkan_by'  => $user?->id,
                    'keterangan'      => trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : null,
                ]);

                $ketDasar = 'Keluar ke [' . trim($this->tujuanKeluar) . ']'
                    . ' | Oleh: ' . $userName
                    . ' | Ket: ' . (trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : '-');

                $totalPalet = count($this->paletQuantities);
                $lembar     = (int) $stok->stok_lembar;
                $kubikasi   = (float) $stok->stok_kubikasi;
                $nilai      = (float) $stok->nilai_stok;
                $lastLogId  = $stok->id_last_log;

                foreach ($this->paletQuantities as $index => $qtyRaw) {
                    $qty = intval($qtyRaw);

                    PlatformJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet'      => $index + 1,
                        'jumlah_lembar'    => $qty,
                    ]);

                    if ($qty <= 0) {
                        continue;
                    }

                    $kubPalet   = $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $qty);
                    $nilaiPalet = round($qty * (float) $stok->hpp_average, 2);

                    $beforeLembar   = $lembar;
                    $beforeKubikasi = $kubikasi;
                    $beforeNilai    = $nilai;

                    $lembar   -= $qty;
                    $kubikasi  = max(0.0, round($kubikasi - $kubPalet, 6));
                    $nilai     = max(0.0, round($nilai - $nilaiPalet, 2));

                    $log = HppPlatformJadiLog::create([
                        'id_jenis_barang'      => $stok->id_jenis_barang,
                        'panjang'              => $stok->panjang,
                        'lebar'                => $stok->lebar,
                        'tebal'                => $stok->tebal,
                        'kw_grade'             => $stok->kw_grade,
                        'tanggal'              => now(),
                        'tipe_transaksi'       => 'keluar',
                        'referensi_type'       => PlatformJadiMutasiKeluar::class,
                        'referensi_id'         => $mutasi->id,
                        'total_lembar'         => $qty,
                        'total_kubikasi'       => $kubPalet,
                        'hpp_pekerja'          => 0,
                        'hpp_bahan_penolong'   => 0,
                        'hpp_average'          => (float) $stok->hpp_average,
                        'nilai_stok'           => $nilaiPalet,
                        'stok_lembar_before'   => $beforeLembar,
                        'stok_kubikasi_before' => $beforeKubikasi,
                        'nilai_stok_before'    => $beforeNilai,
                        'stok_lembar_after'    => $lembar,
                        'stok_kubikasi_after'  => $kubikasi,
                        'nilai_stok_after'     => $nilai,
                        'keterangan'           => 'Palet ' . ($index + 1) . '/' . $totalPalet . ' | ' . $ketDasar,
                    ]);

                    $lastLogId = $log->id;
                }

                $stok->update([
                    'stok_lembar'   => $lembar,
                    'stok_kubikasi' => $kubikasi,
                    'nilai_stok'    => $nilai,
                    'id_last_log'   => $lastLogId,
                ]);
            });

            // Reset form
            $this->selectedStokId      = null;
            $this->jumlahPalet         = 1;
            $this->paletQuantities     = [0 => ''];
            $this->tujuanKeluar        = 'Hotpress';
            $this->keteranganKeluar    = '';
            $this->showFormKeluarModal = false;

            Notification::make()->success()
                ->title('Mutasi Keluar Berhasil')
                ->body("{$totalLembar} lembar berhasil dikeluarkan dari Gudang Platform Jadi.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()->danger()
                ->title('Gagal Mengeluarkan Barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getRiwayatKeluarProperty(): Collection
    {
        $query = PlatformJadiMutasiKeluar::with(['jenisBarang', 'palets', 'operator'])
            ->orderByDesc('created_at');

        if (trim($this->keluarSearchQuery) !== '') {
            $q = strtolower(trim($this->keluarSearchQuery));
            $query->where(function ($query) use ($q) {
                $query->whereHas('jenisBarang', fn ($qr) => $qr->whereRaw('LOWER(nama_jenis_barang) LIKE ?', ["%{$q}%"]))
                    ->orWhereRaw('LOWER(kw_grade) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(tujuan) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ["%{$q}%"]);
            });
        }

        return $query->get();
    }
}