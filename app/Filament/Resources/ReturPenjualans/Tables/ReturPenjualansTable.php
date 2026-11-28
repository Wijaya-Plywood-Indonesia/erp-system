<?php

namespace App\Filament\Resources\ReturPenjualans\Tables;

use App\Filament\Resources\ReturPenjualans\Pages\FormReturPenjualan;
use App\Models\ReturPenjualan;
use App\Services\Akuntansi\ReturPenjualanSyncService;
use App\Services\StokGudangSatuService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ReturPenjualansTable
{
    public static function configure(Table $table): Table
    {
        $isKantor = (bool) auth()->user()?->hasAnyRole(['edmeros', 'super_admin', 'Super Admin']);

        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal Retur')
                    ->date()
                    ->sortable(),

                TextColumn::make('no_retur')
                    ->label('No. Retur')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (ReturPenjualan $r) => $r->keterangan ? \Illuminate\Support\Str::limit($r->keterangan, 50) : null),

                TextColumn::make('no_nota')
                    ->label('No. Nota Asal')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tujuan_nota')
                    ->label('Customer / Pembeli')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('metode_pembayaran')
                    ->label('Metode Refund')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Transfer' => 'info',
                        'Tunai'    => 'success',
                        default    => 'gray',
                    }),

                TextColumn::make('total')
                    ->label('Total Retur')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight()
                    ->visible($isKantor),

                TextColumn::make('status_retur')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'divalidasi' => 'Divalidasi',
                        'selesai'    => 'Selesai',
                        default      => 'Draft',
                    })
                    ->color(fn ($state) => match ($state) {
                        'divalidasi' => 'success',
                        'selesai'    => 'primary',
                        default      => 'gray',
                    }),

                TextColumn::make('status_sync_akuntansi')
                    ->label('Sync Akuntansi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'synced' => 'Terkirim',
                        'failed' => 'Gagal',
                        default  => 'Draft',
                    })
                    ->color(fn ($state) => match ($state) {
                        'synced' => 'success',
                        'failed' => 'danger',
                        default  => 'warning',
                    }),

                TextColumn::make('pembuat.name')
                    ->label('Dibuat Oleh')
                    ->sortable(),

                TextColumn::make('validator.name')
                    ->label('Divalidasi Oleh')
                    ->placeholder('Belum divalidasi')
                    ->badge()
                    ->color(fn ($state) => filled($state) ? 'success' : 'gray'),
            ])
            ->groups([
                Group::make('tanggal')
                    ->label('Tanggal Retur')
                    ->date()
                    ->orderQueryUsing(fn ($query) => $query->orderBy('tanggal', 'desc'))
                    ->collapsible(),
            ])
            ->defaultGroup('tanggal')
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                // 1. VALIDASI RETUR (KANTOR)
                Action::make('validasi')
                    ->label('Validasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ReturPenjualan $record) => blank($record->divalidasi_oleh) && (bool) auth()->user()?->hasAnyRole(['edmeros', 'super_admin', 'Super Admin']))
                    ->requiresConfirmation()
                    ->action(function (ReturPenjualan $record) {
                        DB::transaction(function () use ($record) {
                            $record->update([
                                'divalidasi_oleh' => auth()->id(),
                                'status_retur' => 'divalidasi',
                            ]);

                            $record->loadMissing(['details.detailPenjualan.ukuran']);
                            $stokService = app(StokGudangSatuService::class);

                            foreach ($record->details as $detail) {
                                $qty = (float) ($detail->qty ?? 0);
                                $dp = $detail->detailPenjualan;

                                if ($qty <= 0 || !$dp || !$dp->id_jenis_kayu) {
                                    continue;
                                }

                                $panjang = null;
                                $lebar = null;
                                $tebal = null;

                                if ($dp->ukuran) {
                                    $panjang = (float) $dp->ukuran->panjang;
                                    $lebar = (float) $dp->ukuran->lebar;
                                    $tebal = (float) $dp->ukuran->tebal;
                                } elseif (!empty($dp->ukuran_key) && str_contains($dp->ukuran_key, '|')) {
                                    $parts = array_map('floatval', explode('|', $dp->ukuran_key));
                                    if (count($parts) >= 3) {
                                        [$panjang, $lebar, $tebal] = $parts;
                                    }
                                }

                                if ($panjang === null || $lebar === null || $tebal === null) {
                                    continue;
                                }

                                $kubikasi = ($detail->custom_m3 && (float) $detail->custom_m3 > 0)
                                    ? (float) $detail->custom_m3
                                    : (($panjang * $lebar * $tebal * $qty) / 10_000_000);

                                $stokService->tambah(
                                    idJenisKayu: (int) $dp->id_jenis_kayu,
                                    panjang: $panjang,
                                    lebar: $lebar,
                                    tebal: $tebal,
                                    kwGrade: (string) ($dp->kw_grade ?? '-'),
                                    lembar: $qty,
                                    kubikasi: $kubikasi,
                                    keterangan: 'Retur Penjualan ' . $record->no_retur,
                                    referensi: $record,
                                );
                            }
                        });

                        // Coba sync otomatis ke akuntansi jika divalidasi
                        $syncService = app(ReturPenjualanSyncService::class);
                        $result = $syncService->kirimRetur($record);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Retur Divalidasi & Terkirim ke Akuntansi')
                                ->body('Retur ' . $record->no_retur . ' berhasil dikirim ke modul Akuntansi.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Retur Divalidasi (Gagal Sync Akuntansi)')
                                ->body($result['message'])
                                ->warning()
                                ->send();
                        }
                    }),

                // 2. SYNC MANUAL KE AKUNTANSI
                Action::make('syncAkuntansi')
                    ->label('Kirim Ulang Akuntansi')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (ReturPenjualan $record) => filled($record->divalidasi_oleh) && (bool) auth()->user()?->hasAnyRole(['edmeros', 'super_admin', 'Super Admin']))
                    ->requiresConfirmation()
                    ->action(function (ReturPenjualan $record) {
                        $syncService = app(ReturPenjualanSyncService::class);
                        $result = $syncService->kirimRetur($record);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Sinkronisasi Berhasil')
                                ->body('Retur ' . $record->no_retur . ' berhasil terkirim ke Akuntansi!')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Sinkronisasi Gagal')
                                ->body($result['message'])
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn (ReturPenjualan $record) => FormReturPenjualan::getUrl(['edit' => $record->id])),

                DeleteAction::make(),
            ]);
    }
}
