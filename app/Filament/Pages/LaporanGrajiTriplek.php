<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\ProduksiGrajitriplek;
// Pastikan Anda membuat export class ini nanti
use App\Exports\LaporanGrajiTriplekExport; 
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use UnitEnum;

class LaporanGrajiTriplek extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Laporan Graji Triplek';
    protected string $view = 'filament.pages.laporan-graji-triplek';
    protected static ?int $navigationSort = 9;

    public array $data = ['tanggal' => null];
    public array $laporan = [];
    public bool $isLoading = false;
    public array $kendalaList = [];

    public function mount(): void
    {
        $this->data['tanggal'] = now()->format('Y-m-d');
        $this->form->fill($this->data);
        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Pilih Tanggal Laporan Graji Triplek')
                    ->native(false)
                    ->format('Y-m-d')
                    ->displayFormat('d/m/Y')
                    ->live()
                    ->closeOnDateSelection()
                    ->afterStateUpdated(fn($state) => $this->onTanggalUpdated($state))
                    ->required()
                    ->maxDate(now())
                    ->default(now()->format('Y-m-d'))
                    ->suffixIcon('heroicon-o-calendar')
                    ->suffixIconColor('primary'),
            ])
            ->statePath('data')
            ->columns(1);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->refresh()),

            Action::make('exportExcel')
                ->label('Download Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn() => $this->exportExcel())
                ->visible(fn() => !empty($this->laporan)),
        ];
    }

    public function onTanggalUpdated($state): void
    {
        if (empty($state)) {
            $this->data['tanggal'] = now()->format('Y-m-d');
            $this->loadData();
            return;
        }

        try {
            if ($state instanceof Carbon) {
                $tanggal = $state->format('Y-m-d');
            } elseif (is_string($state)) {
                if (str_contains($state, '/')) {
                    $tanggal = Carbon::createFromFormat('d/m/Y', $state)->format('Y-m-d');
                } else {
                    $tanggal = Carbon::parse($state)->format('Y-m-d');
                }
            } else {
                $tanggal = now()->format('Y-m-d');
            }
            $this->data['tanggal'] = $tanggal;
            $this->loadData();
        } catch (Exception $e) {
            Log::error('Error parsing date in onTanggalUpdated', ['state' => $state, 'error' => $e->getMessage()]);
            $this->data['tanggal'] = now()->format('Y-m-d');
            $this->loadData();
        }
    }

    public function loadData(): void
    {
        try {
            $this->isLoading = true;
            $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');
            $this->laporan = [];
            $this->kendalaList = [];

            $raw = ProduksiGrajitriplek::with([
                'pegawaiGrajiTriplek.pegawaiGrajiTriplek',
                'hasilGrajiTriplek.barangSetengahJadiHp.ukuran',
                'hasilGrajiTriplek.barangSetengahJadiHp.jenisBarang',
                'hasilGrajiTriplek.barangSetengahJadiHp.grade.kategoriBarang',
                'kendalaGrajiTripleks.mesin',
            ])
                ->whereDate('tanggal_produksi', $tanggal)
                ->get();

            if ($raw->isNotEmpty()) {
                $this->laporan = $this->transformData($raw);

                foreach ($raw as $produksi) {
                    if ($produksi->kendalaGrajiTripleks->isNotEmpty()) {
                        foreach ($produksi->kendalaGrajiTripleks as $k) {
                            $this->kendalaList[] = [
                                'tanggal' => $k->waktu_mulai ? Carbon::parse($k->waktu_mulai)->format('d/m/Y') : '-',
                                'mesin' => $k->mesin?->nama_mesin ?? '-',
                                'waktu_mulai' => $k->waktu_mulai ? Carbon::parse($k->waktu_mulai)->format('H:i') : '-',
                                'waktu_selesai' => $k->waktu_selesai ? Carbon::parse($k->waktu_selesai)->format('H:i') : '-',
                                'durasi_menit' => $k->durasi_menit,
                                'kendala' => $k->kendala,
                                'foto_kendala' => $k->foto_kendala,
                                'foto_selesai' => $k->foto_selesai,
                                'status' => $k->status,
                            ];
                        }
                    }
                }
            } else {
                Notification::make()
                    ->warning()
                    ->title('Tidak Ada Data')
                    ->body('Data tidak ditemukan untuk tanggal tersebut.')
                    ->send();
            }
        } catch (Exception $e) {
            Log::error('Error loading graji triplek data', ['message' => $e->getMessage()]);
        } finally {
            $this->isLoading = false;
        }
    }

    private function transformData($collection): array
    {
        $result = [];

        foreach ($collection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');
            $jumlahPekerja = $produksi->pegawaiGrajiTriplek->count();

            // Grouping hasil berdasarkan ukuran + jenis + grade melalui barangSetengahJadiHp
            $hasilGroups = $produksi->hasilGrajiTriplek
                ->groupBy(function($h) {
                    $b = $h->barangSetengahJadiHp;
                    return ($b->id_ukuran ?? 0) . '|' . ($b->id_jenis_barang ?? 0) . '|' . ($b->id_grade ?? 0);
                });

            foreach ($hasilGroups as $hasilItems) {
                $firstHasil = $hasilItems->first();
                $b = $firstHasil->barangSetengahJadiHp;
                if (!$b) continue;

                $ukuran = $b->ukuran;
                $jenis = $b->jenisBarang;
                $grade = $b->grade;

                $byk = (int) $hasilItems->sum('isi');
                $p = $ukuran->panjang ?? 0;
                $l = $ukuran->lebar ?? 0;
                $t = $ukuran->tebal ?? 0;

                // Hitung volume M3 (asumsi dalam milimeter ke meter kubik)
                $m3 = ($p * $l * $t * $byk) / 1000000000;

                $result[] = [
                    'tanggal' => $tanggal,
                    'p' => $p,
                    'l' => $l,
                    't' => $t,
                    'jenis' => strtoupper($jenis->nama_jenis_barang ?? '-'),
                    'grade' => strtoupper($grade->nama_grade ?? '-'),
                    'byk' => $byk,
                    'm3' => round($m3, 4),
                    'ttl_pkj' => $jumlahPekerja,
                    'shift' => $produksi->shift,
                ];
            }
        }

        return $result;
    }

    public function refresh(): void
    {
        $this->loadData();
        Notification::make()->success()->title('Data Diperbarui')->send();
    }

    public function exportExcel()
    {
        try {
            if (empty($this->laporan)) {
                throw new Exception('Tidak ada data untuk diunduh.');
            }

            $detail = [];
            $summary = [];

            foreach ($this->laporan as $row) {
                $detail[] = [
                    'tanggal' => $row['tanggal'],
                    'p' => $row['p'],
                    'l' => $row['l'],
                    't' => $row['t'],
                    'jenis' => $row['jenis'],
                    'grade' => $row['grade'],
                    'byk' => $row['byk'],
                ];
            }

            // Summary per shift/tanggal
            $tanggal = $this->data['tanggal'] ?? now()->format('Y-m-d');
            $raw = ProduksiGrajitriplek::with(['pegawaiGrajiTriplek'])
                ->whereDate('tanggal_produksi', $tanggal)
                ->get();

            foreach ($raw as $prod) {
                $summary[] = [
                    'tanggal' => Carbon::parse($prod->tanggal_produksi)->format('d/m/Y'),
                    'ttl_pkj' => $prod->pegawaiGrajiTriplek->count(),
                ];
            }

            $reportData = [
                'detail' => $detail,
                'summary' => $summary,
            ];

            $tglFile = Carbon::parse($tanggal)->format('d-m-Y');

            return Excel::download(
                new LaporanGrajiTriplekExport($reportData, $tanggal),
                "laporan-graji-triplek-{$tglFile}.xlsx"
            );
        } catch (Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Export Excel')
                ->body($e->getMessage())
                ->send();
        }
    }
}
