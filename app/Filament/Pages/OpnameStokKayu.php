<?php

namespace App\Filament\Pages;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use App\Models\JenisKayu;
use App\Models\Lahan;
use App\Models\LogLogCore;
use App\Models\StokLogCore;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class OpnameStokKayu extends Page implements HasForms
{
    use InteractsWithSchemas, HasPageShield;

    protected static ?string $navigationLabel = 'Opname Kayu & Log Core';
    protected static UnitEnum|string|null $navigationGroup = 'Opname';
    protected static ?string $title = 'Opname Stok Kayu & Log Core';
    protected static ?int $navigationSort = 14;

    protected string $view = 'filament.pages.opname-stok-kayu';

    public string $activeTab = 'kayu'; // Options: 'kayu' | 'log_core'
    public ?array $data = [];

    public function mount(): void
    {
        $this->schema->fill();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetForm();
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        // =========================================================
                        // DATA PRODUK
                        // =========================================================
                        Select::make('id_lahan')
                            ->label('Lahan')
                            ->options(Lahan::orderBy('kode_lahan')->get()->mapWithKeys(fn($l) => [
                                $l->id => "{$l->kode_lahan} - {$l->nama_lahan}"
                            ]))
                            ->searchable()
                            ->required(fn() => $this->activeTab === 'kayu')
                            ->visible(fn() => $this->activeTab === 'kayu')
                            ->live()
                            ->afterStateUpdated(fn(Get $get, Set $set) => $this->loadStokSaatIni($get, $set)),

                        Select::make('id_jenis_kayu')
                            ->label('Jenis Kayu')
                            ->options(JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn(Get $get, Set $set) => $this->loadStokSaatIni($get, $set)),

                        Select::make('panjang')
                            ->label('Panjang (cm)')
                            ->options([130 => '130 cm', 260 => '260 cm'])
                            ->required()
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(fn(Get $get, Set $set) => $this->loadStokSaatIni($get, $set)),

                        // =========================================================
                        // STOK SAAT INI (READONLY)
                        // =========================================================
                        TextInput::make('stok_batang_sekarang')
                            ->label(fn() => $this->activeTab === 'kayu' ? 'Stok Batang (Saat Ini)' : 'Stok Qty (Saat Ini)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->default(0)
                            ->suffix(' Batang'),

                        TextInput::make('stok_kubikasi_sekarang')
                            ->label('Stok Kubikasi (Saat Ini)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn() => $this->activeTab === 'kayu')
                            ->default(0)
                            ->step(0.0001)
                            ->suffix(' m³'),

                        TextInput::make('nilai_stok_sekarang')
                            ->label('Nilai Stok (Poin)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn() => $this->activeTab === 'kayu')
                            ->default(0)
                            ->prefix('Rp '),

                        // =========================================================
                        // HASIL OPNAME (INPUT MANUAL)
                        // =========================================================
                        TextInput::make('stok_batang_baru')
                            ->label(fn() => $this->activeTab === 'kayu' ? 'Stok Batang (Hasil Opname)' : 'Stok Qty (Hasil Opname)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(0)
                            ->suffix(' Batang'),

                        TextInput::make('stok_kubikasi_baru')
                            ->label('Stok Kubikasi (Hasil Opname)')
                            ->numeric()
                            ->required(fn() => $this->activeTab === 'kayu')
                            ->visible(fn() => $this->activeTab === 'kayu')
                            ->minValue(0)
                            ->step(0.0001)
                            ->default(0)
                            ->suffix(' m³'),

                        TextInput::make('nilai_stok_baru')
                            ->label('Poin Baru (Hasil Opname)')
                            ->numeric()
                            ->required(fn() => $this->activeTab === 'kayu')
                            ->visible(fn() => $this->activeTab === 'kayu')
                            ->minValue(0)
                            ->default(0)
                            ->prefix('Rp '),
                    ]),

                Textarea::make('keterangan')
                    ->label('Keterangan Opname')
                    ->placeholder('Contoh: Opname fisik berkala, penyesuaian selisih stok')
                    ->rows(2)
                    ->columnSpanFull(),

                Actions::make([
                    Action::make('simpan')
                        ->label('Simpan Opname')
                        ->color('success')
                        ->icon('heroicon-o-check')
                        ->action(fn(Get $get) => $this->simpanOpname($get)),

                    Action::make('reset')
                        ->label('Reset')
                        ->color('gray')
                        ->icon('heroicon-o-arrow-path')
                        ->action(fn(Set $set) => $this->resetForm()),
                ])->fullWidth(),
            ])
            ->statePath('data');
    }

    private function loadStokSaatIni(Get $get, Set $set): void
    {
        $jenisKayuId = $get('id_jenis_kayu');
        $panjang = $get('panjang');

        if ($this->activeTab === 'kayu') {
            $lahanId = $get('id_lahan');
            if (!$lahanId || !$jenisKayuId || !$panjang) {
                $this->clearStokFields($set);
                return;
            }

            $summary = HppAverageSummarie::where('id_lahan', $lahanId)
                ->where('id_jenis_kayu', $jenisKayuId)
                ->where('panjang', $panjang)
                ->whereNull('grade')
                ->first();

            if ($summary) {
                $set('stok_batang_sekarang', $summary->stok_batang);
                $set('stok_kubikasi_sekarang', round($summary->stok_kubikasi, 4));
                $set('nilai_stok_sekarang', round($summary->nilai_stok, 2));

                $set('stok_batang_baru', $summary->stok_batang);
                $set('stok_kubikasi_baru', round($summary->stok_kubikasi, 4));
                $set('nilai_stok_baru', round($summary->nilai_stok, 2));
            } else {
                $this->clearStokFields($set);
            }
        } else {
            // TAB: LOG CORE
            if (!$jenisKayuId || !$panjang) {
                $set('stok_batang_sekarang', 0);
                $set('stok_batang_baru', 0);
                return;
            }

            $logCore = StokLogCore::where('id_jenis_kayu', $jenisKayuId)
                ->where('panjang', $panjang)
                ->first();

            $qty = $logCore ? $logCore->stok_qty : 0;
            $set('stok_batang_sekarang', $qty);
            $set('stok_batang_baru', $qty);
        }
    }

    private function simpanOpname(Get $get): void
    {
        if ($this->activeTab === 'kayu') {
            $this->simpanOpnameKayu($get);
        } else {
            $this->simpanOpnameLogCore($get);
        }
    }

    private function simpanOpnameKayu(Get $get): void
    {
        $lahanId = $get('id_lahan');
        $jenisKayuId = $get('id_jenis_kayu');
        $panjang = $get('panjang');

        if (!$lahanId || !$jenisKayuId || !$panjang) {
            Notification::make()->danger()->title('Data Tidak Lengkap')->body('Pilih Lahan, Jenis Kayu, dan Panjang.')->send();
            return;
        }

        $batangSekarang = (int) $get('stok_batang_sekarang');
        $batangBaru = (int) $get('stok_batang_baru');
        $kubikasiSekarang = (float) $get('stok_kubikasi_sekarang');
        $kubikasiBaru = (float) $get('stok_kubikasi_baru');
        $nilaiSekarang = (float) $get('nilai_stok_sekarang');
        $nilaiBaru = (float) $get('nilai_stok_baru');

        $selisihBatang = $batangBaru - $batangSekarang;
        $selisihKubikasi = $kubikasiBaru - $kubikasiSekarang;
        $selisihNilai = $nilaiBaru - $nilaiSekarang;

        if ($selisihBatang == 0 && $selisihKubikasi == 0 && $selisihNilai == 0) {
            Notification::make()->warning()->title('Tidak Ada Perubahan')->body('Stok kayu tidak berubah.')->send();
            return;
        }

        DB::transaction(function () use ($get, $lahanId, $jenisKayuId, $panjang, $batangBaru, $kubikasiBaru, $nilaiBaru, $selisihBatang, $selisihKubikasi, $selisihNilai) {
            $summary = HppAverageSummarie::firstOrNew([
                'id_lahan' => $lahanId,
                'id_jenis_kayu' => $jenisKayuId,
                'panjang' => $panjang,
                'grade' => null,
            ]);

            $beforeStok = $summary->stok_batang ?? 0;
            $beforeKubikasi = $summary->stok_kubikasi ?? 0;
            $beforeNilai = $summary->nilai_stok ?? 0;

            $summary->stok_batang = $batangBaru;
            $summary->stok_kubikasi = $kubikasiBaru;
            $summary->nilai_stok = $nilaiBaru;
            $summary->hpp_average = $kubikasiBaru > 0 ? round($nilaiBaru / $kubikasiBaru, 2) : 0;
            $summary->save();

            $keteranganLog = sprintf(
                "STOK OPNAME | %s | Batang: %s%d (%s%.4f m³) | Poin: Rp %s | User: %s",
                $get('keterangan') ?: 'Opname berkala',
                $selisihBatang > 0 ? '+' : '',
                $selisihBatang,
                $selisihKubikasi > 0 ? '+' : '',
                $selisihKubikasi,
                number_format($selisihNilai, 0, ',', '.'),
                Auth::user()->name
            );

            HppAverageLog::create([
                'id_lahan' => $lahanId,
                'id_jenis_kayu' => $jenisKayuId,
                'grade' => null,
                'panjang' => $panjang,
                'tanggal' => now(),
                'tipe_transaksi' => $selisihBatang >= 0 ? 'masuk' : 'keluar',
                'keterangan' => $keteranganLog,
                'total_batang' => abs($selisihBatang),
                'total_kubikasi' => round(abs($selisihKubikasi), 4),
                'harga' => $summary->hpp_average,
                'nilai_stok' => abs(round($selisihNilai, 2)),
                'stok_batang_before' => $beforeStok,
                'stok_kubikasi_before' => round($beforeKubikasi, 4),
                'nilai_stok_before' => round($beforeNilai, 2),
                'stok_batang_after' => $summary->stok_batang,
                'stok_kubikasi_after' => round($summary->stok_kubikasi, 4),
                'nilai_stok_after' => round($summary->nilai_stok, 2),
                'hpp_average' => $summary->hpp_average,
            ]);

            $this->syncTempatKayu($lahanId);
        });

        Notification::make()->success()->title('✅ Opname Kayu Berhasil')->send();
        $this->resetForm();
    }

    private function simpanOpnameLogCore(Get $get): void
    {
        $jenisKayuId = $get('id_jenis_kayu');
        $panjang = $get('panjang');

        if (!$jenisKayuId || !$panjang) {
            Notification::make()->danger()->title('Data Tidak Lengkap')->body('Pilih Jenis Kayu dan Panjang terlebih dahulu.')->send();
            return;
        }

        $qtySekarang = (float) $get('stok_batang_sekarang');
        $qtyBaru = (float) $get('stok_batang_baru');
        $selisih = $qtyBaru - $qtySekarang;

        if ($selisih == 0) {
            Notification::make()->warning()->title('Tidak Ada Perubahan')->body('Stok log core tidak berubah, opname tidak disimpan.')->send();
            return;
        }

        DB::transaction(function () use ($get, $jenisKayuId, $panjang, $qtySekarang, $qtyBaru, $selisih) {
            // 1. Ambil/Buat record Stok Log Core
            $logCore = StokLogCore::firstOrNew([
                'id_jenis_kayu' => $jenisKayuId,
                'panjang'       => $panjang,
            ]);

            $beforeQty = $logCore->stok_qty ?? 0;
            $beforeNilai = $logCore->nilai_stok ?? 0; // Sesuaikan jika tabel StokLogCore memiliki nilai_stok

            // Update Qty Stok Utama
            $logCore->stok_qty = $qtyBaru;
            $logCore->save();

            // 2. Format Keterangan Log Opname
            $keteranganLog = sprintf(
                "STOK OPNAME LOG CORE | %s | Qty: %s%d Batang | Oleh: %s",
                $get('keterangan') ?: 'Penyesuaian Stok Log Core',
                $selisih > 0 ? '+' : '',
                $selisih,
                Auth::user()->name
            );

            LogLogCore::create([
                'id_jenis_kayu'     => $jenisKayuId,
                'panjang'           => $panjang,
                'tanggal'           => now(),
                'tipe_transaksi'    => $selisih >= 0 ? 'masuk' : 'keluar',
                'keterangan'        => $keteranganLog,
                'referensi_type'    => null,
                'referensi_id'      => null,
                'qty'               => abs($selisih),
                'harga_satuan'      => 0, // Set 0 atau sesuaikan jika ada perhitungan harga/poin Log Core
                'nilai'             => 0,
                'stok_qty_before'   => $beforeQty,
                'nilai_stok_before' => $beforeNilai,
                'stok_qty_after'    => $qtyBaru,
                'nilai_stok_after'  => $beforeNilai, // Sesuaikan jika ada perubahan nilai total
                'id_validator'      => Auth::id(),
                'tanggal_validasi'  => now(),
            ]);
        });

        Notification::make()
            ->success()
            ->title('Opname Log Core Berhasil')
            ->body('Stok log core berhasil diperbarui dan telah dicatat ke Log Log Core.')
            ->send();

        $this->resetForm();
    }

    private function syncTempatKayu(int $lahanId): void
    {
        $totalBatang = HppAverageSummarie::where('id_lahan', $lahanId)
            ->whereNull('grade')
            ->sum('stok_batang');

        $kayuMasuk = \App\Models\KayuMasuk::whereHas('detailTurusanKayus', fn($q) => $q->where('lahan_id', $lahanId))
            ->latest()
            ->first();

        if ($kayuMasuk) {
            \App\Models\TempatKayu::updateOrCreate(
                ['id_lahan' => $lahanId, 'id_kayu_masuk' => $kayuMasuk->id],
                ['jumlah_batang' => $totalBatang]
            );
        }
    }

    private function clearStokFields(Set $set): void
    {
        $set('stok_batang_sekarang', 0);
        $set('stok_kubikasi_sekarang', 0);
        $set('nilai_stok_sekarang', 0);
        $set('stok_batang_baru', 0);
        $set('stok_kubikasi_baru', 0);
        $set('nilai_stok_baru', 0);
    }

    private function resetForm(): void
    {
        $this->schema->fill([
            'id_lahan' => null,
            'id_jenis_kayu' => null,
            'panjang' => null,
            'stok_batang_sekarang' => 0,
            'stok_kubikasi_sekarang' => 0,
            'nilai_stok_sekarang' => 0,
            'stok_batang_baru' => 0,
            'stok_kubikasi_baru' => 0,
            'nilai_stok_baru' => 0,
            'keterangan' => '',
        ]);
    }
}
