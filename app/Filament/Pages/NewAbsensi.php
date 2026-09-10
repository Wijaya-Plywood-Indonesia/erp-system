<?php

namespace App\Filament\Pages;

use App\Exports\NewRekapAbsensiExport;
use App\Exports\RumusGajiWijayaExport;
use App\Models\NewAbsensiUpload;
use App\Services\DownloadAbsensiUploadService;
use App\Services\NewRekapAbsensiPegawaiService;
use App\Services\PotonganGajiService;
use App\Services\UploadFingerService;
use App\Services\ValidasiTargetProduksiService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class NewAbsensi extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Rekap Absensi Pegawai';

    protected static ?string $title = 'Rekap Absensi Pegawai';

    protected string $view = 'filament.pages.new-absensi';

    /**
     * Disinkronkan ke query string URL (?tanggal=YYYY-MM-DD) supaya kalau
     * halaman di-refresh atau link-nya dibagikan/dibuka ulang, tanggal yang
     * lagi dipilih user tetap sama (tidak balik ke tanggal hari ini).
     * `keep: true` supaya parameter tetap muncul di URL walau nilainya
     * balik ke default.
     */
    #[Url(keep: true)]
    public ?string $tanggal = null;

    public string $activeTab = 'data';

    /**
     * @var array<string, mixed>
     */
    public array $uploadData = [];

    /**
     * @var array<int, TemporaryUploadedFile>|null
     */
    public ?array $fingerFiles = null;

    /**
     * Baris-baris (dikunci per id_pegawai, fallback nama_pegawai kalau id
     * kosong — sama seperti key yang dipakai gabungkanMultiSumber() di
     * service) yang lagi dalam kondisi "expanded" di tabel Data Absensi.
     * Dipakai buat expandable row preview finger (shift pagi & malam).
     *
     * Sengaja pakai Livewire property (bukan Alpine-only x-data) supaya
     * satu toggle bisa dipakai bareng antara row utama & row detail tanpa
     * perlu wrapping element tambahan yang gak valid di dalam <tbody>.
     *
     * @var array<string, bool>
     */
    public array $expandedRows = [];

    /**
     * Hasil pengecekan terakhir dari ValidasiTargetProduksiService untuk
     * tanggal yang sedang dipilih. Diisi otomatis oleh cekTargetProduksi()
     * — dipanggil dari mount() (supaya user tidak perlu pencet tombol dulu
     * saat pertama kali buka halaman), dari updatedTanggal() (setiap kali
     * tanggal diganti), MAUPUN otomatis diisi ulang setiap kali
     * exportRumusGajiWijaya() dipanggil, supaya user selalu lihat status
     * paling baru di halaman (tabel peringatan), tanpa export itu sendiri
     * ikut terblokir kalau ada yang belum lengkap.
     *
     * CATATAN: tombol manual "Cek Ulang Kelengkapan Target" sudah
     * dihilangkan dari UI karena pengecekan sekarang selalu otomatis
     * (mount() & updatedTanggal()). Method cekTargetProduksi() sengaja
     * TIDAK dihapus — masih dipanggil dari kedua tempat tersebut.
     *
     * @var array<int, array{divisi: string, ukuran: string, keterangan: string}>
     */
    public array $missingTargetItems = [];

    /**
     * Menandai apakah pengecekan target untuk tanggal yang sedang dipilih
     * sudah pernah dijalankan (dipakai buat bedakan "belum pernah dicek"
     * vs "sudah dicek dan hasilnya kosong/aman").
     */
    public bool $sudahDicekTarget = false;

    /**
     * Menandai apakah panel peringatan "item belum punya target" sedang
     * ditampilkan atau disembunyikan di UI. Panel tetap otomatis DIHITUNG
     * setiap kali halaman dibuka / tanggal berubah (lihat mount() &
     * updatedTanggal()) — property ini murni mengatur visibility-nya.
     *
     * CATATAN: tombol untuk toggle property ini (toggleTargetPanel) di
     * blade sekarang HANYA ditampilkan untuk user dengan role
     * `super_admin` — user lain selalu melihat panel ini terbuka kalau
     * ada item yang belum punya target (tidak bisa menyembunyikannya).
     */
    public bool $showTargetPanel = true;

    public function mount(): void
    {
        // Pakai ??= (bukan langsung assign) supaya nilai yang sudah datang
        // dari query string URL (?tanggal=...) lewat atribut #[Url] di atas
        // tidak ketimpa balik ke tanggal hari ini. Livewire mengisi
        // property dari query string SEBELUM mount() dipanggil, jadi kalau
        // $this->tanggal sudah ada isinya, biarkan seperti itu — hanya
        // fallback ke hari ini kalau memang belum ada tanggal sama sekali
        // (misal pertama kali buka halaman tanpa query string).
        $this->tanggal ??= now()->format('Y-m-d');
        $this->uploadForm->fill();

        // Langsung jalankan pengecekan target saat halaman pertama kali
        // dibuka, supaya user tidak perlu pencet tombol manual dulu buat
        // lihat status kelengkapan target tanggal yang sedang aktif —
        // tabel peringatan (kalau ada) langsung tampil.
        $this->cekTargetProduksi();
    }

    /**
     * Filament butuh tahu form mana saja yang aktif di halaman ini
     * supaya masing-masing punya state & validasi sendiri-sendiri.
     */
    protected function getForms(): array
    {
        return [
            'uploadForm',
        ];
    }

    /**
     * Form upload file finger — hanya dirender di tab Upload.
     */
    public function uploadForm(Schema $schema): Schema
    {
        return $schema
            ->schema([
                FileUpload::make('fingerFiles')
                    ->label('Upload File Finger ')
                    ->multiple()
                    ->storeFiles(false)
                    ->maxFiles(10)
                    ->rules(['file', 'extensions:txt,dat']),
            ])
            ->statePath('uploadData');
    }

    /**
     * Dipanggil dari tombol "Proses Upload".
     */
    public function uploadFinger(): void
    {
        $data = $this->uploadForm->getState();

        if (empty($data['fingerFiles'])) {
            Notification::make()
                ->title('Pilih minimal 1 file dulu')
                ->warning()
                ->send();

            return;
        }

        try {
            $upload = app(UploadFingerService::class)->handle(
                $data['fingerFiles'],
                auth()->user()?->name ?? 'system',
                $this->tanggal ?? now()->format('Y-m-d'), // tanggal dari datepicker di UI
            );

            Notification::make()
                ->title('Berhasil diproses')
                ->body('Batch #'.$upload->id.' — '.count($upload->file_path).' file berhasil diproses.')
                ->success()
                ->send();

            $this->fingerFiles = null;
            $this->uploadForm->fill();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal memproses file')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Rekap absensi untuk tabel Data Absensi. Setiap baris diperkaya
     * dengan field 'potongan' (potongan target produksi, digabung dari
     * 11 divisi produksi lewat PotonganGajiService) supaya kolom
     * "Potongan" di blade angkanya SAMA PERSIS dengan kolom "Potongan"
     * pada file "Export Format Baru" (RumusGajiWijayaExport) — sumber
     * perhitungannya sengaja dipakai bareng (satu service), bukan
     * dihitung ulang terpisah dengan logic sendiri.
     */
    public function getRekap(): Collection
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        $rekap = app(NewRekapAbsensiPegawaiService::class)->getRekap($tanggal);

        $potonganService = app(PotonganGajiService::class);
        $potonganMap = $potonganService->getPotonganMap($tanggal);

        return $rekap->map(function ($row) use ($potonganService, $potonganMap) {
            $row['potongan'] = $potonganService->resolvePotongan($potonganMap, $row['kode_pegawai'] ?? null);

            return $row;
        });
    }

    public function getAbsensiLainLain(): Collection
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        return app(NewRekapAbsensiPegawaiService::class)->getAbsensiLainLain($tanggal);
    }

    /**
     * Dipanggil dari tombol "Export Excel" di tab Data Absensi.
     */
    public function exportExcel()
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        $rekap = app(NewRekapAbsensiPegawaiService::class)->getRekap($tanggal);

        return Excel::download(
            new NewRekapAbsensiExport($rekap, $tanggal),
            "Absen-{$tanggal}.xlsx"
        );
    }

    /**
     * Dipanggil dari mount() (otomatis saat halaman pertama kali dibuka)
     * dan dari updatedTanggal() (otomatis setiap kali tanggal diganti) —
     * HANYA mengecek & menampilkan hasilnya di halaman (tabel peringatan),
     * TIDAK men-download apa pun.
     *
     * Tombol manual untuk memanggil method ini secara langsung sudah
     * dihilangkan dari UI (lihat new-absensi.blade.php) karena
     * pengecekan sekarang selalu otomatis jalan di kedua lifecycle hook
     * di atas.
     */
    public function cekTargetProduksi(): void
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        $this->missingTargetItems = app(ValidasiTargetProduksiService::class)
            ->cekMissingTarget($tanggal);
        $this->sudahDicekTarget = true;

        if (empty($this->missingTargetItems)) {
            Notification::make()
                ->title('Semua item sudah punya target')
                ->body('Tidak ditemukan ukuran/produksi tanpa target untuk tanggal ini.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->warning()
            ->title(count($this->missingTargetItems).' item belum punya target')
            ->body('Lihat daftar lengkapnya di tabel bawah tombol export. Kamu tetap bisa export — potongan untuk item tersebut akan dianggap 0.')
            ->send();
    }

    /**
     * Dipanggil dari tombol show/hide di panel peringatan target — di
     * blade tombolnya sekarang HANYA ditampilkan untuk role
     * `super_admin`. Tidak menghitung ulang apa pun — hanya toggle
     * visibility panelnya, datanya sendiri (missingTargetItems) tetap
     * tersimpan di property seperti biasa.
     */
    public function toggleTargetPanel(): void
    {
        $this->showTargetPanel = ! $this->showTargetPanel;
    }

    /**
     * Dipanggil dari tombol "Export Rumus Gaji Wijaya" di tab Data Absensi.
     *
     * Sengaja dibuat sebagai method & export class TERPISAH dari
     * exportExcel()/NewRekapAbsensiExport di atas — supaya format rumus
     * gaji ini bisa dipakai berdampingan (soft transition) tanpa
     * mengganggu export lama yang sudah berjalan.
     *
     * ALUR BARU: sebelum download dibuat, jalankan dulu
     * ValidasiTargetProduksiService::cekMissingTarget() untuk tanggal
     * yang sedang dipilih. Kalau ketemu ukuran/produksi tanpa target,
     * tampilkan notifikasi peringatan DAN isi $missingTargetItems supaya
     * tabelnya juga muncul di halaman — TAPI proses export tetap
     * dilanjutkan seperti biasa (tidak diblokir). Konsekuensinya: item
     * yang tidak punya target otomatis dihitung potongan = 0 (perilaku
     * lama, tidak berubah), user cuma diberi tahu lebih dulu.
     */
    public function exportRumusGajiWijaya()
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        $this->missingTargetItems = app(ValidasiTargetProduksiService::class)
            ->cekMissingTarget($tanggal);
        $this->sudahDicekTarget = true;
        // Pastikan panelnya tampil lagi kalau sebelumnya di-hide user,
        // supaya hasil pengecekan terbaru ini benar-benar terlihat.
        $this->showTargetPanel = true;

        if (! empty($this->missingTargetItems)) {
            $bodyLines = collect($this->missingTargetItems)
                ->take(10)
                ->map(fn ($m) => "• [{$m['divisi']}] {$m['ukuran']}")
                ->implode("\n");

            $sisa = count($this->missingTargetItems) - 10;
            if ($sisa > 0) {
                $bodyLines .= "\n… dan {$sisa} item lainnya (lihat tabel di halaman).";
            }

            Notification::make()
                ->warning()
                ->title(count($this->missingTargetItems).' ukuran belum punya target — export tetap dilanjutkan')
                ->body("Item berikut tidak punya target, potongannya akan dianggap 0:\n\n".$bodyLines)
                ->persistent()
                ->send();
        }

        $rekap = app(NewRekapAbsensiPegawaiService::class)->getRekap($tanggal);

        return Excel::download(
            new RumusGajiWijayaExport($rekap, $tanggal),
            "Rumus-Gaji-Wijaya-{$tanggal}.xlsx"
        );
    }

    /**
     * Riwayat upload, terbaru duluan (dibatasi 20 biar ringan, tinggal
     * diganti pagination kalau nanti datanya udah banyak).
     */
    public function getUploadHistory(): Collection
    {
        return NewAbsensiUpload::query()
            ->latest('id')
            ->limit(20)
            ->get();
    }

    /**
     * Batch upload yang tanggalnya cocok dengan tanggal yang lagi
     * dipilih user di halaman ini. Dipakai untuk tombol download.
     */
    public function getUploadForSelectedDate(): Collection
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        return NewAbsensiUpload::query()
            ->whereDate('tanggal', $tanggal)
            ->latest('id')
            ->get();
    }

    public function downloadUpload(int $uploadId)
    {
        $upload = NewAbsensiUpload::findOrFail($uploadId);

        return app(DownloadAbsensiUploadService::class)->download([$upload]);
    }

    public function downloadFingerForSelectedDate()
    {
        $uploads = $this->getUploadForSelectedDate();

        if ($uploads->isEmpty()) {
            Notification::make()
                ->title('Tidak ada file finger untuk tanggal ini')
                ->warning()
                ->send();

            return;
        }

        return app(DownloadAbsensiUploadService::class)->download($uploads);
    }

    /**
     * Toggle expand/collapse baris preview finger (shift pagi & malam) di
     * tabel Data Absensi. $rowKey dikirim dari blade — sama persis dengan
     * key yang dipakai buat wire:key row itu (id_pegawai, fallback
     * nama_pegawai kalau id kosong).
     */
    public function toggleRow(string $rowKey): void
    {
        if (! empty($this->expandedRows[$rowKey])) {
            unset($this->expandedRows[$rowKey]);

            return;
        }

        $this->expandedRows[$rowKey] = true;
    }

    public function isRowExpanded(string $rowKey): bool
    {
        return ! empty($this->expandedRows[$rowKey]);
    }

    /**
     * Dipanggil otomatis oleh Livewire setiap kali property $tanggal
     * berubah lewat wire:model.live di blade. Reset dulu hasil
     * pengecekan lama (supaya tabel peringatan tidak "nyampur" dengan
     * hasil cek tanggal sebelumnya) lalu langsung jalankan ulang
     * cekTargetProduksi() untuk tanggal yang baru — jadi user tidak perlu
     * pencet tombol manual lagi tiap kali ganti tanggal. Panel juga
     * dipastikan tampil lagi (showTargetPanel = true) supaya hasil
     * terbaru langsung kelihatan.
     */
    public function updatedTanggal(): void
    {
        $this->missingTargetItems = [];
        $this->sudahDicekTarget = false;
        $this->showTargetPanel = true;

        $this->cekTargetProduksi();
    }
}
