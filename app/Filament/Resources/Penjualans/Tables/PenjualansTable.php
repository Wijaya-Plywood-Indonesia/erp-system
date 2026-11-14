<?php

namespace App\Filament\Resources\Penjualans\Tables;

use App\Models\Penjualan;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class PenjualansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),

                TextColumn::make('no_nota')
                    ->label('No. Nota')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('tujuan_nota')
                    ->label('Customer / Pembeli')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('metode_pembayaran')
                    ->label('Pembayaran')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Transfer' => 'info',
                        'Tunai' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('total')
                    ->label('Total Penjualan')
                    ->money('IDR')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('status_sync_akuntansi')
                    ->label('Sync Akuntansi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'synced' => 'Terkirim',
                        'failed' => 'Gagal',
                        default => 'Draft',
                    })
                    ->color(fn ($state) => match ($state) {
                        'synced' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('pembuat.name')
                    ->label('Petugas Lapangan')
                    ->sortable(),

                TextColumn::make('validator.name')
                    ->label('Validasi Kantor')
                    ->placeholder('Belum divalidasi')
                    ->badge()
                    ->color(fn ($state) => filled($state) ? 'success' : 'gray'),
            ])
            ->groups([
                Group::make('tanggal')
                    ->label('Tanggal')
                    ->date()
                    ->orderQueryUsing(fn ($query) => $query->orderBy('tanggal', 'desc'))
                    ->collapsible(),
            ])
            ->defaultGroup('tanggal')
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                // 1. INPUT HARGA / NOTA KANTOR (ORANG KANTOR)
                Action::make('notaKantor')
                    ->label('Nota Kantor & Harga')
                    ->icon('heroicon-o-building-office')
                    ->color('success')
                    ->tooltip('Input harga nota kantor dan kirim ke Akuntansi')
                    ->url(fn (Penjualan $record) => route('penjualan.preview', ['record' => $record->id, 'jenis' => 'kantor']))
                    ->openUrlInNewTab(),

                // 2. INPUT HARGA / NOTA SALES (ORANG KANTOR)
                Action::make('notaSales')
                    ->label('Nota Sales')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('#8b5cf6')
                    ->tooltip('Input harga & potongan sales, kirim ke Akuntansi')
                    ->url(fn (Penjualan $record) => route('penjualan.preview', ['record' => $record->id, 'jenis' => 'sales']))
                    ->openUrlInNewTab(),

                // 3. CETAK SURAT JALAN (SJ)
                Action::make('suratJalan')
                    ->label('Surat Jalan')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->url(fn (Penjualan $record) => route('penjualan.surat-jalan', ['record' => $record->id]))
                    ->openUrlInNewTab(),

                // 4. SYNC MANUAL KE AKUNTANSI (Hanya muncul jika sudah divalidasi)
                Action::make('syncAkuntansi')
                    ->label('Kirim Ulang Akuntansi')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (Penjualan $record) => filled($record->divalidasi_oleh))
                    ->requiresConfirmation()
                    ->action(function (Penjualan $record) {
                        try {
                            $controller = app(\App\Http\Controllers\PenjualanCetakController::class);
                            [$resolvedItems, $grandTotal] = $controller->resolvePenjualanItems($record, 'kantor');

                            $syncService = app(\App\Services\Akuntansi\PenjualanSyncService::class);
                            $result = $syncService->kirimPenjualan($record, $resolvedItems, $grandTotal);

                            if ($result['success']) {
                                $record->update([
                                    'status_sync_akuntansi' => 'synced',
                                    'sync_response'         => json_encode($result['data'] ?? []),
                                ]);
                                Notification::make()
                                    ->title('Sinkronisasi Berhasil')
                                    ->body('Penjualan ' . $record->no_nota . ' berhasil dikirim ke web Akuntansi!')
                                    ->success()
                                    ->send();
                            } else {
                                $record->update([
                                    'status_sync_akuntansi' => 'failed',
                                    'sync_response'         => $result['message'] ?? 'Error',
                                ]);
                                Notification::make()
                                    ->title('Sinkronisasi Gagal')
                                    ->body($result['message'])
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Terjadi Kesalahan')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
