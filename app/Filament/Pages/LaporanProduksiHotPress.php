<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanProduksiHotPressExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProduksiHp;
use App\Models\BahanPenolongProduksi;
use App\Models\HargaPegawai;
use Carbon\Carbon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanProduksiHotPress extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-produksi-hot-press';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Produksi Hot Press';
    protected static ?int $navigationSort = 11;

    public $dataHp = [];
    public $tanggal = null;

    public function mount(): void
    {
        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->tanggal = now()->format('Y-m-d');
        $this->loadAllData();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->loadAllData()),

            Action::make('exportExcel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn() => $this->exportExcel())
                ->visible(fn() => !empty($this->dataHp)),
        ];
    }

    public function exportExcel()
    {
        try {
            if (empty($this->dataHp)) {
                throw new \Exception('Tidak ada data untuk diunduh.');
            }

            $tglFile = Carbon::parse($this->tanggal)->format('d-m-Y');

            return Excel::download(
                new LaporanProduksiHotPressExport($this->dataHp, $this->tanggal),
                "laporan-hot-press-{$tglFile}.xlsx"
            );
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Export Excel')
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggal')
                ->label('Pilih Tanggal')
                ->reactive()
                ->format('Y-m-d')
                ->displayFormat('d/m/Y')
                ->live()
                ->afterStateUpdated(function ($state) {
                    $this->tanggal = $state;
                    $this->loadAllData();
                }),
        ];
    }

    public function loadAllData()
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        $produksiList = ProduksiHp::with([
            'detailPegawaiHp.pegawaiHp',
            'triplekHasilHp.ukuran',
            'triplekHasilHp.jenisKayu',
            'triplekHasilHp.barangSetengahJadi.jenisBarang',
            'triplekHasilHp.barangSetengahJadi.grade',
            'triplekHasilHp.mesin',
            'platformHasilHp.ukuran',
            'platformHasilHp.jenisKayu',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.mesin',
            'bahanPenolongHp'
        ])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();

        $bahanPenolongList = BahanPenolongProduksi::where('kategori_produksi', 'hot_press')->get();
        $hargaPegawai = HargaPegawai::first()->harga ?? 115000;
        $dempulNames = ['Kalsium', 'Semen putih', 'Tepung', 'Lem PVAC', 'lem Dempul', 'Semen'];

        $this->dataHp = [];

        $grouped = $produksiList->groupBy(function ($p) {
            return 'HOTPRESS ' . strtoupper($p->shift) . ' BESAR';
        });

        foreach ($grouped as $machineName => $records) {
            $hasil = [];
            foreach ($records as $prod) {
                // Triplek Loop
                foreach ($prod->triplekHasilHp as $t) {
                    $u = $t->barangSetengahJadi->ukuran ?? null;
                    $p = $u->panjang ?? 0;
                    $l = $u->lebar ?? 0;
                    $tebal = $u->tebal ?? 0;
                    $banyak = $t->isi;
                    $kubikasi = ($p * $l * $tebal * $banyak) / 1000000000;

                    $hasil[] = [
                        'no_palet' => $t->no_palet,
                        'p' => $p,
                        'l' => $l,
                        't' => $tebal,
                        'isi' => $banyak,
                        'jenis_kayu' => $t->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? '-',
                        'kwalitas' => strtoupper('TRIPLEK ' . ($t->barangSetengahJadi->grade->nama_grade ?? '-')),
                        'nama_barang' => $t->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? 'Plywood',
                        'kubikasi' => round($kubikasi, 4),
                        'tipe' => 'Triplek',
                    ];
                }
                // Platform Loop
                foreach ($prod->platformHasilHp as $t) {
                    $u = $t->barangSetengahJadi->ukuran ?? null;
                    $p = $u->panjang ?? 0;
                    $l = $u->lebar ?? 0;
                    $tebal = $u->tebal ?? 0;
                    $banyak = $t->isi;
                    $kubikasi = ($p * $l * $tebal * $banyak) / 1000000000;

                    $hasil[] = [
                        'no_palet' => $t->no_palet,
                        'p' => $p,
                        'l' => $l,
                        't' => $tebal,
                        'isi' => $banyak,
                        'jenis_kayu' => $t->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? '-',
                        'kwalitas' => strtoupper('PLATFORM ' . ($t->barangSetengahJadi->grade->nama_grade ?? '-')),
                        'nama_barang' => $t->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? 'Platform',
                        'kubikasi' => round($kubikasi, 4),
                        'tipe' => 'Platform',
                    ];
                }
            }

            $materialUsage = [];
            foreach ($bahanPenolongList as $bp) {
                $sum = 0;
                foreach ($records as $prod) {
                    $sum += $prod->bahanPenolongHp->where('nama_bahan', $bp->nama_bahan_penolong)->sum('jumlah');
                }

                $category = 'Bahan';
                foreach ($dempulNames as $dn) {
                    if (stripos($bp->nama_bahan_penolong, $dn) !== false) {
                        $category = 'Bahan Dempul';
                        break;
                    }
                }

                $materialUsage[] = [
                    'kategori' => $category,
                    'nama' => $bp->nama_bahan_penolong,
                    'banyak' => $sum,
                    'harga' => $bp->harga ?: 0,
                    'total' => $sum * ($bp->harga ?: 0),
                ];
            }

            $totalPekerja = 0;
            foreach ($records as $prod) {
                $totalPekerja += $prod->detailPegawaiHp->count();
            }

            $this->dataHp[] = [
                'machine' => $machineName,
                'tanggal' => Carbon::parse($tanggal)->format('d F Y'),
                'hasil' => $hasil,
                'material_usage' => $materialUsage,
                'total_pekerja' => $totalPekerja,
                'harga_pekerja' => $hargaPegawai,
                'penyusutan' => 1905000,
                'bulanan' => 220000,
            ];
        }
    }
}
