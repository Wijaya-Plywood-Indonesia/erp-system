<?php

namespace App\Filament\Resources\OpnameStokKayus\Schemas;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use App\Models\JenisKayu;
use App\Models\Lahan;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OpnameStokKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // =========================================================
            // SECTION 1: PILIH LAHAN & PRODUK
            // =========================================================
            Section::make('Pilih Lahan & Produk')
                ->description('Pilih lahan dan produk kayu yang akan diopname')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('id_lahan')
                                ->label('Lahan')
                                ->options(Lahan::orderBy('kode_lahan')->get()->mapWithKeys(fn($l) => [
                                    $l->id => "{$l->kode_lahan} - {$l->nama_lahan}"
                                ]))
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::loadCurrentStok($get, $set);
                                }),

                            Select::make('id_jenis_kayu')
                                ->label('Jenis Kayu')
                                ->options(JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::loadCurrentStok($get, $set);
                                }),

                            Select::make('panjang')
                                ->label('Panjang (cm)')
                                ->options([
                                    130 => '130 cm',
                                    260 => '260 cm',
                                ])
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::loadCurrentStok($get, $set);
                                }),
                        ]),
                ]),

            // =========================================================
            // SECTION 2: STOK SAAT INI (SISTEM)
            // =========================================================
            Section::make('Stok Saat Ini (Sistem)')
                ->description('Data stok sebelum dilakukan opname')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('stok_batang_sistem')
                                ->label('Stok Batang')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->default(0)
                                ->suffix(' Batang'),

                            TextInput::make('stok_kubikasi_sistem')
                                ->label('Stok Kubikasi (m³)')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->default(0)
                                ->step(0.0001)
                                ->suffix(' m³'),

                            TextInput::make('hpp_average_sistem')
                                ->label('HPP Average')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->default(0)
                                ->prefix('Rp ')
                                ->suffix(' /m³'),
                        ]),
                ]),

            // =========================================================
            // SECTION 3: HASIL OPNAME (FISIK)
            // =========================================================
            Section::make('Hasil Opname Fisik')
                ->description('Masukkan hasil pengecekan stok fisik di lapangan')
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('stok_batang_fisik')
                                ->label('Stok Batang (Hasil Opname)')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->live()
                                ->suffix(' Batang')
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::hitungSelisih($get, $set);
                                }),

                            TextInput::make('stok_kubikasi_fisik')
                                ->label('Stok Kubikasi (Hasil Opname)')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->step(0.0001)
                                ->live()
                                ->suffix(' m³')
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::hitungSelisih($get, $set);
                                }),
                        ]),
                ]),

            // =========================================================
            // SECTION 4: SELISIH OPNAME
            // =========================================================
            Section::make('Selisih Opname')
                ->description('Perbedaan antara stok sistem dengan hasil opname')
                ->icon('heroicon-o-arrows-right-left')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('selisih_batang')
                                ->label('Selisih Batang')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->default(0)
                                ->suffix(' Batang')
                                ->helperText('(+) = Kelebihan stok, (-) = Kekurangan stok')
                                ->color(fn(Get $get) => $get('selisih_batang') > 0 ? 'success' : ($get('selisih_batang') < 0 ? 'danger' : 'gray')),

                            TextInput::make('selisih_kubikasi')
                                ->label('Selisih Kubikasi (m³)')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->default(0)
                                ->step(0.0001)
                                ->suffix(' m³')
                                ->color(fn(Get $get) => $get('selisih_kubikasi') > 0 ? 'success' : ($get('selisih_kubikasi') < 0 ? 'danger' : 'gray')),
                        ]),

                    TextInput::make('nilai_stok_baru')
                        ->label('Nilai Stok Baru')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false)
                        ->default(0)
                        ->prefix('Rp ')
                        ->helperText('Nilai stok baru = Kubikasi × HPP'),
                ]),

            // =========================================================
            // SECTION 5: KETERANGAN
            // =========================================================
            Section::make('Keterangan Opname')
                ->description('Informasi tambahan tentang opname ini')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    TextA::make('keterangan')
                        ->label('Keterangan')
                        ->placeholder('Contoh: Opname bulan April 2026, Koreksi stok setelah produksi, Penyesuaian fisik, dll')
                        ->required()
                        ->rows(3),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('petugas')
                                ->label('Petugas Opname')
                                ->default(Auth::user()->name)
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('tanggal_opname')
                                ->label('Tanggal Opname')
                                ->default(now()->format('d/m/Y H:i'))
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                ]),

            // =========================================================
            // SECTION 6: ACTION BUTTONS
            // =========================================================
            Actions::make([
                Action::make('simpan')
                    ->label('Simpan Opname')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->action(function (Get $get, Set $set) {
                        return self::simpanOpname($get, $set);
                    }),

                Action::make('reset')
                    ->label('Reset Form')
                    ->color('gray')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Get $get, Set $set) {
                        self::resetForm($get, $set);
                    }),
            ])->fullWidth(),
        ]);
    }

    // =========================================================
    // LOAD STOK SAAT INI
    // =========================================================
    private static function loadCurrentStok(Get $get, Set $set): void
    {
        $lahanId = $get('id_lahan');
        $jenisKayuId = $get('id_jenis_kayu');
        $panjang = $get('panjang');

        if (!$lahanId || !$jenisKayuId || !$panjang) {
            $set('stok_batang_sistem', 0);
            $set('stok_kubikasi_sistem', 0);
            $set('hpp_average_sistem', 0);
            $set('stok_batang_fisik', 0);
            $set('stok_kubikasi_fisik', 0);
            $set('selisih_batang', 0);
            $set('selisih_kubikasi', 0);
            $set('nilai_stok_baru', 0);
            return;
        }

        $summary = HppAverageSummarie::where('id_lahan', $lahanId)
            ->where('id_jenis_kayu', $jenisKayuId)
            ->where('panjang', $panjang)
            ->whereNull('grade')
            ->first();

        if ($summary) {
            $stokBatang = $summary->stok_batang;
            $stokKubikasi = $summary->stok_kubikasi;
            $hppAverage = $summary->hpp_average;

            $set('stok_batang_sistem', $stokBatang);
            $set('stok_kubikasi_sistem', round($stokKubikasi, 4));
            $set('hpp_average_sistem', $hppAverage);
            $set('stok_batang_fisik', $stokBatang);
            $set('stok_kubikasi_fisik', round($stokKubikasi, 4));
            $set('selisih_batang', 0);
            $set('selisih_kubikasi', 0);

            $nilaiBaru = round($stokKubikasi * $hppAverage, 2);
            $set('nilai_stok_baru', $nilaiBaru);
        } else {
            $set('stok_batang_sistem', 0);
            $set('stok_kubikasi_sistem', 0);
            $set('hpp_average_sistem', 0);
            $set('stok_batang_fisik', 0);
            $set('stok_kubikasi_fisik', 0);
            $set('selisih_batang', 0);
            $set('selisih_kubikasi', 0);
            $set('nilai_stok_baru', 0);
        }
    }

    // =========================================================
    // HITUNG SELISIH
    // =========================================================
    private static function hitungSelisih(Get $get, Set $set): void
    {
        $batangSistem = (int) $get('stok_batang_sistem') ?? 0;
        $batangFisik = (int) $get('stok_batang_fisik') ?? 0;
        $kubikasiSistem = (float) $get('stok_kubikasi_sistem') ?? 0;
        $kubikasiFisik = (float) $get('stok_kubikasi_fisik') ?? 0;
        $hppAverage = (float) $get('hpp_average_sistem') ?? 0;

        $selisihBatang = $batangFisik - $batangSistem;
        $selisihKubikasi = $kubikasiFisik - $kubikasiSistem;

        $set('selisih_batang', $selisihBatang);
        $set('selisih_kubikasi', round($selisihKubikasi, 4));

        // Hitung nilai stok baru
        $nilaiBaru = round($kubikasiFisik * $hppAverage, 2);
        $set('nilai_stok_baru', $nilaiBaru);
    }

    // =========================================================
    // SIMPAN OPNAME
    // =========================================================
    private static function simpanOpname(Get $get, Set $set): void
    {
        $lahanId = $get('id_lahan');
        $jenisKayuId = $get('id_jenis_kayu');
        $panjang = $get('panjang');

        // Validasi
        if (!$lahanId || !$jenisKayuId || !$panjang) {
            Notification::make()
                ->danger()
                ->title('Data Tidak Lengkap')
                ->body('Silakan pilih Lahan, Jenis Kayu, dan Panjang terlebih dahulu.')
                ->send();
            return;
        }

        $selisihBatang = (int) $get('selisih_batang');
        $selisihKubikasi = (float) $get('selisih_kubikasi');
        $batangBaru = (int) $get('stok_batang_fisik');
        $kubikasiBaru = (float) $get('stok_kubikasi_fisik');
        $nilaiBaru = (float) $get('nilai_stok_baru');
        $keterangan = $get('keterangan');

        // Jika tidak ada perubahan
        if ($selisihBatang == 0 && $selisihKubikasi == 0) {
            Notification::make()
                ->warning()
                ->title('Tidak Ada Perubahan')
                ->body('Stok tidak berubah, opname tidak perlu dicatat.')
                ->send();
            return;
        }

        DB::transaction(function () use ($lahanId, $jenisKayuId, $panjang, $selisihBatang, $selisihKubikasi, $batangBaru, $kubikasiBaru, $nilaiBaru, $keterangan) {

            // Ambil summary yang sudah ada (UPDATE, bukan CREATE baru)
            $summary = HppAverageSummarie::where('id_lahan', $lahanId)
                ->where('id_jenis_kayu', $jenisKayuId)
                ->where('panjang', $panjang)
                ->whereNull('grade')
                ->first();

            if (!$summary) {
                // Buat baru jika belum ada
                $summary = new HppAverageSummarie();
                $summary->id_lahan = $lahanId;
                $summary->id_jenis_kayu = $jenisKayuId;
                $summary->panjang = $panjang;
                $summary->grade = null;
            }

            $before = [
                'btg' => $summary->stok_batang ?? 0,
                'm3' => $summary->stok_kubikasi ?? 0,
                'val' => $summary->nilai_stok ?? 0,
                'hpp' => $summary->hpp_average ?? 0,
            ];

            // Update stok
            $summary->stok_batang = $batangBaru;
            $summary->stok_kubikasi = $kubikasiBaru;
            $summary->nilai_stok = $nilaiBaru;
            $summary->hpp_average = $kubikasiBaru > 0 ? round($nilaiBaru / $kubikasiBaru, 2) : 0;
            $summary->save();

            // Tentukan tipe transaksi
            $tipeTransaksi = $selisihBatang > 0 ? 'masuk' : 'keluar';

            // Buat keterangan untuk log
            $keteranganLog = sprintf(
                "STOK OPNAME | %s | Selisih: %s%d batang (%s%.4f m³) | Petugas: %s",
                $keterangan ?: 'Opname berkala',
                $selisihBatang > 0 ? '+' : '',
                abs($selisihBatang),
                $selisihKubikasi > 0 ? '+' : '',
                abs($selisihKubikasi),
                Auth::user()->name
            );

            // Buat Log HPP
            $log = HppAverageLog::create([
                'id_lahan' => $lahanId,
                'id_jenis_kayu' => $jenisKayuId,
                'grade' => null,
                'panjang' => $panjang,
                'tanggal' => now(),
                'tipe_transaksi' => $tipeTransaksi,
                'keterangan' => $keteranganLog,
                'referensi_type' => 'OpnameStokKayu',
                'referensi_id' => null,
                'total_batang' => abs($selisihBatang),
                'total_kubikasi' => round(abs($selisihKubikasi), 4),
                'harga' => $summary->hpp_average,
                'nilai_stok' => abs(round($summary->nilai_stok - $before['val'], 2)),
                'stok_batang_before' => $before['btg'],
                'stok_kubikasi_before' => round($before['m3'], 4),
                'nilai_stok_before' => round($before['val'], 2),
                'stok_batang_after' => $summary->stok_batang,
                'stok_kubikasi_after' => round($summary->stok_kubikasi, 4),
                'nilai_stok_after' => round($summary->nilai_stok, 2),
                'hpp_average' => $summary->hpp_average,
            ]);

            // Update id_last_log di summary
            $summary->update(['id_last_log' => $log->id]);

            // Sync TempatKayu
            self::syncTempatKayu($lahanId);

            Log::info('Stok Opname Kayu selesai', [
                'lahan_id' => $lahanId,
                'jenis_kayu_id' => $jenisKayuId,
                'panjang' => $panjang,
                'selisih_batang' => $selisihBatang,
                'log_id' => $log->id,
            ]);
        });

        Notification::make()
            ->success()
            ->title('✅ Stok Opname Berhasil')
            ->body('Stok kayu telah diperbarui dan dicatat di Log HPP.')
            ->send();

        // Reset form
        self::resetForm($get, $set);
    }

    // =========================================================
    // SYNC TEMPAT KAYU
    // =========================================================
    private static function syncTempatKayu(int $lahanId): void
    {
        $totalBatang = HppAverageSummarie::where('id_lahan', $lahanId)
            ->whereNull('grade')
            ->sum('stok_batang');

        $kayuMasuk = \App\Models\KayuMasuk::whereHas('detailTurusanKayus', function ($q) use ($lahanId) {
            $q->where('lahan_id', $lahanId);
        })->latest()->first();

        if ($kayuMasuk) {
            \App\Models\TempatKayu::updateOrCreate(
                [
                    'id_lahan' => $lahanId,
                    'id_kayu_masuk' => $kayuMasuk->id,
                ],
                ['jumlah_batang' => $totalBatang]
            );
        }
    }

    // =========================================================
    // RESET FORM
    // =========================================================
    private static function resetForm(Get $get, Set $set): void
    {
        $set('id_lahan', null);
        $set('id_jenis_kayu', null);
        $set('panjang', null);
        $set('stok_batang_sistem', 0);
        $set('stok_kubikasi_sistem', 0);
        $set('hpp_average_sistem', 0);
        $set('stok_batang_fisik', 0);
        $set('stok_kubikasi_fisik', 0);
        $set('selisih_batang', 0);
        $set('selisih_kubikasi', 0);
        $set('nilai_stok_baru', 0);
        $set('keterangan', '');

        Notification::make()
            ->info()
            ->title('Form Direset')
            ->body('Form telah dikosongkan, siap untuk opname baru.')
            ->send();
    }
}
