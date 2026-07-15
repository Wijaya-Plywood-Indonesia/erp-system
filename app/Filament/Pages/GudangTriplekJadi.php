<?php

namespace App\Filament\Pages;

use App\Models\SerahTerimaGudangSatu;
use App\Models\StokTriplekJadi;
use App\Models\TriplekJadiMutasiKeluar;
use App\Models\TriplekJadiMutasiKeluarPalet;
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
    public const TUJUAN_GUDANG_SATU = 'Gudang Satu';

    public const TUJUAN_OPTIONS = [
        self::TUJUAN_NYUSUP,
        self::TUJUAN_GUDANG_SATU,
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
                ->body('Tujuan keluar tidak valid. Pilih Produksi Nyusup atau Gudang Satu.')
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

                // 🌟 Buat SATU baris antrean serah terima. tujuan='triplek_jadi'
                // membuatnya muncul di antrean Produksi Nyusup DAN Gudang Satu
                // sekaligus (lihat filter di SerahTerimaGudangSatuRelationManager).
                // Jumlah TIDAK disimpan di sini — accessor getJumlahAttribute()
                // di model mengambilnya langsung dari stok_lembar mutasi via
                // id_triplek_mutasi_keluar, jadi selalu konsisten satu sumber.
                SerahTerimaGudangSatu::create([
                    'id_triplek_mutasi_keluar' => $mutasi->id,
                    'tujuan'                   => 'triplek_jadi',
                    'diserahkan_oleh'          => Auth::user()?->name ?? 'System',
                    'diterima_oleh'            => '-',
                    'status'                   => 'Menunggu',
                ]);
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