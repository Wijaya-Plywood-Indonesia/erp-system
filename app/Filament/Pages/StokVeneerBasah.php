<?php

namespace App\Filament\Pages;

use App\Models\HppVeneerBasahSummary;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use UnitEnum;

class StokVeneerBasah extends Page
{
    protected string $view = 'filament.pages.stok-veneer-basah';

    protected static ?string $navigationLabel = 'Stok Veneer Basah';
    protected static string|UnitEnum|null $navigationGroup = 'Stok Veneer Basah';
    protected static ?string $title          = 'Stok Veneer Basah';
    protected static ?int    $navigationSort = 10;

    // ── State ──────────────────────────────────────────────────
    public string $filterJenisKayu = '';
    public string $filterTebal     = '';
    public string $filterKw        = '';

    /**
     * HEADER ACTION: Input Stok Manual Menggunakan Select Ukuran & Input Kubikasi Manual
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('inputStok')
                ->label('Input Stok Manual')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->modalHeading('Input / Inisialisasi Stok Veneer Basah')
                ->form([
                    Grid::make()
                        ->schema([
                            Select::make('id_jenis_kayu')
                                ->label('Jenis Kayu')
                                ->options(JenisKayu::pluck('nama_kayu', 'id'))
                                ->searchable()
                                ->required(),

                            TextInput::make('kw')
                                ->label('Kualitas (KW)')
                                ->integer()
                                ->required(),

                            Select::make('id_ukuran')
                                ->label('Ukuran Dimensi')
                                ->options(
                                    Ukuran::get()->mapWithKeys(fn($u) => [
                                        $u->id => $u->dimensi
                                    ])
                                )
                                ->default(
                                    fn() => Ukuran::latest()->first()?->id
                                )
                                ->searchable()
                                ->required(),

                            TextInput::make('stok_lembar')
                                ->label('Jumlah Lembar')
                                ->numeric()
                                ->minValue(1)
                                ->required(),

                            // INPUT KUBIKASI MANUAL (Sesuai Permintaan)
                            TextInput::make('stok_kubikasi')
                                ->label('Kubikasi (m³)')
                                ->numeric()
                                ->step('0.0001')
                                ->helperText('Masukkan volume dalam m³ secara manual.')
                                ->required(),
                        ])
                ])
                ->action(function (array $data) {
                    // 1. Ambil data dimensi dari model Ukuran (tetap diperlukan untuk identifikasi baris)
                    $ukuranRecord = Ukuran::find($data['id_ukuran']);

                    if (!$ukuranRecord) {
                        Notification::make()->danger()->title('Gagal')->body('Data ukuran tidak ditemukan.')->send();
                        return;
                    }

                    $panjang = (float) $ukuranRecord->panjang;
                    $lebar   = (float) $ukuranRecord->lebar;
                    $tebal   = (float) $ukuranRecord->tebal;

                    // 2. Gunakan Kubikasi dari Input Manual
                    $stokKubikasi = round((float) $data['stok_kubikasi'], 4);

                    // 3. Hitung Nilai Stok Otomatis (Kubikasi * Harga per m3)
                    $nilaiStok = round($stokKubikasi * (float) $data['harga_satuan'], 2);

                    // 4. Hitung HPP Average (Karena inisialisasi, HPP = Harga per m3)
                    $hppAverage = (int) round($data['harga_satuan']);

                    // 5. Simpan dengan updateOrCreate
                    HppVeneerBasahSummary::updateOrCreate(
                        [
                            'id_jenis_kayu' => $data['id_jenis_kayu'],
                            'panjang'       => $panjang,
                            'lebar'         => $lebar,
                            'tebal'         => $tebal,
                            'kw'            => $data['kw'],
                        ],
                        [
                            'stok_lembar'   => $data['stok_lembar'],
                            'stok_kubikasi' => $stokKubikasi,
                            'nilai_stok'    => $nilaiStok,
                            'hpp_average'   => $hppAverage,
                        ]
                    );

                    Notification::make()
                        ->success()
                        ->title('Stok Berhasil Disimpan')
                        ->body("Data veneer {$panjang}×{$lebar}×{$tebal} (KW {$data['kw']}) telah diperbarui dengan volume {$stokKubikasi} m³.")
                        ->send();
                }),
        ];
    }

    // ── Computed: semua summaries ──────────────────────────────
    public function getSummariesProperty()
    {
        return HppVeneerBasahSummary::with(['jenisKayu', 'lastLog'])
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal',     $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw',        $this->filterKw))
            ->where('stok_lembar', '>', 0)
            ->orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get();
    }

    // ── Computed: grouped per tebal ────────────────────────────
    public function getGroupedSummariesProperty()
    {
        return $this->summaries->groupBy('tebal')->sortKeys();
    }

    // ── Computed: daftar KW unik ───────────────────────────────
    public function getKwListProperty()
    {
        return HppVeneerBasahSummary::where('stok_lembar', '>', 0)
            ->whereNotNull('kw')
            ->distinct()->orderBy('kw')->pluck('kw');
    }

    // ── Computed: daftar tebal unik ─────────────────────────────
    public function getTebalListProperty()
    {
        return HppVeneerBasahSummary::where('stok_lembar', '>', 0)
            ->distinct()->orderBy('tebal')->pluck('tebal');
    }

    // ── Computed: total nilai stok ──────────────────────────────
    public function getTotalNilaiStokProperty(): float
    {
        return (float) HppVeneerBasahSummary::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal',     $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw',        $this->filterKw))
            ->sum('nilai_stok');
    }

    // ── Computed: total lembar ──────────────────────────────────
    public function getTotalLembarProperty(): int
    {
        return (int) HppVeneerBasahSummary::where('stok_lembar', '>', 0)
            ->when($this->filterJenisKayu, fn($q) => $q->where('id_jenis_kayu', $this->filterJenisKayu))
            ->when($this->filterTebal,     fn($q) => $q->where('tebal',     $this->filterTebal))
            ->when($this->filterKw,        fn($q) => $q->where('kw',        $this->filterKw))
            ->sum('stok_lembar');
    }
}
