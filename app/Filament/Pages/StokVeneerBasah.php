<?php

namespace App\Filament\Pages;

use App\Models\HppVeneerBasahSummary;
use App\Models\JenisKayu;
use App\Models\ProduksiKedi;
use App\Models\ProduksiPressDryer;
use App\Models\Ukuran;
use App\Services\GudangVeneerBasahService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use UnitEnum;

class StokVeneerBasah extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.stok-veneer-basah';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Stok Veneer Basah';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Veneer Basah';
    protected static ?int    $navigationSort = 2;

    // State untuk filtering di UI Blade
    public string $filterJenisKayu = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';
    public string $filterCoreType  = '';

    public bool $showKubikasi   = false;
    public bool $showHppAverage = false;
    public bool $showNilaiStok  = false;

    /**
     * Header Action: keluarkan stok gudang veneer basah ke Press Dryer / Kedi.
     * Ini hanya MEMBUAT DOKUMEN MUTASI + baris "Menunggu" di sisi penerima —
     * stok belum berkurang sampai penerima menekan tombol "Terima".
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('mutasiKeluar')
                ->label('Keluarkan ke Produksi')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('primary')
                ->modalHeading('Keluarkan Veneer Basah ke Press Dryer / Kedi')
                ->modalWidth('3xl')
                ->form([
                    Select::make('tujuan')
                        ->label('Tujuan')
                        ->options([
                            'dryer' => 'Press Dryer',
                            'kedi' => 'Kedi',
                        ])
                        ->required()
                        ->live(),

                    Select::make('id_produksi_dryer')
                        ->label('Produksi Press Dryer Tujuan')
                        ->options(fn () => ProduksiPressDryer::latest('tgl_produksi')->limit(50)->get()
                            ->mapWithKeys(fn ($p) => [$p->id => "#{$p->id} - {$p->tgl_produksi}"]))
                        ->searchable()
                        ->required()
                        ->visible(fn (Get $get) => $get('tujuan') === 'dryer'),

                    Select::make('id_produksi_kedi')
                        ->label('Produksi Kedi Tujuan')
                        ->options(fn () => ProduksiKedi::latest('tanggal')->limit(50)->get()
                            ->mapWithKeys(fn ($p) => [$p->id => "#{$p->id} - {$p->tanggal} ({$p->kode_kedi})"]))
                        ->searchable()
                        ->required()
                        ->visible(fn (Get $get) => $get('tujuan') === 'kedi'),

                    Repeater::make('items')
                        ->label('Item yang Dikeluarkan')
                        ->schema([
                            Select::make('id_jenis_kayu')
                                ->label('Jenis Kayu')
                                ->options(JenisKayu::pluck('nama_kayu', 'id'))
                                ->searchable()
                                ->required(),

                            Select::make('id_ukuran')
                                ->label('Ukuran')
                                ->options(Ukuran::get()->mapWithKeys(fn ($u) => [
                                    $u->id => "{$u->panjang}x{$u->lebar}x{$u->tebal}",
                                ]))
                                ->searchable()
                                ->required(),

                            TextInput::make('kw')
                                ->label('KW')
                                ->required(),

                            TextInput::make('qty_lembar')
                                ->label('Jumlah Lembar')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                        ])
                        ->columns(4)
                        ->minItems(1)
                        ->required(),

                    Textarea::make('keterangan')
                        ->label('Keterangan')
                        ->nullable(),
                ])
                ->action(function (array $data) {
                    try {
                        app(GudangVeneerBasahService::class)->buatMutasiKeluar(
                            items: $data['items'],
                            tujuan: $data['tujuan'],
                            idProduksiDryer: $data['id_produksi_dryer'] ?? null,
                            idProduksiKedi: $data['id_produksi_kedi'] ?? null,
                            keterangan: $data['keterangan'] ?? null,
                        );

                        Notification::make()
                            ->title('Mutasi Keluar Dibuat')
                            ->body('Menunggu konfirmasi Terima dari sisi penerima. Stok gudang belum berkurang.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Gagal Membuat Mutasi')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    /**
     * Header Action untuk Inisialisasi/Input Stok Manual
     */
    // protected function getHeaderActionsOld(): array
    // {
    //     return [
    //         Action::make('inputStok')
    //             ->label('Input Stok Manual')
    //             ->icon('heroicon-m-plus')
    //             ->color('primary')
    //             ->modalHeading('Input / Inisialisasi Stok Veneer Basah')
    //             ->form([
    //                 Grid::make()
    //                     ->schema([
    //                         Select::make('id_jenis_kayu')
    //                             ->label('Jenis Kayu')
    //                             ->options(JenisKayu::pluck('nama_kayu', 'id'))
    //                             ->searchable()
    //                             ->required(),

    //                         TextInput::make('kw')
    //                             ->label('Kualitas (KW)')
    //                             ->numeric()
    //                             ->default(1)
    //                             ->required(),

    //                         Select::make('id_ukuran')
    //                             ->label('Ukuran Dimensi')
    //                             ->options(
    //                                 Ukuran::get()->mapWithKeys(fn($u) => [
    //                                     $u->id => "{$u->dimensi} (P:{$u->panjang} L:{$u->lebar} T:{$u->tebal})"
    //                                 ])
    //                             )
    //                             ->default(fn() => Ukuran::latest()->first()?->id)
    //                             ->searchable()
    //                             ->required(),

    //                         TextInput::make('stok_lembar')
    //                             ->label('Jumlah Lembar')
    //                             ->numeric()
    //                             ->minValue(1)
    //                             ->required(),

    //                         TextInput::make('stok_kubikasi')
    //                             ->label('Kubikasi (m³)')
    //                             ->numeric()
    //                             ->placeholder('0.0000')
    //                             ->required(),

    //                         TextInput::make('harga_satuan')
    //                             ->label('Harga per m³ (HPP)')
    //                             ->numeric()
    //                             ->prefix('Rp')
    //                             ->required(),
    //                     ])
    //             ])
    //             ->action(function (array $data) {
    //                 $ukuranRecord = Ukuran::find($data['id_ukuran']);

    //                 if (!$ukuranRecord) {
    //                     Notification::make()->danger()->title('Data Ukuran Tidak Ditemukan')->send();
    //                     return;
    //                 }

    //                 // Sinkronisasi format desimal dengan Database (uq_hpp_vb_summary_kombinasi)
    //                 $panjang = number_format((float) $ukuranRecord->panjang, 2, '.', '');
    //                 $lebar   = number_format((float) $ukuranRecord->lebar, 2, '.', '');
    //                 $tebal   = number_format((float) $ukuranRecord->tebal, 2, '.', '');

    //                 $stokKubikasi = round((float) $data['stok_kubikasi'], 4);
    //                 $hargaSatuan  = (float) ($data['harga_satuan'] ?? 0);
    //                 $nilaiStok    = round($stokKubikasi * $hargaSatuan, 2);

    //                 /**
    //                  * LOGIKA UTAMA: updateOrCreate
    //                  * Mencari berdasarkan dimensi saja. KW dipindah ke update attributes
    //                  * untuk menghindari error Unique Constraint (Duplicate Entry).
    //                  */
    //                 HppVeneerBasahSummary::updateOrCreate(
    //                     [
    //                         'id_jenis_kayu' => $data['id_jenis_kayu'],
    //                         'panjang'       => $panjang,
    //                         'lebar'         => $lebar,
    //                         'tebal'         => $tebal,
    //                     ],
    //                     [
    //                         'kw'            => $data['kw'],
    //                         'stok_lembar'   => $data['stok_lembar'],
    //                         'stok_kubikasi' => $stokKubikasi,
    //                         'nilai_stok'    => $nilaiStok,
    //                         'hpp_average'   => (int) round($hargaSatuan),
    //                     ]
    //                 );

    //                 Notification::make()
    //                     ->success()
    //                     ->title('Stok Berhasil Disimpan')
    //                     ->body("Data veneer {$panjang}x{$lebar} (KW {$data['kw']}) telah diperbarui.")
    //                     ->send();
    //             }),
    //     ];
    // }

    public function getSummariesProperty()
    {
        return HppVeneerBasahSummary::with(['jenisKayu'])
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal',     $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw',        $this->filterKw))
            ->when(
                $this->filterCoreType === 'long',
                fn($q) =>
                $q->where('panjang', 244)->where('lebar', 122)->where('tebal', '>', 1)
            )
            ->when(
                $this->filterCoreType === 'short',
                fn($q) =>
                $q->where('panjang', 122)->where('lebar', 244)->where('tebal', '>', 1)
            )
            ->where('stok_lembar', '<>', 0)
            ->get();
    }

    public function getGroupedSummariesProperty()
    {
        return $this->summaries->groupBy('tebal')->sortKeys();
    }

    public function getKwListProperty()
    {
        return HppVeneerBasahSummary::where('stok_lembar', '<>', 0)->distinct()->pluck('kw');
    }

    public function getTebalListProperty()
    {
        return HppVeneerBasahSummary::where('stok_lembar', '<>', 0)->distinct()->pluck('tebal');
    }

    public function getTotalNilaiStokProperty(): float
    {
        return (float) HppVeneerBasahSummary::where('stok_lembar', '<>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->sum('nilai_stok');
    }

    public function getTotalLembarProperty(): int
    {
        return (int) HppVeneerBasahSummary::where('stok_lembar', '<>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->sum('stok_lembar');
    }

    public function getCoreTypeLabel($row): ?string
    {
        if ((float) $row->tebal <= 1.0) {
            return null;
        }

        if ((float) $row->panjang === 244.0 && (float) $row->lebar === 122.0) {
            return 'Long Core';
        }

        if (
            ((float) $row->panjang === 122.0 && (float) $row->lebar === 244.0)
            || ((float) $row->panjang === 122.0 && (float) $row->lebar === 130.0)
        ) {
            return 'Short Core';
        }

        return null;
    }
}