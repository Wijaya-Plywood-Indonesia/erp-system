<?php

namespace App\Filament\Pages;
use App\Models\JenisKayu;
use App\Models\StokLogCore as ModelsStokLogCore;
use App\Services\LogCoreStokService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use RuntimeException;
use UnitEnum;

class StokLogCore extends Page
{
    use HasPageShield;
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'filament.pages.stok-log-core';
    protected static ?string $navigationLabel = 'Stok LogCoe';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title = 'Stok LogCore';
    protected static ?int $navigationSort = 2;
    public string $filterPanjang = '';
    public string $filterJenis = '';

    // ── Computed: semua stok berjalan ──────────────────
    public function getSummariesProperty()
    {
        return ModelsStokLogCore::with('jenisKayu')
            ->when(
                $this->filterPanjang,
                fn($q) => $q->where('panjang', $this->filterPanjang)
            )
            ->when(
                $this->filterJenis,
                fn($q) => $q->whereHas(
                    'jenisKayu',
                    fn($q2) => $q2->where('nama_kayu', $this->filterJenis)
                )
            )
            ->where('stok_qty', '>', 0)
            ->get();
    }

    public function getGroupedSummariesProperty()
    {
        return $this->summaries->groupBy('panjang')->sortKeys();
    }

    public function getPanjangListProperty()
    {
        return ModelsStokLogCore::where('stok_qty', '>', 0)
            ->distinct()
            ->orderBy('panjang')
            ->pluck('panjang');
    }

    public function getJenisListProperty()
    {
        return ModelsStokLogCore::with('jenisKayu')
            ->where('stok_qty', '>', 0)
            ->get()
            ->pluck('jenisKayu.nama_kayu')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    // ── Tombol Catatan Transaksi ───────────────────────
    protected function getHeaderActions(): array
    {
        return [
            Action::make('catatTransaksi')
                ->label('Catat Transaksi')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->modalHeading('Catatan Transaksi LogCore')
                ->modalSubmitActionLabel('Simpan')
                ->schema([
                    Select::make('panjang')
                        ->label('Panjang')
                        ->options([
                            130 => '130',
                            260 => '260',
                        ])
                        ->required()
                        ->native(false),
                    Select::make('id_jenis_kayu')
                        ->label('Jenis Kayu')
                        ->options(
                            JenisKayu::orderBy('nama_kayu')
                                ->pluck('nama_kayu', 'id')
                        )
                        ->searchable()
                        ->required(),
                    Select::make('tipe_transaksi')
                        ->label('Tipe Transaksi')
                        ->options([
                            'masuk'  => 'Masuk',
                            'keluar' => 'Keluar',
                        ])
                        ->required()
                        ->native(false),
                    TextInput::make('qty')
                        ->label('Qty (batang)')
                        ->numeric()
                        ->minValue(0.01)
                        ->required(),
                    DatePicker::make('tanggal')
                        ->label('Tanggal')
                        ->default(now())
                        ->required(),
                    Textarea::make('keterangan')
                        ->label('Keterangan')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $namaUser = auth()->user()?->name ?? 'Sistem';
                    $ket = trim($data['keterangan'] ?? '');
                    $ketFinal = $ket !== ''
                        ? "{$ket} (oleh {$namaUser})"
                        : "Dicatat oleh {$namaUser}";
                    try {
                        app(LogCoreStokService::class)->catatTransaksi(
                            idJenisKayu: (int) $data['id_jenis_kayu'],
                            panjang: (float) $data['panjang'],
                            tipeTransaksi: $data['tipe_transaksi'],
                            qty: (float) $data['qty'],
                            tanggal: $data['tanggal'],
                            keterangan: $ketFinal,
                        );
                    } catch (RuntimeException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Transaksi gagal')
                            ->body($e->getMessage())
                            ->send();
                        return;
                    }
                    $jenis = JenisKayu::find(
                        $data['id_jenis_kayu']
                    )?->nama_kayu;
                    Notification::make()
                        ->success()
                        ->title('Transaksi berhasil dicatat')
                        ->body(
                            "{$data['qty']} batang {$data['tipe_transaksi']} - {$jenis} panjang {$data['panjang']}"
                        )
                        ->send();
                }),
        ];
    }
}