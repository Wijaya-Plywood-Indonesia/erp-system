<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\DatePicker;
use App\Exports\LaporanPotSikuExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProduksiPotSiku;
use App\Models\Target;
use Carbon\Carbon;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use UnitEnum;

class LaporanPotSiku extends Page
{
    use InteractsWithForms;
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected string $view = 'filament.pages.laporan-pot-siku';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Pot Siku';
    protected static ?int $navigationSort = 6;

    public $dataSiku = [];
    public $tanggal = null;

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
                ->visible(fn() => !empty($this->dataSiku)),
        ];
    }

    public function exportExcel()
    {
        try {
            if (empty($this->dataSiku)) {
                throw new \Exception('Tidak ada data untuk diunduh.');
            }

            $tglFile = Carbon::parse($this->tanggal)->format('d-m-Y');

            return Excel::download(
                new LaporanPotSikuExport($this->dataSiku),
                "laporan-pot-siku-{$tglFile}.xlsx"
            );
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Export Excel')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->form->fill(['tanggal' => $this->tanggal]);
        $this->loadAllData();
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

    protected function roundToNearestHundred(float $number): int
    {
        $thousands = floor($number / 1000);
        $base = $thousands * 1000;
        $remainder = $number - $base;

        if ($remainder < 300)
            return $base;
        if ($remainder < 800)
            return $base + 500;

        return $base + 1000;
    }

    public function loadAllData()
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        $produksiList = ProduksiPotSiku::with([
            'pegawaiPotSiku.pegawai',
            'detailBarangDikerjakanPotSiku.jenisKayu',
            'detailBarangDikerjakanPotSiku.ukuran',
        ])
            ->whereDate('tanggal_produksi', $tanggal)
            ->get();

        $targetRef = Target::where('kode_ukuran', 'POT SIKU')->first();

        // Target lama (biarkan kalau masih dipakai di laporan lain)
        $stdTarget = $targetRef->target ?? 150;
        $stdJam = $targetRef->jam ?? 10;
        $stdPotonganHarga = $targetRef->potongan ?? 766.67;

        // ✅ TARGET BARU PER PEKERJA
        $targetPerPegawai = 300; // cm

        $this->dataSiku = [];

        foreach ($produksiList as $produksi) {
            $perPekerja = [];

            foreach ($produksi->pegawaiPotSiku as $p) {
                $details = $produksi->detailBarangDikerjakanPotSiku
                    ->where('id_pegawai_pot_siku', $p->id);

                $hasilIndividu = (int) $details->sum('tinggi');

                // 🔥 hitung berdasarkan target 300
                $selisihIndividu = $targetPerPegawai - $hasilIndividu;

                $potongan = 0;
                if ($selisihIndividu > 0) {
                    $potongan = $this->roundToNearestHundred(
                        $selisihIndividu * $stdPotonganHarga
                    );
                }

                $detailTabel = [];
                foreach ($details as $d) {
                    $detailTabel[] = [
                        'jenis_kayu' => $d->jenisKayu->nama_kayu ?? 'Tidak Terdata',
                        'ukuran' => $d->ukuran->nama_ukuran ?? '-',
                        'kw' => $d->kw ?? '-',
                        'tinggi' => $d->tinggi,
                    ];
                }

                $perPekerja[] = [
                    'kode_pegawai' => $p->pegawai->kode_pegawai ?? '-',
                    'nama_pegawai' => $p->pegawai->nama_pegawai ?? '-',
                    'jam_masuk' => $p->masuk ? Carbon::parse($p->masuk)->format('H:i') : '-',
                    'jam_pulang' => $p->pulang ? Carbon::parse($p->pulang)->format('H:i') : '-',
                    'ijin' => $p->ijin ?? '-',
                    'ket' => $p->ket ?? '-',
                    'hasil' => $hasilIndividu,
                    'target' => $targetPerPegawai, // 🔥 penting untuk progress bar
                    'selisih' => $selisihIndividu > 0 ? $selisihIndividu : 0,
                    'potongan_target' => $potongan,
                    'detail_barang' => $detailTabel,
                ];
            }

            $this->dataSiku[] = [
                'tanggal' => Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y'),
                'kendala' => $produksi->kendala ?? 'Tidak ada kendala.',
                'target_harian' => $stdTarget,
                'jam_kerja' => $stdJam,
                'pekerja_list' => $perPekerja,
            ];
        }
    }
}
