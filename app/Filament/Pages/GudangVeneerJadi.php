<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\GudangVeneerJadi as GudangModel;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class GudangVeneerJadi extends Page
{
    // Icon menu navigasi di sidebar Filament
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $title = 'Gudang Veneer Jadi';

    // Path view Blade custom
    protected string $view = 'filament.pages.gudang-veneer-jadi';

    // State pencarian Livewire (.live)
    public string $searchQuery = '';
    public string $tableSearchQuery = '';

    // State Antrean Masuk dari Divisi Repair (Section 2)
    // Dalam riil database, Anda dapat membind ini ke tabel / model tersendiri (misal: AntreanRepair)
    public array $antreanMasuk = [];

    /**
     * Inisialisasi data dummy antrean awal saat halaman pertama dimuat.
     */
    public function mount(): void
    {
        $this->antreanMasuk = [
            [
                'id' => 101,
                'tanggal' => '02/07/2026 14:30',
                'jenis_kayu' => 'Sengon',
                'panjang' => 244,
                'lebar' => 122,
                'tebal' => 0.5,
                'kw' => '3',
                'jumlah' => 500,
            ],
            [
                'id' => 102,
                'tanggal' => '02/07/2026 15:10',
                'jenis_kayu' => 'Sengon',
                'panjang' => 244,
                'lebar' => 122,
                'tebal' => 1.2,
                'kw' => '4',
                'jumlah' => 800,
            ],
            [
                'id' => 103,
                'tanggal' => '02/07/2026 16:00',
                'jenis_kayu' => 'Sengon',
                'panjang' => 244,
                'lebar' => 122,
                'tebal' => 0.8,
                'kw' => '3',
                'jumlah' => 1250,
            ],
            [
                'id' => 104,
                'tanggal' => '02/07/2026 16:30',
                'jenis_kayu' => 'Sengon',
                'panjang' => 122,
                'lebar' => 244,
                'tebal' => 2.2,
                'kw' => '3',
                'jumlah' => 350,
            ],
            [
                'id' => 105,
                'tanggal' => '02/07/2026 17:00',
                'jenis_kayu' => 'Sengon',
                'panjang' => 244,
                'lebar' => 122,
                'tebal' => 0.6,
                'kw' => 'Af',
                'jumlah' => 125,
            ]
        ];
    }

    /**
     * Fungsi menghitung volume kubikasi presisi (P cm * L cm * T mm * Lembar / 1 Milyar)
     */
    public function hitungKubikasi(float $p, float $l, float $t, int $lembar): float
    {
        return ($p * $l * $t * $lembar) / 10000000;
    }

    /**
     * Handler Penerimaan: Memindahkan data antrean fisik ke saldo stok utama database (Section 1)
     */
    public function terimaBarang(int $id): void
    {
        // Cari item dalam antrean lokal
        $itemIndex = collect($this->antreanMasuk)->search(fn($q) => $q['id'] === $id);

        if ($itemIndex === false) {
            Notification::make()
                ->danger()
                ->title('Gagal menerima barang')
                ->body('Data transaksi antrean tidak ditemukan.')
                ->send();
            return;
        }

        $item = $this->antreanMasuk[$itemIndex];

        // 1. Dapatkan Jenis Kayu (Relasi ke tabel jenis_kayu)
        // Jika belum ada, buat record baru
        $jenisKayu = \DB::table('jenis_kayus')->firstOrCreate(
            ['nama' => $item['jenis_kayu']],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // 2. Tambah/Perbarui Saldo ke Database GudangVeneerJadi
        $stokGudang = GudangModel::firstOrCreate(
            [
                'id_jenis_kayu' => $jenisKayu->id,
                'panjang' => $item['panjang'],
                'lebar' => $item['lebar'],
                'tebal' => $item['tebal'],
                'kw_grade' => $item['kw'],
            ],
            [
                'stok_lembar' => 0,
                'stok_kubikasi' => 0.0,
                'nilai_stok' => 0.0,
                'hpp_average' => 0.0,
            ]
        );

        // Update nilai stok lembar & hitung kubikasi barunya
        $newLembar = $stokGudang->stok_lembar + $item['jumlah'];
        $newKubikasi = $this->hitungKubikasi($item['panjang'], $item['lebar'], $item['tebal'], $newLembar);

        $stokGudang->update([
            'stok_lembar' => $newLembar,
            'stok_kubikasi' => $newKubikasi,
        ]);

        // 3. Hapus dari antrean tampilan
        unset($this->antreanMasuk[$itemIndex]);
        $this->antreanMasuk = array_values($this->antreanMasuk);

        // Kirim Notifikasi Sukses Bawaan Filament
        Notification::make()
            ->success()
            ->title('Penerimaan Sukses')
            ->body("Berhasil memverifikasi {$item['jumlah']} lbr Veneer {$item['panjang']}x{$item['lebar']}x{$item['tebal']} mm ke Gudang Jadi.")
            ->send();
    }

    /**
     * Helper memisahkan stok utama dari Database ke Faceback & Core dengan live-search
     */
    public function getSplitStokProperty(): array
    {
        $allStok = GudangModel::with('jenisKayu')
            ->get()
            ->filter(function ($item) {
                if (empty($this->searchQuery)) return true;
                $q = strtolower($this->searchQuery);

                $namaKayu = $item->jenisKayu ? strtolower($item->jenisKayu->nama) : '';
                return str_contains($namaKayu, $q) ||
                    str_contains(strtolower($item->kw_grade), $q) ||
                    str_contains(strtolower("{$item->panjang}x{$item->lebar}x{$item->tebal}"), $q);
            });

        $faceback = $allStok->filter(fn($item) => $item->tebal < 1.0);
        $core = $allStok->filter(fn($item) => $item->tebal >= 1.0);

        return [
            'faceback' => $faceback,
            'core' => $core,
        ];
    }

    /**
     * Helper menyaring antrean masuk (Section 2) dengan live-search
     */
    public function getAntreanFilteredProperty(): Collection
    {
        return collect($this->antreanMasuk)
            ->filter(function ($item) {
                if (empty($this->tableSearchQuery)) return true;
                $q = strtolower($this->tableSearchQuery);

                return str_contains(strtolower($item['jenis_kayu']), $q) ||
                    str_contains(strtolower($item['kw']), $q) ||
                    str_contains(strtolower("{$item['panjang']}x{$item['lebar']}x{$item['tebal']}"), $q);
            });
    }
}
