<?php

namespace App\Filament\Pages;

use App\Actions\HitungPotonganProduksiAction;
use App\DataTransferObjects\PekerjaKerjaInput;
use App\Enums\Mesin;
use App\Exports\LaporanProduksiKediExport;
use App\Models\ProduksiKedi;
use App\Models\Target;
use App\Services\Target\TargetResolverFactory;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class LaporanKedi extends Page
{
    use HasPageShield;
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static UnitEnum|string|null $navigationGroup = 'Laporan';

    protected static ?string $title = 'Laporan Produksi Kedi';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.laporan-kedi';

    protected static bool $shouldRegisterNavigation = false;

    public array $dataKedi = [];

    public ?string $tanggal = null;

    public bool $isLoading = false;

    /**
     * Rekap potongan gabungan per status (BONGKAR/MASUK) untuk seluruh
     * tanggal terpilih. Logic-nya sama persis dengan sheet "Potongan"
     * di Excel export (LaporanKediPotonganSheet::buildAggregatedPotongan),
     * tapi di-duplikasi di sini supaya file export tidak perlu diubah.
     *
     * Struktur tiap elemen:
     * ['label' => string, 'summary' => array|null, 'items' => array, 'total' => int]
     */
    public array $potonganGroups = [];

    public int $totalPotonganSemua = 0;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->form->fill([
            'tanggal' => $this->tanggal,
        ]);
        $this->loadAllData();
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make(3)
                ->schema([
                    DatePicker::make('tanggal')
                        ->label('Pilih Tanggal')
                        ->format('Y-m-d')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->default(now())
                        ->live()
                        ->afterStateUpdated(function ($state) {
                            $this->tanggal = $state;
                            $this->loadAllData();
                        }),
                    Actions::make([
                        Action::make('filter')
                            ->label('Tampilkan Laporan')
                            ->icon('heroicon-o-magnifying-glass')
                            ->action(function () {
                                $data = $this->form->getState();
                                $this->tanggal = $data['tanggal'];
                                $this->loadAllData();
                            }),
                    ])->alignEnd(),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export ke Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportToExcel'),
        ];
    }

    public function exportToExcel()
    {
        if (empty($this->dataKedi)) {
            Notification::make()
                ->title('Gagal Export')
                ->body('Tidak ada data Produksi Kedi untuk rentang tanggal ini.')
                ->danger()
                ->send();

            return;
        }

        $produksiForPotongan = ProduksiKedi::with([
            'detailBongkarKedi.jenisKayu',
            'detailMasukKedi.jenisKayu',
            'detailPegawaiKedi.pegawai',
        ])
            ->whereDate('tanggal_actual_bongkar', $this->tanggal)
            ->orderBy('tanggal_actual_bongkar')
            ->get();

        $filename = 'Laporan-Produksi-Kedi-'.$this->tanggal.'.xlsx';

        return Excel::download(
            new LaporanProduksiKediExport($this->dataKedi, $produksiForPotongan),
            $filename
        );
    }

    public function loadAllData(): void
    {
        $this->isLoading = true;

        $produksiList = ProduksiKedi::with([
            'mesin',
            'detailMasukKedi.ukuran',
            'detailMasukKedi.jenisKayu',
            'detailBongkarKedi.ukuran',
            'detailBongkarKedi.jenisKayu',
            'detailPegawaiKedi.pegawai',
            'validasiTerakhir',
            'kendalaKedis.mesin',
        ])
            ->whereDate('tanggal_actual_bongkar', $this->tanggal)
            ->orderBy('tanggal_actual_bongkar')
            ->get();

        $this->dataKedi = [];
        $this->potonganGroups = [];
        $this->totalPotonganSemua = 0;

        if ($produksiList->isEmpty()) {
            Notification::make()
                ->title('Data tidak ditemukan')
                ->body('Tidak ada data Produksi Kedi pada tanggal ini.')
                ->warning()
                ->send();
        }

        foreach ($produksiList as $produksi) {
            $status = strtolower($produksi->status);

            $detailMasuk = $produksi->detailMasukKedi
                ->groupBy(fn ($d) => $d->id_ukuran.'-'.$d->id_jenis_kayu.'-'.$d->kw)
                ->map(fn ($group) => [
                    'no_palet' => $group->pluck('no_palet')->unique()->implode(', '),
                    'mesin' => $produksi->mesin?->nama_mesin ?? '-',
                    'ukuran' => $group->first()->ukuran?->dimensi ?? '-',
                    'jenis_kayu' => $group->first()->jenisKayu?->nama_kayu ?? '-',
                    'kw' => $group->first()->kw,
                    'jumlah' => $group->sum('jumlah'),
                    'rencana_bongkar' => $produksi->rencana_bongkar
                        ? Carbon::parse($produksi->rencana_bongkar)->format('d/m/Y')
                        : '-',
                ])->values()->toArray();

            $detailBongkar = $produksi->detailBongkarKedi
                ->groupBy(fn ($d) => $d->id_ukuran.'-'.$d->id_jenis_kayu.'-'.$d->kw)
                ->map(fn ($group) => [
                    'no_palet' => $group->pluck('no_palet')->unique()->implode(', '),
                    'mesin' => $produksi->mesin?->nama_mesin ?? '-',
                    'ukuran' => $group->first()->ukuran?->dimensi ?? '-',
                    'jenis_kayu' => $group->first()->jenisKayu?->nama_kayu ?? '-',
                    'kw' => $group->first()->kw,
                    'jumlah' => $group->sum('jumlah'),
                ])->values()->toArray();

            $totalPalet = $produksi->status === 'bongkar'
                ? $produksi->detailBongkarKedi->count()
                : null;

            $this->dataKedi[] = [
                'id' => $produksi->id,
                'tanggal_masuk' => $produksi->tanggal ? Carbon::parse($produksi->tanggal)->format('d/m/Y') : '-',
                'tanggal_keluar' => $produksi->tanggal_actual_bongkar
                    ? Carbon::parse($produksi->tanggal_actual_bongkar)->format('d/m/Y')
                    : '-',
                'tanggal_actual_bongkar' => $produksi->tanggal_actual_bongkar
                    ? Carbon::parse($produksi->tanggal_actual_bongkar)->format('d/m/Y')
                    : null,
                'status' => $produksi->status,
                'detail_masuk' => $detailMasuk,
                'detail_bongkar' => $detailBongkar,
                'validasi_terakhir' => $produksi->validasiTerakhir?->status ?? '-',
                'validasi_oleh' => $produksi->validasiTerakhir?->role ?? '-',
                'total_pekerja' => $produksi->detailPegawaiKedi->count(),
                'ongkos_mesin' => (float) ($produksi->mesin?->ongkos_mesin ?? 0),
                'total_palet' => $totalPalet,
                'kendala_kedis' => $produksi->kendalaKedis->map(fn ($k) => [
                    'tanggal' => $k->waktu_mulai ? Carbon::parse($k->waktu_mulai)->format('d/m/Y') : '-',
                    'mesin' => $k->mesin?->nama_mesin ?? '-',
                    'waktu_mulai' => $k->waktu_mulai ? Carbon::parse($k->waktu_mulai)->format('H:i') : '-',
                    'waktu_selesai' => $k->waktu_selesai ? Carbon::parse($k->waktu_selesai)->format('H:i') : '-',
                    'durasi_menit' => $k->durasi_menit,
                    'kendala' => $k->kendala,
                ])->toArray(),
            ];
        }

        // --- Rekap potongan digabung untuk SELURUH tanggal ---
        if ($produksiList->isNotEmpty()) {
            $built = $this->buildAggregatedPotongan($produksiList);
            $rows = collect($built['rows']);

            $this->potonganGroups = $rows->groupBy('hasil')->map(function ($items, $label) use ($built) {
                return [
                    'label' => $label,
                    'summary' => $built['summary'][$label] ?? null,
                    'items' => $items->values()->toArray(),
                    'total' => $items->sum('potongan_targ'),
                ];
            })->values()->toArray();

            $this->totalPotonganSemua = (int) $rows->sum('potongan_targ');
        }

        $this->isLoading = false;
    }

    /**
     * Gabungkan hasil produksi & jam kerja dari semua sesi ProduksiKedi
     * dalam satu hari untuk status yang sama, lalu hitung target/potongan
     * SEKALI menggunakan total gabungan tersebut.
     *
     * Logic ini sama persis dengan LaporanKediPotonganSheet::buildAggregatedPotongan
     * di app/Exports/LaporanProduksiKediExport.php — supaya angka yang tampil
     * di halaman ini konsisten dengan yang di-export ke Excel.
     *
     * @return array{rows: array, summary: array<string, array>}
     */
    private function buildAggregatedPotongan(Collection $produksiCollection): array
    {
        $groups = $produksiCollection->groupBy(fn ($produksi) => $produksi->status);

        $results = [];
        $summaries = [];

        foreach ($groups as $status => $groupProduksi) {
            $totalHasil = 0;
            $daftarKayu = [];

            foreach ($groupProduksi as $produksi) {
                if ($status === 'bongkar' && $produksi->detailBongkarKedi) {
                    $totalHasil += $produksi->detailBongkarKedi->count();
                    $kayu = $produksi->detailBongkarKedi->first()->jenisKayu->nama_kayu ?? null;
                    if ($kayu) {
                        $daftarKayu[$kayu] = true;
                    }
                } elseif ($status === 'masuk' && $produksi->detailMasukKedi) {
                    $totalHasil += $produksi->detailMasukKedi->sum('jumlah');
                    $kayu = $produksi->detailMasukKedi->first()->jenisKayu->nama_kayu ?? null;
                    if ($kayu) {
                        $daftarKayu[$kayu] = true;
                    }
                }
            }

            $labelDivisi = $status === 'bongkar' ? 'KEDI (BONGKAR)' : 'KEDI (MASUK)';
            if (! empty($daftarKayu)) {
                $labelDivisi .= ' - '.implode(', ', array_keys($daftarKayu));
            }

            $uniquePegawai = [];

            foreach ($groupProduksi as $produksi) {
                if (! $produksi->detailPegawaiKedi) {
                    continue;
                }

                $tanggalStr = Carbon::parse($produksi->tanggal_actual_bongkar ?? $produksi->tanggal ?? now())->format('Y-m-d');

                foreach ($produksi->detailPegawaiKedi as $dp) {
                    if (! $dp->pegawai) {
                        continue;
                    }

                    $kodep = $dp->pegawai->kode_pegawai ?? '-';

                    $masukAt = null;
                    $pulangAt = null;
                    if (! empty($dp->masuk) && ! empty($dp->pulang)) {
                        $masukAt = Carbon::parse($tanggalStr.' '.$dp->masuk);
                        $pulangAt = Carbon::parse($tanggalStr.' '.$dp->pulang);
                        if ($pulangAt->lessThan($masukAt)) {
                            $pulangAt->addDay();
                        }
                    }

                    if (! isset($uniquePegawai[$kodep])) {
                        $uniquePegawai[$kodep] = [
                            'pegawai' => $dp->pegawai,
                            'masuk' => $masukAt,
                            'pulang' => $pulangAt,
                            'ijin' => [],
                            'ket' => [],
                            'potongan_manual' => null,
                        ];
                    } else {
                        if ($masukAt && (! $uniquePegawai[$kodep]['masuk'] || $masukAt->lessThan($uniquePegawai[$kodep]['masuk']))) {
                            $uniquePegawai[$kodep]['masuk'] = $masukAt;
                        }
                        if ($pulangAt && (! $uniquePegawai[$kodep]['pulang'] || $pulangAt->greaterThan($uniquePegawai[$kodep]['pulang']))) {
                            $uniquePegawai[$kodep]['pulang'] = $pulangAt;
                        }
                    }

                    if ($dp->ijin) {
                        $uniquePegawai[$kodep]['ijin'][] = $dp->ijin;
                    }
                    if ($dp->ket) {
                        $uniquePegawai[$kodep]['ket'][] = $dp->ket;
                    }
                    if ($dp->potongan !== null) {
                        $uniquePegawai[$kodep]['potongan_manual'] = $dp->potongan;
                    }
                }
            }

            $jumlahPekerja = count($uniquePegawai);

            $potonganPerPegawai = [];

            if ($status === 'bongkar') {
                $mesinEnum = Mesin::Bongkar;
                $strategi = $mesinEnum->strategiPembagian();

                $pekerjaInput = [];
                foreach ($uniquePegawai as $kodep => $p) {
                    $menitKerja = 0;
                    if ($p['masuk'] && $p['pulang']) {
                        $menitKerja = max(0, $p['masuk']->diffInMinutes($p['pulang']));
                    }
                    $pekerjaInput[] = new PekerjaKerjaInput(
                        idPegawai: $kodep,
                        menitKerja: (float) $menitKerja,
                    );
                }

                $action = new HitungPotonganProduksiAction;

                $hitung = $action->execute(
                    $mesinEnum,
                    $strategi,
                    $pekerjaInput,
                    (float) $totalHasil,
                );

                $potonganPerPegawai = $hitung?->potonganPerPegawai ?? [];

                $targetDisplay = null;
                if ($hitung) {
                    foreach (['targetAdjusted', 'targetNormal', 'target'] as $prop) {
                        if (isset($hitung->{$prop})) {
                            $targetDisplay = (float) $hitung->{$prop};
                            break;
                        }
                    }
                }

                $targetModelBongkar = TargetResolverFactory::make($mesinEnum)->resolve($mesinEnum->value, null, null);
                $jamNormal = $targetModelBongkar->jam ?? null;

                $totalMenitAktual = 0;
                foreach ($pekerjaInput as $pi) {
                    $totalMenitAktual += $pi->menitKerja;
                }
                $totalJamAktual = $totalMenitAktual / 60;
                $rataJamPerOrang = $jumlahPekerja > 0 ? ($totalJamAktual / $jumlahPekerja) : 0;

                $summaries[$labelDivisi] = [
                    'hasil' => (float) $totalHasil,
                    'target' => $targetDisplay,
                    'selisih' => $targetDisplay !== null ? ((float) $totalHasil - $targetDisplay) : null,
                    'satuan' => 'pcs',
                    'jam_normal' => $jamNormal !== null ? (float) $jamNormal : null,
                    'jam_aktual_total' => $totalJamAktual,
                    'jam_aktual_rata' => $rataJamPerOrang,
                ];
            } else {
                $kodeTargetDicari = 'MASUK';
                $targetRef = Target::where('kode_ukuran', $kodeTargetDicari)->first();

                $stdTarget = (int) ($targetRef->target ?? 0);
                $stdPotHarga = (int) ($targetRef->potongan ?? 0);

                $selisih = $totalHasil - $stdTarget;
                $potonganPerOrangLegacy = 0;

                if ($stdTarget > 0 && $selisih < 0 && $stdPotHarga > 0 && $jumlahPekerja > 0) {
                    $kekurangan = abs($selisih);
                    $totalPot = $kekurangan * $stdPotHarga;
                    $potonganRaw = $totalPot / $jumlahPekerja;

                    $ribuan = floor($potonganRaw / 1000);
                    $ratusan = $potonganRaw % 1000;

                    if ($ratusan < 300) {
                        $potonganPerOrangLegacy = $ribuan * 1000;
                    } elseif ($ratusan >= 300 && $ratusan < 800) {
                        $potonganPerOrangLegacy = ($ribuan * 1000) + 500;
                    } else {
                        $potonganPerOrangLegacy = ($ribuan + 1) * 1000;
                    }
                }

                foreach ($uniquePegawai as $kodep => $p) {
                    $potonganPerPegawai[$kodep] = $potonganPerOrangLegacy;
                }

                $jamNormalMasuk = $targetRef->jam ?? null;
                $totalMenitAktualMasuk = 0;
                foreach ($uniquePegawai as $kodep => $p) {
                    if ($p['masuk'] && $p['pulang']) {
                        $totalMenitAktualMasuk += max(0, $p['masuk']->diffInMinutes($p['pulang']));
                    }
                }
                $totalJamAktualMasuk = $totalMenitAktualMasuk / 60;
                $rataJamPerOrangMasuk = $jumlahPekerja > 0 ? ($totalJamAktualMasuk / $jumlahPekerja) : 0;

                $summaries[$labelDivisi] = [
                    'hasil' => (float) $totalHasil,
                    'target' => $stdTarget > 0 ? (float) $stdTarget : null,
                    'selisih' => $stdTarget > 0 ? ((float) $totalHasil - $stdTarget) : null,
                    'satuan' => 'pcs',
                    'jam_normal' => $jamNormalMasuk !== null ? (float) $jamNormalMasuk : null,
                    'jam_aktual_total' => $totalJamAktualMasuk,
                    'jam_aktual_rata' => $rataJamPerOrangMasuk,
                ];
            }

            foreach ($uniquePegawai as $kodep => $p) {
                $potonganFinal = $p['potongan_manual'] ?? ($potonganPerPegawai[$kodep] ?? 0);

                $results[] = [
                    'hasil' => $labelDivisi,
                    'kodep' => $kodep,
                    'nama' => $p['pegawai']->nama_pegawai ?? 'TANPA NAMA',
                    'masuk' => $p['masuk'] ? $p['masuk']->format('H:i:s') : '',
                    'pulang' => $p['pulang'] ? $p['pulang']->format('H:i:s') : '',
                    'ijin' => implode(', ', array_unique($p['ijin'])),
                    'keterangan' => implode(', ', array_unique($p['ket'])),
                    'potongan_targ' => (int) $potonganFinal,
                ];
            }
        }

        return [
            'rows' => $results,
            'summary' => $summaries,
        ];
    }
}
