<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Models\GudangVeneerJadi as GudangModel;
use App\Models\StokVeneerJadi;
use App\Models\HppVeneerJadiLog;
use App\Models\VeneerJadiMutasiKeluar;
use App\Models\VeneerJadiMutasiKeluarPalet;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GudangVeneerJadi extends Page
{
    // Icon menu navigasi di sidebar Filament

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $title = 'Gudang Veneer Jadi';
    protected string $view = 'filament.pages.gudang-veneer-jadi';
    public string $activeTab = 'masuk';
    public string $searchQuery = '';
    public string $tableSearchQuery = '';
    public string $keluarSearchQuery = '';

    // Properti untuk modal konfirmasi
    public bool $showConfirmModal = false;
    public ?int $selectedItemId = null;

    // Modal Form Keluar Barang
    public bool $showFormKeluarModal = false;
    public ?int $selectedStokId = null;

    // 🌟 PERBAIKAN: Diubah menjadi tipe data dinamis agar bisa menampung string kosong saat diedit
    public $jumlahPalet = 1;
    public array $paletQuantities = [0 => '']; // Input dinamis lembar per palet
    public string $tujuanKeluar = 'Hotpress';
    public string $keteranganKeluar = '';

    protected $queryString = ['activeTab'];

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        $lembarAman = $lembar ?? 0;
        return ($p * $l * $t * $lembarAman) / 10000000;
    }

    /**
     * ✅ BUKA MODAL KONFIRMASI
     */
    public function confirmTerima(int $id): void
    {
        $this->selectedItemId = $id;
        $this->showConfirmModal = true;
    }

    /**
     * ✅ BATAL KONFIRMASI
     */
    public function cancelConfirm(): void
    {
        $this->showConfirmModal = false;
        $this->selectedItemId = null;
    }

    /**
     * ✅ PROSES TERIMA BARANG
     */
    public function terimaBarang(): void
    {
        if (!$this->selectedItemId) {
            return;
        }

        $id = $this->selectedItemId;

        try {
            DB::transaction(function () use ($id) {
                $record = GudangModel::where('id', $id)->lockForUpdate()->first();

                if (!$record) {
                    throw new \Exception('Data tidak ditemukan.');
                }

                if ($record->status_gudang === 'sudah diterima') {
                    throw new \Exception('Barang ini sudah pernah diterima sebelumnya.');
                }

                $user     = Auth::user();
                $userName = $user?->name ?? 'System';

                $stokInduk = StokVeneerJadi::where('id_jenis_kayu', $record->id_jenis_kayu)
                    ->where('panjang', $record->panjang)
                    ->where('lebar', $record->lebar)
                    ->where('tebal', $record->tebal)
                    ->where('kw_grade', $record->kw_grade)
                    ->lockForUpdate()
                    ->first();

                if (!$stokInduk) {
                    $stokInduk = StokVeneerJadi::create([
                        'id_jenis_kayu'           => $record->id_jenis_kayu,
                        'panjang'                 => $record->panjang,
                        'lebar'                   => $record->lebar,
                        'tebal'                   => $record->tebal,
                        'kw_grade'                => $record->kw_grade,
                        'stok_lembar'             => 0,
                        'stok_kubikasi'           => 0,
                        'nilai_stok'              => 0,
                        'hpp_average'             => 0,
                        'hpp_pekerja_last'        => 0,
                        'hpp_bahan_penolong_last' => 0,
                        'id_last_log'             => null,
                    ]);
                }

                $stokLembarBefore    = $stokInduk->stok_lembar;
                $stokKubikasiBefore  = $stokInduk->stok_kubikasi;
                $nilaiStokBefore     = $stokInduk->nilai_stok;

                $stokLembarAfter     = $stokLembarBefore + $record->stok_lembar;
                $stokKubikasiAfter   = $stokKubikasiBefore + $record->stok_kubikasi;
                $nilaiStokAfter      = $nilaiStokBefore + $record->nilai_stok;
                $hppAverageAfter     = $stokLembarAfter > 0
                    ? ($nilaiStokAfter / $stokLembarAfter)
                    : 0;

                $log = HppVeneerJadiLog::create([
                    'id_jenis_kayu'        => $record->id_jenis_kayu,
                    'panjang'              => $record->panjang,
                    'lebar'                => $record->lebar,
                    'tebal'                => $record->tebal,
                    'kw_grade'             => $record->kw_grade,
                    'tanggal'              => now(),
                    'tipe_transaksi'       => 'MASUK',
                    'referensi_type'       => GudangModel::class,
                    'referensi_id'         => $record->id,
                    'total_lembar'         => $record->stok_lembar,
                    'total_kubikasi'       => $record->stok_kubikasi,
                    'hpp_pekerja'          => $record->hpp_pekerja_last ?? 0,
                    'hpp_bahan_penolong'   => $record->hpp_bahan_penolong_last ?? 0,
                    'hpp_average'          => $hppAverageAfter,
                    'nilai_stok'           => $record->nilai_stok,
                    'stok_lembar_before'   => $stokLembarBefore,
                    'stok_kubikasi_before' => $stokKubikasiBefore,
                    'nilai_stok_before'    => $nilaiStokBefore,
                    'stok_lembar_after'    => $stokLembarAfter,
                    'stok_kubikasi_after'  => $stokKubikasiAfter,
                    'nilai_stok_after'     => $nilaiStokAfter,
                    'keterangan'           => sprintf(
                        "%s, diterima oleh: %s pada %s",
                        $record->keterangan ?? 'Produksi Repair',
                        $userName,
                        now()->translatedFormat('d F Y H:i')
                    ),
                ]);

                $stokInduk->update([
                    'stok_lembar'   => $stokLembarAfter,
                    'stok_kubikasi' => $stokKubikasiAfter,
                    'nilai_stok'    => $nilaiStokAfter,
                    'hpp_average'   => $hppAverageAfter,
                    'id_last_log'   => $log->id,
                ]);

                $record->update([
                    'status_gudang' => 'sudah diterima',
                    'diterima_by'   => $user?->id,
                    'diterima_at'   => now(),
                ]);
            });

            Notification::make()
                ->success()
                ->title('Sukses Diterima!')
                ->body('Barang resmi masuk gudang dan stok telah diperbarui.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Menerima Barang')
                ->body($e->getMessage())
                ->send();
        }

        $this->showConfirmModal = false;
        $this->selectedItemId   = null;
        $this->dispatch('$refresh');
    }

    /**
     * 📦 STOK UTAMA (Sudah di StokVeneerJadi)
     */
    public function getSplitStokProperty(): array
    {
        $allStok = StokVeneerJadi::with(['jenisKayu', 'lastLog'])
            ->get()
            ->filter(function ($item) {
                if (empty($this->searchQuery))
                    return true;
                $q = strtolower($this->searchQuery);
                $namaKayu = $item->jenisKayu ? strtolower($item->jenisKayu->nama_kayu) : '';

                return str_contains($namaKayu, $q) ||
                    str_contains(strtolower($item->kw_grade), $q) ||
                    str_contains(strtolower(($item->panjang + 0) . "x" . ($item->lebar + 0) . "x" . ($item->tebal + 0)), $q);
            });

        return [
            'faceback' => $allStok->filter(fn($item) => $item->tebal < 1.0),
            'core'     => $allStok->filter(fn($item) => $item->tebal >= 1.0),
        ];
    }

    /**
     * 📥 ANTREAN: Data di GudangVeneerJadi (Tabel Sementara)
     */
    public function getAntreanFilteredProperty(): Collection
    {

        $query = GudangModel::with(['jenisKayu', 'penerima'])
            ->select([
                'gudang_veneer_jadis.*',
                'jenis_kayus.nama_kayu as jenis_kayu_nama',
            ])
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'gudang_veneer_jadis.id_jenis_kayu')
            ->orderByRaw("FIELD(gudang_veneer_jadis.status_gudang, 'belum diterima', 'sudah diterima') ASC")
            ->orderBy('gudang_veneer_jadis.created_at', 'desc');


        if (!empty($this->tableSearchQuery)) {
            $q = strtolower($this->tableSearchQuery);
            $query->where(function ($query) use ($q) {
                $query->whereRaw('LOWER(jenis_kayus.nama_kayu) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(gudang_veneer_jadis.kw_grade) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(gudang_veneer_jadis.keterangan) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(CONCAT(
                        (gudang_veneer_jadis.panjang + 0), "x", 
                        (gudang_veneer_jadis.lebar + 0), "x", 
                        (gudang_veneer_jadis.tebal + 0)
                    )) LIKE ?', ["%{$q}%"]);
            });
        }
        return $query->get()->map(fn($item) => [
            'id'            => $item->id,
            'jenis_kayu'    => $item->jenis_kayu_nama,
            'panjang'       => $item->panjang,
            'lebar'         => $item->lebar,
            'tebal'         => $item->tebal,
            'kw'            => $item->kw_grade,
            'jumlah'        => $item->stok_lembar,
            'stok_kubikasi' => $item->stok_kubikasi,
            'created_at'    => $item->created_at,
            'status_gudang' => $item->status_gudang ?? 'belum diterima',
            'diterima_at'   => $item->diterima_at,
            'diterima_by'   => $item->diterima_by,
            'penerima_name' => $item->penerima?->name ?? 'N/A',
            'keterangan'    => $item->keterangan,
        ]);
    }

    /**
     * 🌟 REAL-TIME REPEATER LOGIC
     * Sinkronisasi dinamis jumlah palet ketika input jumlahPalet berubah di browser.
     */
    public function updatedJumlahPalet($value): void
    {
        // Jika input dikosongkan sementara oleh operator saat mengetik, jangan overwrite ke 1
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            return;
        }

        $count = max(1, intval($value));
        $this->paletQuantities = array_slice($this->paletQuantities, 0, $count);

        while (count($this->paletQuantities) < $count) {
            $this->paletQuantities[] = '';
        }
    }

    /**
     * 🌟 REAL-TIME DELETE LOGIC
     * Memungkinkan penghapusan baris palet secara instan dan memperbarui counter jumlahPalet di atasnya.
     */
    public function hapusPalet(int $index): void
    {
        if (isset($this->paletQuantities[$index])) {
            unset($this->paletQuantities[$index]);
            // Re-index array kunci agar tetap berurutan 0, 1, 2...
            $this->paletQuantities = array_values($this->paletQuantities);
            // Sinkronisasikan angka di form Jumlah Palet secara real-time
            $this->jumlahPalet = count($this->paletQuantities);
        }
    }

    public function prosesKeluar(): void
    {
        $totalLembar = array_sum(array_map('intval', $this->paletQuantities));

        if (!$this->selectedStokId || $totalLembar <= 0 || empty($this->tujuanKeluar)) {
            Notification::make()
                ->danger()
                ->title('Input Gagal')
                ->body('Spesifikasi stok, kuantitas palet, dan tujuan pengeluaran wajib diisi.')
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                $stok = StokVeneerJadi::where('id', $this->selectedStokId)->lockForUpdate()->first();

                if (!$stok || $totalLembar > $stok->stok_lembar) {
                    throw new \Exception('Sisa stok fisik di gudang tidak mencukupi.');
                }

                $stokLembarBefore   = $stok->stok_lembar;
                $stokKubikasiBefore = $stok->stok_kubikasi;
                $nilaiStokBefore    = $stok->nilai_stok;

                $stokLembarAfter    = $stokLembarBefore - $totalLembar;
                $stokKubikasiAfter  = $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $stokLembarAfter);

                $nilaiStokTerpotong = $totalLembar * $stok->hpp_average;
                $nilaiStokAfter     = max(0, $nilaiStokBefore - $nilaiStokTerpotong);

                $stok->update([
                    'stok_lembar'   => $stokLembarAfter,
                    'stok_kubikasi' => $stokKubikasiAfter,
                    'nilai_stok'    => $nilaiStokAfter,
                ]);

                $mutasi = VeneerJadiMutasiKeluar::create([
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
                    'keterangan'     => $this->keteranganKeluar,
                ]);

                foreach ($this->paletQuantities as $index => $qty) {
                    VeneerJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet'      => $index + 1,
                        'jumlah_lembar'    => intval($qty),
                    ]);
                }

                $log = HppVeneerJadiLog::create([
                    'id_jenis_kayu'        => $stok->id_jenis_kayu,
                    'panjang'              => $stok->panjang,
                    'lebar'                => $stok->lebar,
                    'tebal'                => $stok->tebal,
                    'kw_grade'             => $stok->kw_grade,
                    'tanggal'              => now(),
                    'tipe_transaksi'       => 'KELUAR',
                    'referensi_type'       => VeneerJadiMutasiKeluar::class,
                    'referensi_id'         => $mutasi->id,
                    'total_lembar'         => $totalLembar,
                    'total_kubikasi'       => $mutasi->stok_kubikasi,
                    'hpp_pekerja'          => $stok->hpp_pekerja_last ?? 0,
                    'hpp_bahan_penolong'   => $stok->hpp_bahan_penolong_last ?? 0,
                    'hpp_average'          => $stok->hpp_average,
                    'nilai_stok'           => $nilaiStokTerpotong,
                    'stok_lembar_before'   => $stokLembarBefore,
                    'stok_kubikasi_before' => $stokKubikasiBefore,
                    'nilai_stok_before'    => $nilaiStokBefore,
                    'stok_lembar_after'    => $stokLembarAfter,
                    'stok_kubikasi_after'  => $stokKubikasiAfter,
                    'nilai_stok_after'     => $nilaiStokAfter,
                    'keterangan'           => "Mutasi Keluar ke [{$this->tujuanKeluar}] sebanyak " . count($this->paletQuantities) . " palet.",
                ]);

                $stok->update(['id_last_log' => $log->id]);
            });

            unset($this->splitStok);
            unset($this->riwayatKeluarFiltered);

            // Reset Form ke keadaan bersih
            $this->selectedStokId   = null;
            $this->jumlahPalet      = 1;
            $this->paletQuantities  = [0 => ''];
            $this->tujuanKeluar     = 'Hotpress';
            $this->keteranganKeluar = '';
            $this->showFormKeluarModal = false;

            Notification::make()
                ->success()
                ->title('✓ Mutasi Keluar Berhasil!')
                ->body("Sebanyak {$totalLembar} lembar veneer berhasil dipotong dari stok gudang.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Mengeluarkan Barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * 📤 RIWAYAT MUTASI KELUAR
     */
    public function getRiwayatKeluarFilteredProperty(): Collection
    {
        $query = VeneerJadiMutasiKeluar::with(['jenisKayu', 'palets', 'operator'])
            ->orderBy('created_at', 'desc');

        if (!empty($this->keluarSearchQuery)) {
            $q = strtolower($this->keluarSearchQuery);
            $query->where(function ($query) use ($q) {
                $query->whereHas('jenisKayu', function ($qr) use ($q) {
                    $qr->whereRaw('LOWER(nama_kayu) LIKE ?', ["%{$q}%"]);
                })
                    ->orWhereRaw('LOWER(kw_grade) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(tujuan) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ["%{$q}%"]);
            });
        }

        return $query->get()->map(fn($item) => [
            'created_at'     => $item->created_at->format('d/m/Y H:i'),
            'jenis_kayu'     => $item->jenisKayu->nama_kayu ?? '-',
            'panjang'        => $item->panjang,
            'lebar'          => $item->lebar,
            'tebal'          => $item->tebal,
            'kw'             => $item->kw_grade,
            'stok_lembar'    => $item->stok_lembar ?? 0,
            'stok_kubikasi'  => $item->stok_kubikasi ?? 0,
            'jumlah_palet'   => $item->jumlah_palet,
            'rincian_palet'  => $item->palets->pluck('jumlah_lembar')->toArray(),
            'tujuan'         => $item->tujuan,
            'dikeluarkan_by' => $item->operator->name ?? 'System',
            'keterangan'     => $item->keterangan,
        ]);
    }
}
