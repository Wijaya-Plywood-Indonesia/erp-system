<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Models\GudangVeneerJadi as GudangModel;
use App\Models\StokVeneerJadi;
use App\Models\HppVeneerJadiLog;
use App\Models\ProduksiHp;
use App\Models\VeneerJadiMutasiKeluar;
use App\Models\VeneerJadiMutasiKeluarPalet;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use App\Services\SerahTerimaVeneerJadiService;
use Carbon\Carbon;
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
    // 🌟 Sekarang menyimpan composite id, contoh: "gudang-5" atau "mutasi-12"
    public ?string $selectedItemId = null;

    // Modal Form Keluar Barang
    public bool $showFormKeluarModal = false;
    public ?int $selectedStokId = null;

    // 🌟 PERBAIKAN: Diubah menjadi tipe data dinamis agar bisa menampung string kosong saat diedit
    public $jumlahPalet = 1;
    public array $paletQuantities = [0 => '']; // Input dinamis lembar per palet
    public string $tujuanKeluar = 'Hotpress';
    public string $keteranganKeluar = '';
    public ?int $idProduksiHp = null;

    protected $queryString = ['activeTab'];
    public string $activeSubTab = 'produksi';

    public function hitungKubikasi(float $p, float $l, float $t, ?int $lembar): float
    {
        $lembarAman = $lembar ?? 0;
        return ($p * $l * $t * $lembarAman) / 10000000;
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
        if (!$this->selectedItemId) {
            return;
        }

        [$source, $rawId] = array_pad(explode('-', $this->selectedItemId, 2), 2, null);

        if ($source === 'mutasi') {
            $this->terimaDariMutasi((int) $rawId);
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

                if (!$record) {
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

                if (!$stokInduk) {
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
                        "%s, diterima oleh: %s pada %s",
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
            'core' => $allStok->filter(fn($item) => $item->tebal >= 1.0),
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
        $query = GudangModel::with(['jenisKayu', 'penerima'])
            ->select([
                'gudang_veneer_jadis.*',
                'jenis_kayus.nama_kayu as jenis_kayu_nama',
            ])
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'gudang_veneer_jadis.id_jenis_kayu');

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

        // Sumber 1: GudangVeneerJadi (Produksi/Repair)
        $antreanGudang = $query->get()->map(fn($item) => [
            'id' => 'gudang-' . $item->id,
            'source' => 'gudang',
            'jenis_kayu' => $item->jenis_kayu_nama,
            'panjang' => $item->panjang,
            'lebar' => $item->lebar,
            'tebal' => $item->tebal,
            'kw' => $item->kw_grade,
            'jumlah' => $item->stok_lembar,
            'stok_kubikasi' => $item->stok_kubikasi,
            'created_at' => $item->created_at, // dipakai untuk tampilan (masih Carbon)
            'created_at_ts' => $item->created_at?->timestamp ?? 0, // dipakai untuk sorting (integer)
            'status_gudang' => $item->status_gudang ?? 'belum diterima',
            'diterima_at' => $item->diterima_at,
            'diterima_by' => $item->diterima_by,
            'penerima_name' => $item->penerima?->name ?? 'N/A',
            'keterangan' => $item->keterangan,
        ]);

        // Sumber 2: VeneerMutasi (arah masuk) — lihat ambilAntreanDariMutasiJadi()
        $antreanMutasi = $this->ambilAntreanDariMutasiJadi();

        // Gabungkan kedua sumber, lalu urutkan: "belum diterima" dulu, baru terbaru dulu
        return $antreanGudang->concat($antreanMutasi)
            ->sortBy([
                fn($a, $b) => ($a['status_gudang'] === 'belum diterima' ? 0 : 1)
                <=> ($b['status_gudang'] === 'belum diterima' ? 0 : 1),
                fn($a, $b) => $b['created_at_ts'] <=> $a['created_at_ts'],
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
            ->whereHas('notaBm', fn($q) => $q->whereNotNull('divalidasi_oleh'))
            ->whereHas('details', fn($q) => $q->where('tipe_veneer', 'like', $jadiLike))
            ->with([
                'details' => fn($q) => $q
                    ->where('tipe_veneer', 'like', $jadiLike)
                    ->with(['ukuran', 'jenisKayu']),
            ])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        // Kumpulkan semua id detail dari semua mutasi di atas, lalu cek SEKALI
        // mana saja yang sudah punya log 'masuk' (artinya: sudah "Diterima").
        $semuaDetailIds = $mutasiRows->flatMap(fn($vm) => $vm->details->pluck('id'));

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
                if (!empty($this->tableSearchQuery)) {
                    $q = strtolower($this->tableSearchQuery);
                    $kayu = strtolower((string) $detail->jenisKayu?->nama_kayu);
                    $kw = strtolower((string) $detail->kw);
                    if (!str_contains($kayu, $q) && !str_contains($kw, $q)) {
                        continue;
                    }
                }

                $waktuNota = $mutasi->created_at ?? $mutasi->tanggal ?? now();
                $timestamp = $waktuNota instanceof Carbon ? $waktuNota->timestamp : strtotime($waktuNota);

                $hasil->push([
                    'id' => 'mutasi-' . $detail->id,
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
                    'keterangan' => 'No Nota: ' . ($mutasi->no_nota ?? '-'),
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

                $userName = Auth::user()?->name ?? 'System';

                // Header tetap dibuat sekali — ini tidak berubah dari sebelumnya
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
                    'id_produksi_hp' => $this->tujuanKeluar === 'Hotpress'
                        ? ProduksiHp::latest()->first()?->id
                        : null,
                ]);

                // 🌟 Variabel "berjalan" — ini kuncinya. Nilainya bergerak turun tiap iterasi
                $stokLembarBerjalan = $stok->stok_lembar;
                $stokKubikasiBerjalan = $stok->stok_kubikasi;
                $nilaiStokBerjalan = $stok->nilai_stok;
                $lastLogId = null;

                foreach ($this->paletQuantities as $index => $qty) {
                    $qtyPalet = intval($qty);
                    if ($qtyPalet <= 0)
                        continue; // lewati palet kosong, jangan sampai ganggu log

                    $palet = VeneerJadiMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'nomor_palet' => $index + 1,
                        'jumlah_lembar' => $qtyPalet,
                    ]);

                    // Simpan kondisi "sebelum" dari nilai berjalan saat ini
                    $stokLembarBefore = $stokLembarBerjalan;
                    $stokKubikasiBefore = $stokKubikasiBerjalan;
                    $nilaiStokBefore = $nilaiStokBerjalan;

                    // Update nilai berjalan berdasarkan palet ini SAJA
                    $stokLembarBerjalan -= $qtyPalet;
                    $stokKubikasiBerjalan = $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $stokLembarBerjalan);
                    $nilaiTerpotongPalet = $qtyPalet * $stok->hpp_average;
                    $nilaiStokBerjalan = max(0, $nilaiStokBerjalan - $nilaiTerpotongPalet);

                    $log = HppVeneerJadiLog::create([
                        'id_jenis_kayu' => $stok->id_jenis_kayu,
                        'panjang' => $stok->panjang,
                        'lebar' => $stok->lebar,
                        'tebal' => $stok->tebal,
                        'kw_grade' => $stok->kw_grade,
                        'tanggal' => now(),
                        'tipe_transaksi' => 'KELUAR',
                        'referensi_type' => VeneerJadiMutasiKeluarPalet::class, // ⬅️ diarahkan ke palet, bukan header
                        'referensi_id' => $palet->id,
                        'total_lembar' => $qtyPalet,
                        'total_kubikasi' => $this->hitungKubikasi($stok->panjang, $stok->lebar, $stok->tebal, $qtyPalet),
                        'hpp_pekerja' => $stok->hpp_pekerja_last ?? 0,
                        'hpp_bahan_penolong' => $stok->hpp_bahan_penolong_last ?? 0,
                        'hpp_average' => $stok->hpp_average,
                        'nilai_stok' => $nilaiTerpotongPalet,
                        'stok_lembar_before' => $stokLembarBefore,
                        'stok_kubikasi_before' => $stokKubikasiBefore,
                        'nilai_stok_before' => $nilaiStokBefore,
                        'stok_lembar_after' => $stokLembarBerjalan,
                        'stok_kubikasi_after' => $stokKubikasiBerjalan,
                        'nilai_stok_after' => $nilaiStokBerjalan,
                        'keterangan' => "Mutasi Keluar ke [{$this->tujuanKeluar}] — Palet #{$palet->nomor_palet} dari Gudang Veneer Jadi oleh {$userName}",
                    ]);

                    $lastLogId = $log->id;
                }

                // Update stok induk pakai nilai FINAL setelah semua palet diproses
                $stok->update([
                    'stok_lembar' => $stokLembarBerjalan,
                    'stok_kubikasi' => $stokKubikasiBerjalan,
                    'nilai_stok' => $nilaiStokBerjalan,
                    'id_last_log' => $lastLogId,
                ]);
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
}