<?php

namespace App\Filament\Pages;

use App\Exports\NewRekapAbsensiExport;
use App\Exports\RumusGajiWijayaExport;
use App\Models\NewAbsensiUpload;
use App\Services\DownloadAbsensiUploadService;
use App\Services\NewRekapAbsensiPegawaiService;
use App\Services\UploadFingerService;
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

    public function getRekap(): Collection
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        return app(NewRekapAbsensiPegawaiService::class)->getRekap($tanggal);
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
     * Dipanggil dari tombol "Export Rumus Gaji Wijaya" di tab Data Absensi.
     *
     * Sengaja dibuat sebagai method & export class TERPISAH dari
     * exportExcel()/NewRekapAbsensiExport di atas — supaya format rumus
     * gaji ini bisa dipakai berdampingan (soft transition) tanpa
     * mengganggu export lama yang sudah berjalan.
     */
    public function exportRumusGajiWijaya()
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

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
}
