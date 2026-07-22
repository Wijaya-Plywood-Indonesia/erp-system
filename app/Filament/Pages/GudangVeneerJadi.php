<?php

namespace App\Filament\Pages;

use App\Models\GudangVeneerJadi as GudangModel;
use App\Models\HasilPilihVeneer;
use App\Models\HppVeneerJadiLog;
use App\Models\SerahTerimaVeneerKering;
use App\Models\StokVeneerJadi;
use App\Models\VeneerJadiMutasiKeluar;
use App\Models\VeneerJadiMutasiKeluarPalet;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use App\Services\SerahTerimaVeneerJadiService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GudangVeneerJadi extends Page
{
    use HasPageShield;

    // Icon menu navigasi di sidebar Filament
    // Tab aktif untuk section Serah Terima Dryer/Kedi/Joint: 'aktif' / 'history'
    public string $serahTerimaTab = 'aktif';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $title = 'Gudang Veneer Jadi';

    protected string $view = 'filament.pages.gudang-veneer-jadi';

    protected static string|UnitEnum|null $navigationGroup = 'Gudang';

    public string $activeTab = 'masuk';

    public string $searchQuery = '';

    public string $tableSearchQuery = '';

    public string $keluarSearchQuery = '';

    // Properti untuk modal konfirmasi
    public bool $showConfirmModal = false;

    // 🌟 Sekarang menyimpan composite id, contoh: "gudang-5" atau "mutasi-12"
    public ?string $selectedItemId = null;

    // Modal Form Keluar Barang
    public bool $showFormKeluarModal = false;

    public ?int $selectedStokId = null;

    // 🌟 PERBAIKAN: Diubah menjadi tipe data dinamis agar bisa menampung string kosong saat diedit
    public $jumlahPalet = 1;

    public array $paletQuantities = [0 => '']; // Input dinamis lembar per palet

    // Daftar tujuan pengeluaran veneer jadi. Tambah opsi baru di sini kalau perlu.
    public string $tujuanKeluar = 'Hotpress';

    public array $daftarTujuanKeluar = ['Hotpress', 'Repair', 'Joint', 'Jual'];

    public string $keteranganKeluar = '';

    public ?int $idProduksiHp = null;

    protected $queryString = ['activeTab'];

    // Kebutuhan Edit
    public bool $showEditKeluarModal = false;

    public ?int $editKeluarId = null;

    public $editJumlahPalet = 1;

    public array $editPaletQuantities = [0 => ''];

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        $lembarAman = $lembar ?? 0;

        return ($p * $l * $t * $lembarAman) / 10000000;
    }

    /**
     * Cek apakah satu baris mutasi keluar masih boleh diedit.
     * Terkunci begitu sudah diterima di sisi Hotpress ATAU sisi Repair.
     */
    protected function mutasiKeluarBisaDiedit(VeneerJadiMutasiKeluar $mutasi): bool
    {
        return is_null($mutasi->id_produksi_hp) && is_null($mutasi->id_produksi_repair);
    }

    public function editKeluar(int $id): void
    {
        $mutasi = VeneerJadiMutasiKeluar::with('palets')->find($id);

        if (! $mutasi) {
            Notification::make()->danger()->title('Data tidak ditemukan')->send();

            return;
        }

        if (! $this->mutasiKeluarBisaDiedit($mutasi)) {
            Notification::make()
                ->danger()
                ->title('Tidak Bisa Diedit')
                ->body('Barang ini sudah diterima di sisi tujuan (Hotpress/Repair), rincian tidak bisa diubah lagi.')
                ->send();

            return;
        }

        $this->editKeluarId = $mutasi->id;

        $palet = $mutasi->palets->sortBy('nomor_palet')->pluck('jumlah_lembar')->values()->toArray();
        $this->editPaletQuantities = ! empty($palet) ? $palet : [0 => ''];
        $this->editJumlahPalet = count($this->editPaletQuantities);

        $this->showEditKeluarModal = true;
    }

    public function cancelEditKeluar(): void
    {
        $this->showEditKeluarModal = false;
        $this->editKeluarId = null;
    }

    /**
     * Sinkronisasi dinamis jumlah palet di form EDIT — sama polanya
     * dengan updatedJumlahPalet(), tapi khusus array editPaletQuantities.
     */
    public function updatedEditJumlahPalet($value): void
    {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            return;
        }

        $count = max(1, intval($value));
        $this->editPaletQuantities = array_slice($this->editPaletQuantities, 0, $count);

        while (count($this->editPaletQuantities) < $count) {
            $this->editPaletQuantities[] = '';
        }
    }

    public function hapusEditPalet(int $index): void
    {
        if (isset($this->editPaletQuantities[$index])) {
            unset($this->editPaletQuantities[$index]);
            $this->editPaletQuantities = array_values($this->editPaletQuantities);
            $this->editJumlahPalet = count($this->editPaletQuantities);
        }
    }

    /**
     * 💾 SIMPAN PERUBAHAN RINCIAN KELUAR
     * Baris palet lama dihapus lalu dibuat ulang — lebih sederhana dan aman
     * daripada mencocokkan baris satu per satu, karena jumlah palet bisa
     * bertambah/berkurang saat diedit.
     */
    public function updateKeluar(): void
    {
        if (! $this->editKeluarId) {
            return;
        }

        $totalLembar = array_sum(array_map('intval', $this->editPaletQuantities));

        if ($totalLembar <= 0) {
            Notification::make()
                ->danger()
                ->title('Input Gagal')
                ->body('Kuantitas palet wajib diisi.')
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                $mutasi = VeneerJadiMutasiKeluar::where('id', $this->editKeluarId)->lockForUpdate()->first();

                if (! $mutasi) {
                    throw new \Exception('Data tidak ditemukan.');
                }

                if (! $this->mutasiKeluarBisaDiedit($mutasi)) {
                    throw new \Exception('Barang ini sudah diterima di sisi tujuan, tidak bisa diedit lagi.');
                }

                $stok = StokVeneerJadi::where('id_jenis_kayu', $mutasi->id_jenis_kayu)
                    ->where('panjang', $mutasi->panjang)
                    ->where('lebar', $mutasi->lebar)
                    ->where('tebal', $mutasi->tebal)
                    ->where('kw_grade', $mutasi->kw_grade)
                    ->lockForUpdate()
                    ->first();

                if (! $stok || $totalLembar > $stok->stok_lembar) {
                    throw new \Exception('Sisa stok fisik di gudang tidak mencukupi untuk kuantitas baru.');
                }

                $mutasi->update([
                    'jumlah_palet' => count($this->editPaletQuantities),
                    'stok_lembar' => $totalLembar,
                    'stok_kubikasi' => $this->hitungKubikasi($mutasi->panjang, $mutasi->lebar, $mutasi->tebal, $totalLembar),
                ]);

                // 🆕 Hapus dulu baris antrean 'gudang_jadi' punya palet-palet lama
                // milik mutasi ini, SEBELUM palet lama dihapus (supaya FK
                // id_mutasi_keluar_palet_jadi masih valid saat query where-in).
                // Aman dihapus karena mutasiKeluarBisaDiedit() sudah memastikan
                // belum ada yang diterima (id_produksi_repair masih null).
                $idPaletLama = $mutasi->palets()->pluck('id');
                SerahTerimaVeneerKering::where('tipe_sumber', 'gudang_jadi')
                    ->whereIn('id_mutasi_keluar_palet_jadi', $idPaletLama)
                    ->delete();

                $mutasi->palets()->delete();

                $userName = Auth::user()?->name ?? 'System';

                foreach ($this->editPaletQuantities as $index => $qty) {
                    $qtyPalet = intval($qty);
                    if ($qtyPalet <= 0) {
                        continue;
                    }

                    $palet = VeneerJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet' => $index + 1,
                        'jumlah_lembar' => $qtyPalet,
                    ]);

                    // 🆕 Repair & Joint sama-sama butuh antrean Serah Terima,
                    // dibedakan lewat kolom 'tujuan' (diambil dari header mutasi
                    // yang sudah tersimpan, bukan dari properti form Livewire,
                    // karena form "keluar" & "edit keluar" beda properti).
                    if (in_array($mutasi->tujuan, ['Repair', 'Joint'], true)) {
                        SerahTerimaVeneerKering::create([
                            'id_mutasi_keluar_palet_jadi' => $palet->id,
                            'tipe_sumber' => 'gudang_jadi',
                            'diserahkan_oleh' => $userName,
                            'diterima_oleh' => '-',
                            'jenis_terima' => 'jadi',
                            'status' => 'Serah Veneer',
                            'tujuan' => strtolower($mutasi->tujuan), // 'repair' | 'joint'
                        ]);
                    }
                }
            });

            unset($this->riwayatKeluarFiltered);

            $this->showEditKeluarModal = false;
            $this->editKeluarId = null;

            Notification::make()
                ->success()
                ->title('✓ Rincian Diperbarui')
                ->body("Rincian palet berhasil diubah menjadi {$totalLembar} lembar.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Memperbarui')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * ✅ BUKA MODAL KONFIRMASI
     *
     * $compositeId formatnya "{source}-{id_asli}", misal "gudang-5" atau
     * "mutasi-12". Ini WAJIB dipakai (bukan id polos) karena kita menggabung
     * dua tabel berbeda yang ruang id-nya terpisah — id 5 di GudangVeneerJadi
     * dan id 5 di VeneerMutasiDetail adalah dua baris yang sama sekali beda.
     */
    public function confirmTerima(string $compositeId): void
    {
        $this->selectedItemId = $compositeId;
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
     *
     * Percabangan berdasarkan sumber data:
     *  - "gudang-{id}" -> logika lama, dari tabel GudangVeneerJadi (Produksi/Repair).
     *  - "mutasi-{id}" -> baru, dari VeneerMutasiDetail (alur Barang Masuk),
     *                     didelegasikan ke SerahTerimaVeneerJadiService.
     */
    public function terimaBarang(): void
    {
        if (! $this->selectedItemId) {
            return;
        }

        [$source, $rawId] = array_pad(explode('-', $this->selectedItemId, 2), 2, null);

        if ($source === 'mutasi') {
            $this->terimaDariMutasi((int) $rawId);
        } elseif ($source === 'pilih') {
            $this->terimaDariPilihVeneer((int) $rawId);
        } else {
            // Default ke perilaku lama supaya backward-compatible kalau
            // suatu saat composite id belum sempat dipakai di tempat lain.
            $this->terimaDariGudang((int) $rawId);
        }

        $this->showConfirmModal = false;
        $this->selectedItemId = null;
        $this->dispatch('$refresh');
    }

    /**
     * Terima barang dari tabel GudangVeneerJadi (alur Produksi/Repair).
     * Ini adalah logika ASLI yang sudah ada sebelumnya — tidak diubah,
     * cuma dipindah ke method sendiri.
     */
    protected function terimaDariGudang(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $record = GudangModel::where('id', $id)->lockForUpdate()->first();

                if (! $record) {
                    throw new \Exception('Data tidak ditemukan.');
                }

                if ($record->status_gudang === 'sudah diterima') {
                    throw new \Exception('Barang ini sudah pernah diterima sebelumnya.');
                }

                $user = Auth::user();
                $userName = $user?->name ?? 'System';

                $stokInduk = StokVeneerJadi::where('id_jenis_kayu', $record->id_jenis_kayu)
                    ->where('panjang', $record->panjang)
                    ->where('lebar', $record->lebar)
                    ->where('tebal', $record->tebal)
                    ->where('kw_grade', $record->kw_grade)
                    ->lockForUpdate()
                    ->first();

                if (! $stokInduk) {
                    $stokInduk = StokVeneerJadi::create([
                        'id_jenis_kayu' => $record->id_jenis_kayu,
                        'panjang' => $record->panjang,
                        'lebar' => $record->lebar,
                        'tebal' => $record->tebal,
                        'kw_grade' => $record->kw_grade,
                        'stok_lembar' => 0,
                        'stok_kubikasi' => 0,
                        'nilai_stok' => 0,
                        'hpp_average' => 0,
                        'hpp_pekerja_last' => 0,
                        'hpp_bahan_penolong_last' => 0,
                        'id_last_log' => null,
                    ]);
                }

                $stokLembarBefore = $stokInduk->stok_lembar;
                $stokKubikasiBefore = $stokInduk->stok_kubikasi;
                $nilaiStokBefore = $stokInduk->nilai_stok;

                $stokLembarAfter = $stokLembarBefore + $record->stok_lembar;
                $stokKubikasiAfter = $stokKubikasiBefore + $record->stok_kubikasi;
                $nilaiStokAfter = $nilaiStokBefore + $record->nilai_stok;
                $hppAverageAfter = $stokLembarAfter > 0
                    ? ($nilaiStokAfter / $stokLembarAfter)
                    : 0;

                $log = HppVeneerJadiLog::create([
                    'id_jenis_kayu' => $record->id_jenis_kayu,
                    'panjang' => $record->panjang,
                    'lebar' => $record->lebar,
                    'tebal' => $record->tebal,
                    'kw_grade' => $record->kw_grade,
                    'tanggal' => now(),
                    'tipe_transaksi' => 'MASUK',
                    'referensi_type' => GudangModel::class,
                    'referensi_id' => $record->id,
                    'total_lembar' => $record->stok_lembar,
                    'total_kubikasi' => $record->stok_kubikasi,
                    'hpp_pekerja' => $record->hpp_pekerja_last ?? 0,
                    'hpp_bahan_penolong' => $record->hpp_bahan_penolong_last ?? 0,
                    'hpp_average' => $hppAverageAfter,
                    'nilai_stok' => $record->nilai_stok,
                    'stok_lembar_before' => $stokLembarBefore,
                    'stok_kubikasi_before' => $stokKubikasiBefore,
                    'nilai_stok_before' => $nilaiStokBefore,
                    'stok_lembar_after' => $stokLembarAfter,
                    'stok_kubikasi_after' => $stokKubikasiAfter,
                    'nilai_stok_after' => $nilaiStokAfter,
                    'keterangan' => sprintf(
                        '%s, diterima oleh: %s pada %s',
                        $record->keterangan ?? 'Produksi Repair',
                        $userName,
                        now()->translatedFormat('d F Y H:i')
                    ),
                ]);

                $stokInduk->update([
                    'stok_lembar' => $stokLembarAfter,
                    'stok_kubikasi' => $stokKubikasiAfter,
                    'nilai_stok' => $nilaiStokAfter,
                    'hpp_average' => $hppAverageAfter,
                    'id_last_log' => $log->id,
                ]);

                $record->update([
                    'status_gudang' => 'sudah diterima',
                    'diterima_by' => $user?->id,
                    'diterima_at' => now(),
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
    }

    /**
     * Terima barang dari VeneerMutasiDetail (alur Barang Masuk / Nota BM).
     * Cukup delegasikan ke service — semua logika hitung sudah ada di sana.
     */
    protected function terimaDariMutasi(int $detailId): void
    {
        try {
            $detail = VeneerMutasiDetail::findOrFail($detailId);

            app(SerahTerimaVeneerJadiService::class)->terimaSatuDetail($detail, (int) Auth::id());

            Notification::make()
                ->success()
                ->title('Sukses Diterima!')
                ->body('Barang dari Nota Barang Masuk resmi masuk gudang dan stok telah diperbarui.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Menerima Barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * 📦 STOK UTAMA (Sudah di StokVeneerJadi)
     */
    public function getSplitStokProperty(): array
    {
        $allStok = StokVeneerJadi::with(['jenisKayu', 'lastLog'])
            ->get()
            ->filter(function ($item) {
                if (empty($this->searchQuery)) {
                    return true;
                }
                $q = strtolower($this->searchQuery);
                $namaKayu = $item->jenisKayu ? strtolower($item->jenisKayu->nama_kayu) : '';

                return str_contains($namaKayu, $q) ||
                    str_contains(strtolower($item->kw_grade), $q) ||
                    str_contains(strtolower(($item->panjang + 0).'x'.($item->lebar + 0).'x'.($item->tebal + 0)), $q);
            });

        return [
            'faceback' => $allStok->filter(fn ($item) => $item->tebal < 1.0),
            'core' => $allStok->filter(fn ($item) => $item->tebal >= 1.0),
        ];
    }

    /**
     * 📥 ANTREAN GABUNGAN: GudangVeneerJadi (Produksi/Repair) + VeneerMutasiDetail
     * (Barang Masuk yang sudah divalidasi tapi belum "Diterima").
     *
     * Setiap baris hasil diberi:
     *  - id          => composite id "{source}-{id_asli}", dipakai confirmTerima()
     *  - source      => 'gudang' | 'mutasi', dipakai terimaBarang() untuk branching
     * Field lain (jenis_kayu, jumlah, dst) dibuat SERAGAM di kedua sumber supaya
     * view Blade tidak perlu tahu bedanya sama sekali.
     */
    public function getAntreanFilteredProperty(): Collection
    {
        $dariGudang = $this->ambilAntreanDariGudang();
        $dariPilihVeneer = $this->ambilAntreanDariPilihVeneer();
        // 🔒 SEMENTARA DINONAKTIFKAN: sub-tab "Terima dari BM" (sumber mutasi)
        // dihilangkan dari tampilan atas permintaan — hanya antrean Produksi/Repair
        // yang ditampilkan. Method ambilAntreanDariMutasiJadi() & terimaDariMutasi()
        // sengaja TIDAK dihapus (masih ada di bawah), supaya mudah diaktifkan lagi
        // kalau fitur ini dibutuhkan kembali nanti.
        // $dariMutasi = $this->ambilAntreanDariMutasiJadi();

        return $dariGudang
            // ->concat($dariMutasi)
            ->concat($dariPilihVeneer)
            ->sortBy([
                fn ($item) => $item['status_gudang'] === 'belum diterima' ? 0 : 1,
                fn ($item) => -$item['created_at_ts'],
                fn ($item) => $item['id'],
            ])
            ->values();
    }

    /**
     * Sumber 1: tabel GudangVeneerJadi (Produksi/Repair) — logika ASLI,
     * cuma ditambah 'id' composite dan 'source'.
     */
    protected function ambilAntreanDariGudang(): Collection
    {
        $query = GudangModel::with(['jenisKayu', 'penerima'])
            ->select([
                'gudang_veneer_jadis.*',
                'jenis_kayus.nama_kayu as jenis_kayu_nama',
            ])
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'gudang_veneer_jadis.id_jenis_kayu');

        if (! empty($this->tableSearchQuery)) {
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

        // Sumber tunggal: GudangVeneerJadi (Produksi/Repair)
        return $query->get()->map(fn ($item) => [
            'id' => 'gudang-'.$item->id,
            'source' => 'gudang',
            'jenis_kayu' => $item->jenis_kayu_nama,
            'panjang' => $item->panjang,
            'lebar' => $item->lebar,
            'tebal' => $item->tebal,
            'kw' => $item->kw_grade,
            'jumlah' => $item->stok_lembar,
            'stok_kubikasi' => $item->stok_kubikasi,
            'created_at' => $item->created_at,
            'created_at_ts' => $item->created_at?->timestamp ?? 0,
            'status_gudang' => $item->status_gudang ?? 'belum diterima',
            'diterima_at' => $item->diterima_at,
            'diterima_by' => $item->diterima_by,
            'penerima_name' => $item->penerima?->name ?? 'N/A',
            'keterangan' => $item->keterangan,
            'sumber_label' => $item->id_produksi_repair ? 'Repair' : 'Produksi',
        ])
            ->sortBy([
                fn ($item) => $item['status_gudang'] === 'belum diterima' ? 0 : 1,
                fn ($item) => -$item['created_at_ts'],
            ])
            ->values();
    }

    /**
     * Sumber 2: VeneerMutasi (arah masuk) yang punya detail bertipe 'jadi',
     * dari nota yang SUDAH divalidasi.
     *
     * Struktur query di sini SENGAJA ditulis top-down (mulai dari VeneerMutasi,
     * bukan dari VeneerMutasiDetail) meniru pola yang sudah terbukti jalan di
     * GudangVeneerKering::getSerahTerimaProperty() — termasuk nama relasi
     * `notaBm` dan `details` yang sudah terverifikasi dari kode itu.
     *
     * BEDA dengan versi Kering: status "sudah diterima" TIDAK saya ambil dari
     * method $vm->sudahDiterima() (karena isi method itu kemungkinan spesifik
     * untuk kering — mengecek relasi gudangKering — dan bisa salah kalau
     * dipakai untuk jadi). Untuk jadi, sumber kebenaran status tetap
     * HppVeneerJadiLog (tipe_transaksi = 'masuk'), sama seperti yang dipakai
     * SerahTerimaVeneerJadiService sejak awal.
     *
     * Optimisasi: pengecekan "sudah diterima / belum" dilakukan SEKALI lewat
     * whereIn atas semua id detail, bukan whereNotExists per baris — supaya
     * tidak jadi query database berulang kalau baris detail-nya banyak.
     */
    protected function ambilAntreanDariMutasiJadi(): Collection
    {
        $jadiLike = SerahTerimaVeneerJadiService::TIPE_JADI_LIKE;

        $mutasiRows = VeneerMutasi::query()
            ->whereNotNull('id_nota_bm') // proxy "arah masuk", sama seperti versi Kering
            ->whereHas('notaBm', fn ($q) => $q->whereNotNull('divalidasi_oleh'))
            ->whereHas('details', fn ($q) => $q->where('tipe_veneer', 'like', $jadiLike))
            ->with([
                'details' => fn ($q) => $q
                    ->where('tipe_veneer', 'like', $jadiLike)
                    ->with(['ukuran', 'jenisKayu']),
            ])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        // Kumpulkan semua id detail dari semua mutasi di atas, lalu cek SEKALI
        // mana saja yang sudah punya log 'masuk' (artinya: sudah "Diterima").
        $semuaDetailIds = $mutasiRows->flatMap(fn ($vm) => $vm->details->pluck('id'));

        $sudahDiterimaIds = HppVeneerJadiLog::query()
            ->where('referensi_type', VeneerMutasiDetail::class)
            ->whereIn('referensi_id', $semuaDetailIds)
            ->where('tipe_transaksi', 'masuk')
            ->pluck('referensi_id')
            ->flip(); // supaya isset($sudahDiterimaIds[$id]) O(1), bukan in_array O(n)

        $hasil = collect();

        foreach ($mutasiRows as $mutasi) {
            foreach ($mutasi->details as $detail) {
                $ukuran = $detail->ukuran;
                $sudahTerima = isset($sudahDiterimaIds[$detail->id]);

                // Filter search dilakukan manual di sini (bukan di query SQL)
                // karena kita sudah eager-load semuanya sekaligus per mutasi.
                if (! empty($this->tableSearchQuery)) {
                    $q = strtolower($this->tableSearchQuery);
                    $kayu = strtolower((string) $detail->jenisKayu?->nama_kayu);
                    $kw = strtolower((string) $detail->kw);
                    if (! str_contains($kayu, $q) && ! str_contains($kw, $q)) {
                        continue;
                    }
                }

                $waktuNota = $mutasi->created_at ?? $mutasi->tanggal ?? now();
                $timestamp = $waktuNota instanceof Carbon ? $waktuNota->timestamp : strtotime($waktuNota);

                $hasil->push([
                    'id' => 'mutasi-'.$detail->id,
                    'source' => 'mutasi',
                    'jenis_kayu' => $detail->jenisKayu?->nama_kayu,
                    'panjang' => $ukuran?->panjang,
                    'lebar' => $ukuran?->lebar,
                    'tebal' => $ukuran?->tebal,
                    'kw' => $detail->kw,
                    'jumlah' => $detail->qty,
                    'stok_kubikasi' => $detail->m3,
                    'created_at' => $waktuNota,
                    'created_at_ts' => $timestamp,
                    'status_gudang' => $sudahTerima ? 'sudah diterima' : 'belum diterima',
                    'diterima_at' => null,
                    'diterima_by' => null,
                    'penerima_name' => 'N/A',
                    'keterangan' => 'No Nota: '.($mutasi->no_nota ?? '-'),
                ]);
            }
        }

        return $hasil;
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

        if (! $this->selectedStokId || $totalLembar <= 0 || empty($this->tujuanKeluar)) {
            Notification::make()
                ->danger()
                ->title('Input Gagal')
                ->body('Spesifikasi stok, kuantitas palet, dan tujuan pengeluaran wajib diisi.')
                ->send();

            return;
        }

        if (! in_array($this->tujuanKeluar, $this->daftarTujuanKeluar, true)) {
            Notification::make()
                ->danger()
                ->title('Input Gagal')
                ->body('Tujuan pengeluaran tidak valid.')
                ->send();

            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                $stok = StokVeneerJadi::where('id', $this->selectedStokId)->lockForUpdate()->first();

                if (! $stok || $totalLembar > $stok->stok_lembar) {
                    throw new \Exception('Sisa stok fisik di gudang tidak mencukupi.');
                }

                $userName = Auth::user()?->name ?? 'System';

                // Header tetap dibuat sekali — tidak berubah dari sebelumnya
                $mutasi = VeneerJadiMutasiKeluar::create([
                    'id_jenis_kayu' => $stok->id_jenis_kayu,
                    'panjang' => $stok->panjang,
                    'lebar' => $stok->lebar,
                    'tebal' => $stok->tebal,
                    'kw_grade' => $stok->kw_grade,
                    'jumlah_palet' => count($this->paletQuantities),
                    'stok_lembar' => $totalLembar,
                    'stok_kubikasi' => $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $totalLembar),
                    'tujuan' => $this->tujuanKeluar,
                    'dikeluarkan_by' => Auth::id(),
                    'keterangan' => $this->keteranganKeluar,
                    'id_produksi_hp' => null,
                    'id_produksi_repair' => null,
                ]);

                // Catatan: stok & HppVeneerJadiLog TIDAK dibuat di sini lagi.
                // Baris palet di bawah cuma mencatat "niat kirim" per palet.
                foreach ($this->paletQuantities as $index => $qty) {
                    $qtyPalet = intval($qty);
                    if ($qtyPalet <= 0) {
                        continue;
                    }

                    $palet = VeneerJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet' => $index + 1,
                        'jumlah_lembar' => $qtyPalet,
                    ]);

                    // 🆕 Repair & Joint sama-sama butuh antrean "Serah Terima
                    // Veneer" per palet, tipe_sumber = 'gudang_jadi'. Yang
                    // membedakan tujuan akhirnya cukup kolom 'tujuan'.
                    if (in_array($this->tujuanKeluar, ['Repair', 'Joint'], true)) {
                        SerahTerimaVeneerKering::create([
                            'id_mutasi_keluar_palet_jadi' => $palet->id,
                            'tipe_sumber' => 'gudang_jadi',
                            'diserahkan_oleh' => $userName,
                            'diterima_oleh' => '-',
                            'jenis_terima' => 'jadi',
                            'status' => 'Serah Veneer',
                            'tujuan' => strtolower($this->tujuanKeluar), // 'repair' | 'joint'
                        ]);
                    }
                }
            });

            unset($this->splitStok);
            unset($this->riwayatKeluarFiltered);

            // Reset Form ke keadaan bersih
            $this->selectedStokId = null;
            $this->jumlahPalet = 1;
            $this->paletQuantities = [0 => ''];
            $this->tujuanKeluar = 'Hotpress';
            $this->keteranganKeluar = '';
            $this->showFormKeluarModal = false;

            Notification::make()
                ->success()
                ->title('✓ Mutasi Keluar Dicatat')
                ->body("Sebanyak {$totalLembar} lembar veneer tercatat dikirim. Stok akan terpotong setelah dikonfirmasi diterima.")
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

        if (! empty($this->keluarSearchQuery)) {
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

        return $query->get()->map(fn ($item) => [
            'id' => $item->id,
            'bisa_diedit' => $this->mutasiKeluarBisaDiedit($item),
            'created_at' => $item->created_at->format('d/m/Y H:i'),
            'jenis_kayu' => $item->jenisKayu->nama_kayu ?? '-',
            'panjang' => $item->panjang,
            'lebar' => $item->lebar,
            'tebal' => $item->tebal,
            'kw' => $item->kw_grade,
            'stok_lembar' => $item->stok_lembar ?? 0,
            'stok_kubikasi' => $item->stok_kubikasi ?? 0,
            'jumlah_palet' => $item->jumlah_palet,
            'rincian_palet' => $item->palets->pluck('jumlah_lembar')->toArray(),
            'tujuan' => $item->tujuan,
            'dikeluarkan_by' => $item->operator->name ?? 'System',
            'keterangan' => $item->keterangan,
        ]);
    }

    // ─── SERAH TERIMA (DARI DRYER / KEDI / JOINT) — TUJUAN VENEER JADI ─────

    /**
     * Daftar antrean SerahTerimaVeneerKering yang berasal dari Press Dryer,
     * Kedi, atau Joint, dengan jenis_terima = 'jadi', dan BELUM diterima
     * siapa pun. Ditampilkan di tab "Serah Terima" halaman Gudang Veneer
     * Jadi, supaya admin gudang bisa langsung menerima hasil dryer/kedi/
     * joint ke stok Veneer Jadi TANPA perlu lewat halaman Produksi Repair.
     *
     * ⚠️ Relasi joint = hasilJoint (tabel hasil_joint), BUKAN
     * hasilSandingJoint. Sanding Joint tidak melewati alur serah terima ini.
     */
    public function getSerahTerimaProperty(): Collection
    {
        return SerahTerimaVeneerKering::query()
            ->whereIn('tipe_sumber', ['dryer', 'kedi', 'joint'])
            ->where('jenis_terima', 'jadi')
            ->where('diterima_oleh', '-')
            ->where(function ($q) {
                $q->whereNotNull('id_detail_hasil')
                    ->orWhereNotNull('id_detail_bongkar_kedi')
                    ->orWhereNotNull('id_hasil_joint');
            })
            ->with([
                'detailHasil.ukuran',
                'detailHasil.jenisKayu',
                'detailBongkarKedi.ukuran',
                'detailBongkarKedi.jenisKayu',
                'hasilJoint.ukuran',
                'hasilJoint.jenisKayu',
            ])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Riwayat veneer dari Dryer/Kedi/Joint (jenis_terima = 'jadi')
     * yang SUDAH diterima ke Gudang Veneer Jadi.
     */
    public function getRiwayatSerahTerimaProperty(): Collection
    {
        return SerahTerimaVeneerKering::query()
            ->whereIn('tipe_sumber', ['dryer', 'kedi', 'joint'])
            ->where('jenis_terima', 'jadi')
            ->where('diterima_oleh', '!=', '-')
            ->where(function ($q) {
                $q->whereNotNull('id_detail_hasil')
                    ->orWhereNotNull('id_detail_bongkar_kedi')
                    ->orWhereNotNull('id_hasil_joint');
            })
            ->with([
                'detailHasil.ukuran',
                'detailHasil.jenisKayu',
                'detailBongkarKedi.ukuran',
                'detailBongkarKedi.jenisKayu',
                'hasilJoint.ukuran',
                'hasilJoint.jenisKayu',
            ])
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Terima satu baris antrean dryer/kedi/joint (jenis_terima =
     * 'jadi') langsung ke stok Gudang Veneer Jadi. HPP belum dihitung (0
     * dulu, sama seperti alur terima triplek/platform di
     * SerahTerimaHpRelationManager).
     */
    public function terimaDryer(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $fresh = SerahTerimaVeneerKering::lockForUpdate()->findOrFail($id);

                if ($fresh->diterima_oleh !== '-') {
                    throw new \RuntimeException('Veneer ini sudah diterima sebelumnya.');
                }

                if (! in_array($fresh->tipe_sumber, ['dryer', 'kedi', 'joint'], true)) {
                    throw new \RuntimeException('Sumber veneer tidak valid untuk diterima di Gudang Veneer Jadi.');
                }

                if ($fresh->jenis_terima !== 'jadi') {
                    throw new \RuntimeException('Barang ini bukan veneer jadi, tidak bisa diterima di sini.');
                }

                // ✅ FIX: 'joint' HARUS memakai relasi hasilJoint (tabel
                // hasil_joint). Sebelumnya memakai hasilSandingJoint yang
                // menunjuk tabel hasil_sanding_joint, sehingga id 147 dari
                // hasil_joint dibaca sebagai baris 147 di hasil_sanding_joint
                // → jenis kayu, ukuran, kw, dan jumlah lembar semuanya salah.
                $sumber = match ($fresh->tipe_sumber) {
                    'dryer' => $fresh->detailHasil,
                    'kedi' => $fresh->detailBongkarKedi,
                    'joint' => $fresh->hasilJoint,
                    default => null,
                };

                if (! $sumber || ! $sumber->ukuran || ! $sumber->jenisKayu) {
                    throw new \RuntimeException('Data ukuran atau jenis kayu tidak lengkap.');
                }

                $ukuran = $sumber->ukuran;
                $jenisKayu = $sumber->jenisKayu;
                $kw = (string) $sumber->kw;
                // dryer pakai kolom "isi", kedi & joint pakai "jumlah"
                $lembar = (float) ($fresh->tipe_sumber === 'dryer' ? $sumber->isi : $sumber->jumlah);

                if ($lembar <= 0) {
                    throw new \RuntimeException('Jumlah lembar sumber kosong atau tidak valid.');
                }

                $user = Auth::user();
                $userName = $user?->name ?? 'System';

                $stokInduk = StokVeneerJadi::where('id_jenis_kayu', $jenisKayu->id)
                    ->where('panjang', $ukuran->panjang)
                    ->where('lebar', $ukuran->lebar)
                    ->where('tebal', $ukuran->tebal)
                    ->where('kw_grade', $kw)
                    ->lockForUpdate()
                    ->first();

                if (! $stokInduk) {
                    $stokInduk = StokVeneerJadi::create([
                        'id_jenis_kayu' => $jenisKayu->id,
                        'panjang' => $ukuran->panjang,
                        'lebar' => $ukuran->lebar,
                        'tebal' => $ukuran->tebal,
                        'kw_grade' => $kw,
                        'stok_lembar' => 0,
                        'stok_kubikasi' => 0,
                        'nilai_stok' => 0,
                        'hpp_average' => 0,
                        'hpp_pekerja_last' => 0,
                        'hpp_bahan_penolong_last' => 0,
                        'id_last_log' => null,
                    ]);
                }

                $kubikasiMasuk = $this->hitungKubikasi($ukuran->panjang, $ukuran->lebar, $ukuran->tebal, (int) $lembar);

                $stokLembarBefore = $stokInduk->stok_lembar;
                $stokKubikasiBefore = $stokInduk->stok_kubikasi;
                $nilaiStokBefore = $stokInduk->nilai_stok;

                $stokLembarAfter = $stokLembarBefore + $lembar;
                $stokKubikasiAfter = $stokKubikasiBefore + $kubikasiMasuk;
                $nilaiStokAfter = $nilaiStokBefore; // HPP belum dihitung, masih 0
                $hppAverageAfter = $stokLembarAfter > 0
                    ? ($nilaiStokAfter / $stokLembarAfter)
                    : 0;

                $labelSumber = match ($fresh->tipe_sumber) {
                    'dryer' => 'Press Dryer',
                    'kedi' => 'Kedi',
                    'joint' => 'Joint',
                    default => '-',
                };

                $log = HppVeneerJadiLog::create([
                    'id_jenis_kayu' => $jenisKayu->id,
                    'panjang' => $ukuran->panjang,
                    'lebar' => $ukuran->lebar,
                    'tebal' => $ukuran->tebal,
                    'kw_grade' => $kw,
                    'tanggal' => now(),
                    'tipe_transaksi' => 'MASUK',
                    'referensi_type' => SerahTerimaVeneerKering::class,
                    'referensi_id' => $fresh->id,
                    'total_lembar' => $lembar,
                    'total_kubikasi' => $kubikasiMasuk,
                    'hpp_pekerja' => 0,
                    'hpp_bahan_penolong' => 0,
                    'hpp_average' => $hppAverageAfter,
                    'nilai_stok' => 0,
                    'stok_lembar_before' => $stokLembarBefore,
                    'stok_kubikasi_before' => $stokKubikasiBefore,
                    'nilai_stok_before' => $nilaiStokBefore,
                    'stok_lembar_after' => $stokLembarAfter,
                    'stok_kubikasi_after' => $stokKubikasiAfter,
                    'nilai_stok_after' => $nilaiStokAfter,
                    'keterangan' => sprintf(
                        'Terima veneer jadi dari %s, diserahkan oleh: %s, diterima oleh: %s pada %s',
                        $labelSumber,
                        $fresh->diserahkan_oleh,
                        $userName,
                        now()->translatedFormat('d F Y H:i')
                    ),
                ]);

                $stokInduk->update([
                    'stok_lembar' => $stokLembarAfter,
                    'stok_kubikasi' => $stokKubikasiAfter,
                    'nilai_stok' => $nilaiStokAfter,
                    'hpp_average' => $hppAverageAfter,
                    'id_last_log' => $log->id,
                ]);

                $fresh->update([
                    'diterima_oleh' => $userName.' - Gudang Veneer Jadi',
                    'status' => 'Terima Veneer',
                ]);
            });

            Notification::make()
                ->title('Veneer jadi berhasil diterima ke Gudang.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menerima Veneer')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // 🔧 TODO: TERIMA DI SISI REPAIR
    // ─────────────────────────────────────────────────────────────────
    // Method di bawah ini BELUM diimplementasikan karena alur "siapa yang
    // klik terima, dari halaman mana, dan kapan id_produksi_repair diisi"
    // belum ditentukan. Kerangkanya mengikuti pola terimaDariMutasi():
    //
    // protected function terimaDariRepair(int $idMutasiKeluar, int $idProduksiRepair): void
    // {
    //     DB::transaction(function () use ($idMutasiKeluar, $idProduksiRepair) {
    //         $mutasi = VeneerJadiMutasiKeluar::lockForUpdate()->findOrFail($idMutasiKeluar);
    //
    //         if (! is_null($mutasi->id_produksi_hp) || ! is_null($mutasi->id_produksi_repair)) {
    //             throw new \Exception('Barang ini sudah diterima sebelumnya.');
    //         }
    //
    //         // 1. Potong StokVeneerJadi sejumlah $mutasi->stok_lembar
    //         // 2. Catat HppVeneerJadiLog tipe_transaksi = 'KELUAR'
    //         // 3. $mutasi->update(['id_produksi_repair' => $idProduksiRepair]);
    //     });
    // }

    public function formatKodePalet($serahTerima): string
    {
        if (! $serahTerima) {
            return '-';
        }

        // 1. Jika sumber berasal dari Press Dryer
        if ($serahTerima->tipe_sumber === 'dryer' && $serahTerima->detailHasil) {
            return 'DRY-'.$serahTerima->detailHasil->no_palet;
        }

        // 2. Jika sumber berasal dari Bongkar Kedi
        if ($serahTerima->tipe_sumber === 'kedi' && $serahTerima->detailBongkarKedi) {
            return 'KD-'.$serahTerima->detailBongkarKedi->no_palet;
        }

        // 3. Jika sumber berasal dari Produksi Joint (tabel hasil_joint)
        if ($serahTerima->tipe_sumber === 'joint' && $serahTerima->hasilJoint) {
            return 'JNT-'.$serahTerima->hasilJoint->no_palet;
        }

        return '-';
    }

    // Untuk serah terima hasil pilih veneer
    protected function ambilAntreanDariPilihVeneer(): Collection
    {
        $hasilRows = HasilPilihVeneer::with(['modalPilihVeneer.stokVeneerJadi.jenisKayu'])
            ->whereNotNull('diserahkan_at')
            ->whereNull('diterima_gudang_at')
            ->get();

        return $hasilRows->map(function ($hasil) {
            $stokAsal = $hasil->modalPilihVeneer?->stokVeneerJadi;

            return [
                'id' => 'pilih-' . $hasil->id,
                'source' => 'pilih_veneer',
                'sumber_label' => 'Pilih Veneer',
                'jenis_kayu' => $stokAsal?->jenisKayu?->nama_kayu,
                'panjang' => $stokAsal?->panjang,
                'lebar' => $stokAsal?->lebar,
                'tebal' => $stokAsal?->tebal,
                'kw' => $hasil->kw, // KW HASIL, bukan KW modal
                'jumlah' => $hasil->jumlah,
                'stok_kubikasi' => $this->hitungKubikasi($stokAsal?->panjang ?? 0, $stokAsal?->lebar ?? 0, $stokAsal?->tebal ?? 0, $hasil->jumlah),
                'created_at' => $hasil->created_at,
                'created_at_ts' => $hasil->created_at?->timestamp ?? 0,
                'status_gudang' => 'belum diterima',
                'diterima_at' => null,
                'diterima_by' => null,
                'penerima_name' => 'N/A',
                'keterangan' => 'Pilih Veneer tanggal ' . $hasil->created_at?->translatedFormat('d F Y'),
            ];
        });
    }

    /**
     * Terima hasil pilih veneer ke Gudang.
     *
     * 🔑 PENTING: method ini SENGAJA tidak lagi menghitung/menulis mutasi
     * StokVeneerJadi atau HppVeneerJadiLog secara manual. Tugasnya cuma
     * validasi + tandai `diterima_gudang_at`. Begitu kolom itu ter-update,
     * event `saved` di HasilPilihVeneer::booted() otomatis mendeteksi
     * perubahan (null -> ada isi) dan menjalankan mutasi stok yang benar:
     *   - KW hasil SAMA dengan KW stok asal -> tidak ada mutasi stok.
     *   - KW hasil BEDA dari KW stok asal   -> pindahkan lembar dari baris
     *     stok KW asal ke baris stok KW hasil (dengan HPP rata-rata
     *     tertimbang di sisi tujuan).
     *
     * Kalau logika mutasi stok ditulis DI SINI JUGA, hasilnya dobel-mutasi
     * (baris ini menambah ke stok tujuan, DAN booted() juga menambah/
     * mengurangi) — itulah penyebab bug stok bertambah tidak wajar yang
     * pernah terjadi sebelumnya (modal 300 -> stok KW 3 & KW 2 sama-sama
     * jadi 450, padahal seharusnya berpindah, bukan digandakan).
     */
    protected function terimaDariPilihVeneer(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $hasil = HasilPilihVeneer::with('modalPilihVeneer.stokVeneerJadi')
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($hasil->diterima_gudang_at !== null) {
                    throw new \Exception('Hasil pilih veneer ini sudah diterima sebelumnya.');
                }

                if (! $hasil->modalPilihVeneer?->stokVeneerJadi) {
                    throw new \Exception('Data stok asal modal tidak lengkap.');
                }

                $user = Auth::user();

                // Update ini men-trigger event `saved` di HasilPilihVeneer,
                // yang lalu memanggil mutasiStokSaatDiterima() untuk
                // menghitung & mencatat perubahan stok yang sebenarnya.
                $hasil->update([
                    'diterima_gudang_at' => now(),
                    'diterima_gudang_by' => $user?->id,
                ]);
            });

            Notification::make()
                ->success()
                ->title('Sukses Diterima!')
                ->body('Hasil pilih veneer resmi masuk gudang.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Menerima Barang')
                ->body($e->getMessage())
                ->send();
        }
    }
}
