<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\GudangVeneerJadi as GudangModel;
use App\Models\StokVeneerJadi;
use App\Models\HppVeneerJadiLog;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GudangVeneerJadi extends Page
{
    protected static ?string $title = 'Gudang Veneer Jadi';
    protected string $view = 'filament.pages.gudang-veneer-jadi';

    public string $searchQuery = '';
    public string $tableSearchQuery = '';

    // ✅ Properti untuk modal konfirmasi
    public bool $showConfirmModal = false;
    public ?int $selectedItemId = null;

    public function hitungKubikasi(float $p, float $l, float $t, int $lembar): float
    {
        return ($p * $l * $t * $lembar) / 10000000;
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
     * ✅ PROSES TERIMA BARANG (Dipanggil dari tombol 'Ya' di dalam modal konfirmasi)
     */
    /**
     * ✅ PROSES TERIMA BARANG (Dipanggil dari tombol 'Ya' di dalam modal konfirmasi)
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

                // ✅ throw, bukan return — supaya transaksi rollback dan user dapat feedback
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
        // Eager load jenisKayu dan lastLog untuk melacak history transaksi/opname terakhir
        $allStok = StokVeneerJadi::with(['jenisKayu', 'lastLog'])
            ->get()
            ->filter(function ($item) {
                if (empty($this->searchQuery)) return true;
                $q = strtolower($this->searchQuery);

                // Menggunakan kolom nama_kayu sesuai relasi database Anda
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
}
