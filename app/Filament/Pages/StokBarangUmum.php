<?php

namespace App\Filament\Pages;

use App\Models\BarangUmum;
use App\Services\BarangUmumInventoryService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class StokBarangUmum extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.stok-barang-umum';

    protected static ?string $navigationLabel = 'Stok Barang Umum';
    protected static string|UnitEnum|null $navigationGroup = 'Stok';
    protected static ?string $title          = 'Stok Barang Umum';
    protected static ?int    $navigationSort = 30;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    // State filter
    public string $search         = '';
    public string $filterKategori = '';

    // Kolom nilai Rp: kolomnya tetap ada di tabel, disembunyikan dari UI dulu
    public bool $showNilai = false;

    public function getBarangListProperty(): Collection
    {
        return BarangUmum::with('stok')
            ->when($this->filterKategori, fn($q) => $q->where('kategori', $this->filterKategori))
            ->orderBy('nama_barang')
            ->get()
            ->filter(function ($item) {
                if (trim($this->search) === '') {
                    return true;
                }
                $q = strtolower(trim($this->search));
                return str_contains(strtolower($item->nama_barang), $q)
                    || str_contains(strtolower((string) $item->kategori), $q);
            })
            ->values();
    }

    public function getKategoriListProperty(): Collection
    {
        return BarangUmum::whereNotNull('kategori')->distinct()->pluck('kategori')->filter()->values();
    }

    public function getTotalItemProperty(): int
    {
        return $this->barangList->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('catatTransaksi')
                ->label('Catat Transaksi')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->modalHeading('Catat Transaksi Barang Umum')
                ->modalSubmitActionLabel('Simpan')
                ->schema([
                    Select::make('id_barang_umum')
                        ->label('Barang')
                        ->options(BarangUmum::orderBy('nama_barang')->pluck('nama_barang', 'id'))
                        ->searchable()
                        ->required()
                        ->live(),

                    Select::make('tipe_transaksi')
                        ->label('Tipe Transaksi')
                        ->options([
                            'masuk'  => 'Masuk',
                            'keluar' => 'Keluar',
                        ])
                        ->required()
                        ->native(false),

                    TextInput::make('qty')
                        ->label('Qty')
                        ->numeric()
                        ->minValue(0.0001)
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
                    $barang = BarangUmum::find($data['id_barang_umum']);

                    if (!$barang) {
                        Notification::make()->danger()->title('Barang tidak ditemukan.')->send();
                        return;
                    }

                    // Cek stok cukup untuk transaksi keluar
                    if ($data['tipe_transaksi'] === 'keluar') {
                        $stokSaatIni = (float) ($barang->stok?->stok_qty ?? 0);
                        if ($stokSaatIni < (float) $data['qty']) {
                            Notification::make()->danger()
                                ->title('Stok tidak cukup')
                                ->body("Stok {$barang->nama_barang} saat ini: {$stokSaatIni} {$barang->satuan}.")
                                ->send();
                            return;
                        }
                    }

                    // Susun keterangan + info user yang menginput
                    $namaUser   = auth()->user()?->name ?? 'Sistem';
                    $keterangan = trim($data['keterangan'] ?? '');
                    $keteranganFinal = $keterangan !== ''
                        ? "{$keterangan} (oleh {$namaUser})"
                        : "Dicatat oleh {$namaUser}";

                    app(BarangUmumInventoryService::class)->catatTransaksi(
                        idBarangUmum: $barang->id,
                        tipeTransaksi: $data['tipe_transaksi'],
                        qty: (float) $data['qty'],
                        tanggal: $data['tanggal'],
                        keterangan: $keteranganFinal,
                    );

                    Notification::make()->success()
                        ->title('Transaksi berhasil dicatat')
                        ->body("{$data['qty']} {$barang->satuan} {$data['tipe_transaksi']} - {$barang->nama_barang}")
                        ->send();
                }),
        ];
    }
}
