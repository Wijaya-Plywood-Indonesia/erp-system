<?php

namespace App\Filament\Resources\NotaKayus\Tables;

use App\Models\DetailKayuMasuk;
use App\Models\DetailTurusanKayu;
use App\Services\JurnalSyncService;
use App\Services\NotaKayuJurnalPayloadService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotaKayusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_nota')
                    ->searchable(),

                TextColumn::make('info_kayu')
                    ->label('Info Kayu')
                    ->sortable()
                    ->searchable(query: function ($query, string $search) {
                        $numberOnly = preg_replace('/[^0-9]/', '', $search);

                        return $query->whereHas('kayuMasuk', function ($q) use ($search, $numberOnly) {
                            if (is_numeric($numberOnly) && $numberOnly !== '') {
                                $q->where('seri', '=', $numberOnly);
                            } else {
                                $q->whereHas('penggunaanSupplier', function ($sq) use ($search) {
                                    $sq->where('nama_supplier', 'like', "%{$search}%");
                                });
                            }
                        });
                    })
                    ->getStateUsing(function ($record) {
                        if (!$record->kayuMasuk) return '-';
                        $seri        = $record->kayuMasuk->seri ?? '-';
                        $namaSupplier = $record->kayuMasuk->penggunaanSupplier?->nama_supplier ?? '-';
                        $noTelepon   = $record->kayuMasuk->penggunaanSupplier?->no_telepon ?? '-';

                        return "Seri {$seri} - {$namaSupplier} ({$noTelepon})";
                    }),

                TextColumn::make('penanggung_jawab')
                    ->label('PJ')
                    ->searchable(),

                TextColumn::make('total_summary2')
                    ->label('Rekap Turusan 1')
                    ->getStateUsing(function ($record) {
                        if (!$record->kayuMasuk) {
                            return "0 Batang\n0.0000 m³";
                        }
                        $total    = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $batang   = number_format($total['total_batang']);
                        $kubikasi = number_format($total['total_kubikasi'], 4);

                        return "{$batang} Batang\n{$kubikasi} m³";
                    })
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn(string $state) => str_replace("\n", '<br>', e($state)))
                    ->html()
                    ->alignCenter(),

                TextColumn::make('total_summary')
                    ->label('Rekap Turusan 2')
                    ->getStateUsing(function ($record) {
                        if (!$record->kayuMasuk) {
                            return "0 Batang\n0.0000 m³";
                        }
                        $total    = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $batang   = number_format($total['total_batang']);
                        $kubikasi = number_format($total['total_kubikasi'], 4);

                        return "{$batang} Batang\n{$kubikasi} m³";
                    })
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn(string $state) => str_replace("\n", '<br>', e($state)))
                    ->html()
                    ->alignCenter(),

                TextColumn::make('penerima')
                    ->searchable(),

                TextColumn::make('satpam')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->searchable()
                    ->badge()
                    ->colors([
                        'secondary' => 'Belum Diperiksa',
                        'success'   => fn($state) => str_contains($state, 'Sudah Diperiksa'),
                        'warning'   => fn($state) => str_contains($state, 'Menunggu'),
                        'danger'    => fn($state) => str_contains($state, 'Ditolak'),
                    ]),

                // Kolom baru: indikator status sync ke Perusahaan 2
                TextColumn::make('sync_status')
                    ->label('Jurnal P2')
                    ->getStateUsing(function ($record) {
                        // Belum diperiksa → belum relevan
                        if (! str_contains($record->status ?? '', 'Sudah Diperiksa')) {
                            return '-';
                        }

                        // Cek apakah sudah ada jurnal di P2 dengan no_dokumen ini
                        // Kita simpan no_jurnal hasil sync di kolom keterangan_sync
                        // atau tampilkan berdasarkan flag sederhana
                        if (! empty($record->keterangan_sync)) {
                            return $record->keterangan_sync; // mis: "J-0044"
                        }

                        return '⏳ Belum Sync';
                    })
                    ->badge()
                    ->color(fn($state) => match (true) {
                        str_starts_with((string) $state, 'J-') => 'success',
                        $state === '⏳ Belum Sync'              => 'warning',
                        default                                 => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->defaultSort('created_at', 'desc')

            ->recordActions([

                // ──────────────────────────────────────────────────────
                // ACTION: Tandai Sudah Diperiksa
                // PERUBAHAN: setelah save status, langsung sync ke P2
                // ──────────────────────────────────────────────────────
                Action::make('cek')
                    ->label('Tandai Sudah Diperiksa')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(function ($record) {
                        if ($record->status !== 'Belum Diperiksa') {
                            return false;
                        }

                        if (!$record->kayuMasuk) {
                            return false;
                        }

                        $total1 = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $total2 = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);

                        $batangSama   = $total1['total_batang'] == $total2['total_batang'];
                        $kubikasiSama = abs($total1['total_kubikasi'] - $total2['total_kubikasi']) < 0.0001;

                        return $batangSama && $kubikasiSama;
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Sudah Diperiksa?')
                    ->modalDescription('Nota ini akan ditandai sudah diperiksa dan otomatis dikirim ke jurnal Perusahaan 2.')
                    ->modalSubmitActionLabel('Ya, Tandai & Kirim Jurnal')
                    ->action(function ($record) {
                        $user = Auth::user();

                        // ── STEP 1: Update status (sama seperti sebelumnya) ──
                        $record->status = "Sudah Diperiksa oleh {$user->name}";
                        $record->save();

                        // ── STEP 2: Sync ke Perusahaan 2 ──
                        $syncBerhasil = self::jalankanSync($record);

                        // ── STEP 3: Notifikasi ke user ──
                        if ($syncBerhasil['success']) {
                            $noJurnal = $syncBerhasil['no_jurnal'] ?? '-';

                            Notification::make()
                                ->success()
                                ->title('Berhasil')
                                ->body("Status diperbarui & jurnal {$noJurnal} berhasil dikirim ke Perusahaan 2.")
                                ->send();
                        } else {
                            // Status tetap tersimpan, hanya sync yang gagal
                            Notification::make()
                                ->warning()
                                ->title('Status disimpan, tapi jurnal gagal dikirim')
                                ->body('Pesan error: ' . ($syncBerhasil['message'] ?? 'Tidak diketahui') . '. Silakan coba sync ulang.')
                                ->persistent() // Tetap tampil sampai user tutup
                                ->send();
                        }
                    }),

                // ── ACTION: Sync Ulang (muncul jika sync sebelumnya gagal) ──
                Action::make('sync_ulang')
                    ->label('Sync Ulang ke P2')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(function ($record) {
                        // Tampil jika: sudah diperiksa TAPI belum ada no jurnal di P2
                        return str_contains($record->status ?? '', 'Sudah Diperiksa')
                            && empty($record->keterangan_sync);
                    })
                    ->action(function ($record) {
                        $result = self::jalankanSync($record);

                        if ($result['success']) {
                            Notification::make()
                                ->success()
                                ->title('Sync Ulang Berhasil')
                                ->body("Jurnal {$result['no_jurnal']} berhasil dikirim ke Perusahaan 2.")
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Sync Ulang Gagal')
                                ->body($result['message'] ?? 'Terjadi kesalahan.')
                                ->send();
                        }
                    }),

                // ── ACTION: Cetak Nota ──
                Action::make('print')
                    ->label('Cetak Nota')
                    ->icon('heroicon-o-printer')
                    ->color('green')
                    ->url(fn($record) => route('nota-kayu.show', $record))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record->status !== 'Belum Diperiksa')
                    ->disabled(
                        fn($record) => !$record->kayuMasuk?->detailTurusanKayus()->exists()
                    ),

                // ── ACTION: Cetak Turus ──
                Action::make('print_turus')
                    ->label('Cetak Turus')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->url(fn($record) => route('nota-kayu.turus', $record))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record->status !== 'Belum Diperiksa')
                    ->disabled(
                        fn($record) => !$record->kayuMasuk?->detailTurusanKayus()->exists()
                    ),

                ViewAction::make(),
                EditAction::make(),
            ])

            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make(),
                // ]),
            ])

            ->filters([
                SelectFilter::make('seri')
                    ->relationship('kayuMasuk', 'seri')
                    ->searchable()
                    ->label('Pilih Seri'),
            ]);
    }

    // ──────────────────────────────────────────────────────────────
    // HELPER: jalankanSync
    //
    // Dipanggil dari Action 'cek' dan Action 'sync_ulang'.
    // Dipisah ke method static agar tidak duplikasi kode.
    //
    // Return: ['success' => bool, 'no_jurnal' => '...', 'message' => '...']
    // ──────────────────────────────────────────────────────────────
    private static function jalankanSync($record): array
    {
        try {
            // Muat relasi yang dibutuhkan untuk kalkulasi
            $record->loadMissing([
                'kayuMasuk.detailTurusanKayus.jenisKayu',
                'kayuMasuk.penggunaanSupplier',
            ]);

            // Pastikan ada data detail kayu
            if (! $record->kayuMasuk || $record->kayuMasuk->detailTurusanKayus->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Tidak ada detail turusan kayu untuk nota ini.',
                ];
            }

            // Step 1: Bangun payload (grouping per panjang + kalkulasi)
            $payloadService = app(NotaKayuJurnalPayloadService::class);
            $payload        = $payloadService->buildPayload($record);

            // Payload untuk menerima petugasnnya.
            $payload['petugas'] = [
                'nama'  => Auth::user()->name,
            ];

            // Step 2: Kirim ke Perusahaan 2
            $syncService = app(JurnalSyncService::class);
            $result      = $syncService->kirim($record, $payload);

            // Step 3: Jika berhasil, simpan no_jurnal ke kolom keterangan_sync
            // Ini opsional — hanya jika Anda ingin menampilkan di kolom 'Jurnal P2'
            // Jika tidak ingin menyimpan apapun, hapus 3 baris berikut:
            if ($result['success'] && ! empty($result['no_jurnal'])) {
                $record->keterangan_sync = $result['no_jurnal']; // mis: "J-0044"
                $record->saveQuietly();  // saveQuietly = simpan tanpa trigger observer/event lagi
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("[NotaKayusTable] Sync gagal untuk nota {$record->no_nota}: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
