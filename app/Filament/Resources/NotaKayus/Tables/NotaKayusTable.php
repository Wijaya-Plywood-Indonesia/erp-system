<?php

namespace App\Filament\Resources\NotaKayus\Tables;

use App\Filament\Resources\NotaKayus\NotaKayuResource;
use App\Models\DetailKayuMasuk;
use App\Models\DetailTurusanKayu;
use App\Models\HppAverageLog;
use App\Models\NotaKayu;
use App\Services\HppAverageService;
use App\Services\JurnalSyncService;
use App\Services\NotaKayuJurnalPayloadService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
                        if (! $record->kayuMasuk) return '-';
                        $seri         = $record->kayuMasuk->seri ?? '-';
                        $namaSupplier = $record->kayuMasuk->penggunaanSupplier?->nama_supplier ?? '-';
                        $noTelepon    = $record->kayuMasuk->penggunaanSupplier?->no_telepon ?? '-';

                        return "Seri {$seri} - {$namaSupplier} ({$noTelepon})";
                    }),

                TextColumn::make('penanggung_jawab')
                    ->label('PJ')
                    ->searchable(),

                TextColumn::make('total_summary2')
                    ->label('Rekap Turusan 1')
                    ->getStateUsing(function ($record) {
                        if (! $record->kayuMasuk) {
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
                        if (! $record->kayuMasuk) {
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

                TextColumn::make('status_pelunasan')
                    ->label('Pelunasan')
                    ->badge()
                    ->colors([
                        'danger' => 'Belum Lunas',
                        'success' => 'Lunas',
                        'warning' => 'Sebagian',
                    ])
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                // --- ACTION: CETAK NOTA ---
                Action::make('print')
                    ->label('Cetak Nota')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn($record) => route('nota-kayu.show', $record))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => str_contains($record->status ?? '', 'Sudah Diperiksa')),

                // --- ACTION: CETAK TURUS ---
                Action::make('print_turus')
                    ->label('Cetak Turus')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->url(fn($record) => route('nota-kayu.turus', $record))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => str_contains($record->status ?? '', 'Sudah Diperiksa')),

                // --- ACTION: TANDAI LUNAS (TRIGGER UTAMA HPP & JURNAL) ---
                Action::make('set_lunas')
                    ->label('Tandai Lunas')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pelunasan & Sinkronisasi')
                    ->modalDescription('Menandai nota sebagai Lunas akan memicu perhitungan HPP (Stok Masuk) dan mengirim data Jurnal ke Akuntansi. Lanjutkan?')
                    ->action(function ($record) {
                        // 1. Update status pelunasan dengan TANGGAL dan JAM
                        $timestamp = now()->format('d/m/Y H:i');
                        $record->status_pelunasan = "Lunas - {$timestamp}";
                        $record->save();

                        // 2. TRIGGER HPP: Cek apakah log HPP sudah ada (mencegah duplikasi)
                        $sudahAdaLog = HppAverageLog::where('referensi_type', NotaKayu::class)
                            ->where('referensi_id', $record->id)
                            ->exists();

                        if (! $sudahAdaLog) {
                            try {
                                app(HppAverageService::class)->prosesNotaKayuMasuk($record);
                                Log::info('[HPP] Proses stok berhasil dijalankan saat Pelunasan', [
                                    'nota_id' => $record->id,
                                ]);
                            } catch (\Throwable $e) {
                                Log::error('[HPP] GAGAL proses stok saat Pelunasan', [
                                    'nota_id' => $record->id,
                                    'error'   => $e->getMessage(),
                                ]);
                            }
                        }

                        // 3. TRIGGER JURNAL: Sync jurnal ke Perusahaan 2
                        self::jalankanSync($record);

                        Notification::make()
                            ->title('Nota Lunas & Sinkron')
                            ->body("Status diperbarui menjadi: Lunas - {$timestamp}")
                            ->success()
                            ->send();
                    })
                    ->visible(
                        fn($record) =>
                        str_contains($record->status ?? '', 'Sudah Diperiksa') &&
                            !str_contains($record->status_pelunasan ?? '', 'Lunas')
                    ),

                // --- ACTION: TANDAI SUDAH DIPERIKSA (HANYA VERIFIKASI FISIK) ---
                Action::make('cek')
                    ->label('Tandai Sudah Diperiksa')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(function ($record) {
                        if (str_contains($record->status ?? '', 'Sudah Diperiksa')) return false;
                        if (! $record->kayuMasuk) return false;

                        $total1 = DetailTurusanKayu::hitungTotalByKayuMasuk($record->kayuMasuk->id);
                        $total2 = DetailKayuMasuk::hitungTotalByKayuMasuk($record->kayuMasuk->id);

                        $batangSama   = $total1['total_batang'] == $total2['total_batang'];
                        $kubikasiSama = abs($total1['total_kubikasi'] - $total2['total_kubikasi']) < 0.0001;

                        return $batangSama && $kubikasiSama;
                    })
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $user = Auth::user();

                        // HANYA mengubah status fisik. Stok dan Jurnal belum diproses di sini.
                        $record->status = "Sudah Diperiksa oleh {$user->name}";
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Verifikasi Berhasil')
                            ->body('Data fisik telah diverifikasi. Stok akan bertambah saat nota ditandai Lunas.')
                            ->send();
                    }),

                // --- LOGIKA OTORISASI ---
                ViewAction::make()
                    ->visible(function ($record) {
                        $user = Auth::user();
                        $isAdmin = $user->hasRole(['admin', 'super_admin']);
                        $sudahDiperiksa = str_contains($record->status ?? '', 'Sudah Diperiksa');
                        return $isAdmin || !$sudahDiperiksa;
                    }),

                EditAction::make()
                    ->visible(function ($record) {
                        $user = Auth::user();
                        $isAdmin = $user->hasRole(['admin', 'super_admin']);
                        $sudahDiperiksa = str_contains($record->status ?? '', 'Sudah Diperiksa');
                        return $isAdmin || !$sudahDiperiksa;
                    }),

                DeleteAction::make()
                    ->visible(fn() => Auth::user()->hasRole(['admin', 'super_admin'])),
            ])
            ->filters([
                Filter::make('seri_kayu')
                    ->form([
                        TextInput::make('nomor_seri')
                            ->label('Cari Seri Kayu')
                            ->placeholder('Contoh: 123')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['nomor_seri'],
                            fn(Builder $query, $seri): Builder => $query->whereHas(
                                'kayuMasuk',
                                fn(Builder $q) => $q->where('seri', 'like', "%{$seri}%")
                            )
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['nomor_seri']) {
                            return null;
                        }
                        return 'Seri Kayu: ' . $data['nomor_seri'];
                    }),

                SelectFilter::make('status_pelunasan')
                    ->options([
                        'Belum Lunas' => 'Belum Lunas',
                        'Lunas' => 'Lunas',
                        'Sebagian' => 'Sebagian',
                    ])
            ]);
    }

    private static function jalankanSync($record): array
    {
        try {
            $record->loadMissing(['kayuMasuk.detailTurusanKayus.jenisKayu', 'kayuMasuk.penggunaanSupplier']);
            if (! $record->kayuMasuk || $record->kayuMasuk->detailTurusanKayus->isEmpty()) return ['success' => false];

            $payloadService = app(NotaKayuJurnalPayloadService::class);
            $payload = $payloadService->buildPayload($record);
            $payload['petugas'] = ['nama' => Auth::user()?->name, 'email' => Auth::user()?->email];

            $syncService = app(JurnalSyncService::class);
            $result = $syncService->kirim($record, $payload);

            if ($result['success'] && ! empty($result['no_jurnal'])) {
                Cache::put("jurnal_sync_{$record->id}", $result['no_jurnal'], now()->addYear());
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error("[NotaKayusTable] Sync gagal: {$e->getMessage()}");
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
