<?php

namespace App\Filament\Pages;

use App\Models\HppTriplekJadiLog;
use App\Models\SerahTerimaGudangSatu;
use App\Models\SerahTerimaHp;
use App\Models\SerahTerimaTriplekJadi;
use App\Models\StokTriplekJadi;
use App\Models\TriplekJadiMutasiKeluar;
use App\Models\TriplekJadiMutasiKeluarPalet;
use App\Models\WipSandingReset;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GudangTriplekJadi extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $title = 'Gudang Triplek Jadi';
    protected string $view = 'filament.pages.gudang-triplek-jadi';
    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    // 🌟 Tujuan keluar yang diizinkan — SATU sumber kebenaran.
    // Dipakai di view (opsi <select>) dan validasi prosesKeluar().
    public const TUJUAN_NYUSUP      = 'Produksi Nyusup';
    public const TUJUAN_GUDANG_SATU = 'Produksi Sampling Plywood';
    public const TUJUAN_SANDING     = 'Produksi Sanding';

    public const TUJUAN_OPTIONS = [
        self::TUJUAN_NYUSUP,
        self::TUJUAN_GUDANG_SATU,
        self::TUJUAN_SANDING,
    ];

    public string $searchQuery = '';        // search dropdown stok (di modal)
    public string $keluarSearchQuery = '';  // search riwayat keluar

    // ── Form Barang Keluar ──
    public bool $showFormKeluarModal = false;
    public ?int $selectedStokId = null;
    public $jumlahPalet = 1;
    public array $paletQuantities = [0 => ''];
    public string $tujuanKeluar = self::TUJUAN_NYUSUP;
    public string $keteranganKeluar = '';

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        return ($p * $l * $t * ($lembar ?? 0)) / 10000000;
    }

    // ─── WIP SANDING (agregat) ───────────────────────────────────────────────

    /**
     * Total lembar yang "sedang di sanding" — sudah keluar dari gudang menuju
     * Produksi Sanding dan diterima di sana, tapi hasilnya BELUM kembali masuk
     * stok triplek jadi.
     *
     * Rumus agregat (bukan per-batch):
     *   WIP = Σ(keluar ke sanding) − Σ(masuk dari hasil sanding)
     *
     * Kedua angka diambil dari HppTriplekJadiLog agar satu sumber:
     *  - Keluar : tipe 'keluar', referensi TriplekJadiMutasiKeluar, TAPI hanya
     *             mutasi yang tujuannya Produksi Sanding (keluar ke Nyusup/Gudang
     *             Satu itu keluar permanen, bukan WIP).
     *  - Masuk  : tipe 'masuk', referensi SerahTerimaTriplekJadi yang asalnya
     *             dari sanding (punya id_hasil_sanding terisi).
     *
     * Casing tipe_transaksi SENGAJA dibandingkan case-insensitive (LOWER) karena
     * di kode lama ada campuran 'keluar'/'Masuk' — supaya hitungan tidak bergantung
     * pada collation database.
     *
     * Selaras dengan halaman Stok: mengurangi baseline "tutup buku" dari tabel
     * wip_sanding_resets. Jadi setelah pengawas menekan "Selesaikan WIP" di Stok,
     * badge total di Gudang ini pun ikut berkurang.
     */
    public function getWipSandingProperty(): float
    {
        // Id mutasi keluar yang tujuannya Produksi Sanding
        $idMutasiSanding = TriplekJadiMutasiKeluar::where('tujuan', self::TUJUAN_SANDING)
            ->pluck('id');

        if ($idMutasiSanding->isEmpty()) {
            return 0.0;
        }

        $keluar = (float) HppTriplekJadiLog::query()
            ->whereRaw('LOWER(tipe_transaksi) = ?', ['keluar'])
            ->where('referensi_type', TriplekJadiMutasiKeluar::class)
            ->whereIn('referensi_id', $idMutasiSanding)
            ->sum('total_lembar');

        // Id serah terima triplek jadi yang benar-benar berasal dari sanding
        $idSerahDariSanding = SerahTerimaTriplekJadi::whereNotNull('id_hasil_sanding')
            ->pluck('id');

        $masuk = $idSerahDariSanding->isEmpty()
            ? 0.0
            : (float) HppTriplekJadiLog::query()
                ->whereRaw('LOWER(tipe_transaksi) = ?', ['masuk'])
                ->where('referensi_type', SerahTerimaTriplekJadi::class)
                ->whereIn('referensi_id', $idSerahDariSanding)
                ->sum('total_lembar');

        // Kurangi baseline yang sudah "ditutup buku" per spesifikasi. Kalau satu
        // spec direset berkali-kali, ambil baseline terbesar (reset terakhir).
        $baselineKeluar = 0.0;
        $baselineMasuk = 0.0;
        WipSandingReset::query()
            ->orderBy('spec_key')
            ->get(['spec_key', 'keluar_kumulatif', 'masuk_kumulatif'])
            ->groupBy('spec_key')
            ->each(function ($rows) use (&$baselineKeluar, &$baselineMasuk) {
                $baselineKeluar += (float) $rows->max('keluar_kumulatif');
                $baselineMasuk  += (float) $rows->max('masuk_kumulatif');
            });

        $wip = ($keluar - $baselineKeluar) - ($masuk - $baselineMasuk);

        // Tidak boleh negatif (jaga-jaga bila ada data lama tak sinkron).
        return max(0.0, $wip);
    }

    // ─── STOK (untuk dropdown pilih barang di modal keluar) ─────────────────

    public function getStokListProperty(): Collection
    {
        return StokTriplekJadi::with(['jenisKayu', 'lastLog'])
            ->where('stok_lembar', '>', 0)
            ->get()
            ->filter(function ($item) {
                if (trim($this->searchQuery) === '') {
                    return true;
                }
                $q        = strtolower(trim($this->searchQuery));
                $namaKayu = strtolower((string) $item->jenisKayu?->nama_kayu);
                $dimensi  = strtolower(($item->panjang + 0) . 'x' . ($item->lebar + 0) . 'x' . ($item->tebal + 0));

                return str_contains($namaKayu, $q)
                    || str_contains(strtolower((string) $item->kw_grade), $q)
                    || str_contains($dimensi, $q);
            })
            ->values();
    }

    // ─── BARANG KELUAR ───────────────────────────────────────────────────────

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
     * Catat mutasi keluar: header + rincian palet.
     * Stok TIDAK dipotong di sini — pemotongan terjadi saat barang
     * dikonfirmasi diterima di tujuan (Produksi Nyusup / Gudang Satu),
     * konsisten dengan pola veneer/platform.
     */
    public function prosesKeluar(): void
    {
        $totalLembar = array_sum(array_map('intval', $this->paletQuantities));

        if (! $this->selectedStokId || $totalLembar <= 0) {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Spesifikasi stok dan kuantitas palet wajib diisi.')
                ->send();
            return;
        }

        // Tujuan hanya boleh salah satu dari daftar resmi.
        if (! in_array($this->tujuanKeluar, self::TUJUAN_OPTIONS, true)) {
            Notification::make()->danger()
                ->title('Input Gagal')
                ->body('Tujuan keluar tidak valid. Pilih Produksi Nyusup, Gudang Satu, atau Produksi Sanding.')
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                $stok = StokTriplekJadi::lockForUpdate()->findOrFail($this->selectedStokId);

                if ($totalLembar > (int) $stok->stok_lembar) {
                    throw new \Exception('Sisa stok tidak mencukupi. Tersedia: ' . $stok->stok_lembar . ' lembar.');
                }

                $mutasi = TriplekJadiMutasiKeluar::create([
                    'id_jenis_kayu'  => $stok->id_jenis_kayu,
                    'panjang'        => $stok->panjang,
                    'lebar'          => $stok->lebar,
                    'tebal'          => $stok->tebal,
                    'kw_grade'       => $stok->kw_grade,
                    'jumlah_palet'   => count($this->paletQuantities),
                    'stok_lembar'    => $totalLembar,
                    'stok_kubikasi'  => $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $totalLembar),
                    'tujuan'         => $this->tujuanKeluar,
                    'dikeluarkan_by' => Auth::id(),
                    'keterangan'     => trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : null,
                    'status'         => TriplekJadiMutasiKeluar::STATUS_DIKIRIM,
                ]);

                foreach ($this->paletQuantities as $index => $qtyRaw) {
                    $qty = intval($qtyRaw);
                    if ($qty <= 0) {
                        continue;
                    }

                    TriplekJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet'      => $index + 1,
                        'jumlah_lembar'    => $qty,
                    ]);
                }

                // 🌟 Buat baris antrean serah terima sesuai tujuan.
                // Jumlah TIDAK disimpan di mana pun selain mutasi — accessor
                // di masing-masing model mengambilnya dari stok_lembar via
                // id_triplek_mutasi_keluar, jadi selalu konsisten satu sumber.
                if ($this->tujuanKeluar === self::TUJUAN_SANDING) {
                    // Antrean Produksi Sanding hidup di tabel SerahTerimaHp
                    // (tab "Terima Platform/Plywood", filter tujuan='sanding').
                    SerahTerimaHp::create([
                        'id_triplek_mutasi_keluar' => $mutasi->id,
                        'tujuan'                   => 'sanding',
                        'diserahkan_oleh'          => Auth::user()?->name ?? 'System',
                        'diterima_oleh'            => '-',
                        'status'                   => 'Serah ke Sanding',
                    ]);
                } else {
                    // Nyusup & Gudang Satu: tujuan='triplek_jadi' membuat baris
                    // muncul di antrean KEDUANYA sekaligus (lihat filter di
                    // SerahTerimaGudangSatuRelationManager); siapa terima duluan
                    // mengklaimnya.
                    SerahTerimaGudangSatu::create([
                        'id_triplek_mutasi_keluar' => $mutasi->id,
                        'tujuan'                   => 'triplek_jadi',
                        'diserahkan_oleh'          => Auth::user()?->name ?? 'System',
                        'diterima_oleh'            => '-',
                        'status'                   => 'Menunggu',
                    ]);
                }
            });

            // Reset form
            $this->selectedStokId      = null;
            $this->jumlahPalet         = 1;
            $this->paletQuantities     = [0 => ''];
            $this->tujuanKeluar        = self::TUJUAN_NYUSUP;
            $this->keteranganKeluar    = '';
            $this->showFormKeluarModal = false;

            Notification::make()->success()
                ->title('Mutasi Keluar Dicatat')
                ->body("{$totalLembar} lembar tercatat dikirim ke tujuan. Stok akan terpotong setelah dikonfirmasi diterima.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()->danger()
                ->title('Gagal Mengeluarkan Barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getRiwayatKeluarFilteredProperty(): Collection
    {
        $query = TriplekJadiMutasiKeluar::with(['jenisKayu', 'palets', 'operator'])
            ->orderByDesc('created_at');

        if (trim($this->keluarSearchQuery) !== '') {
            $q = strtolower(trim($this->keluarSearchQuery));
            $query->where(function ($query) use ($q) {
                $query->whereHas('jenisKayu', fn($qr) => $qr->whereRaw('LOWER(nama_kayu) LIKE ?', ["%{$q}%"]))
                    ->orWhereRaw('LOWER(kw_grade) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(tujuan) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ["%{$q}%"]);
            });
        }

        // Kembalikan model langsung supaya view bisa akses relasi
        // $rk->palets, $rk->jenisKayu, $rk->operator (gaya Platform Jadi).
        return $query->get();
    }
}