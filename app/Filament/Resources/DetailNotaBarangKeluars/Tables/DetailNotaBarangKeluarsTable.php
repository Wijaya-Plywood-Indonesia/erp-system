<?php

namespace App\Filament\Resources\DetailNotaBarangKeluars\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DetailNotaBarangKeluarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                //
                TextColumn::make('nota.no_nota')
                    ->label('No Nota')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->sortable()
                    ->numeric(),

                TextColumn::make('satuan')
                    ->label('Satuan')
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->keterangan),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Barang')
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();
                        // Hanya muncul jika belum divalidasi dan tidak memiliki mutasi veneer terkait
                        return $nota && $nota->divalidasi_oleh === null && !$nota->mutasi()->exists();
                    })
                    ->disabled(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();
                        return $nota?->divalidasi_oleh !== null;
                    })
                    ->tooltip('Nota sudah divalidasi, tidak bisa menambah barang'),


                Action::make('validasi_nota')
                    ->label('Validasi Nota')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(function (RelationManager $livewire) {
                        // Tombol hanya muncul jika BELUM divalidasi
                        return empty($livewire->ownerRecord->divalidasi_oleh);
                    })
                    ->disabled(function (RelationManager $livewire) {
                        // Pembuat TIDAK boleh validasi
                        return $livewire->ownerRecord->dibuat_oleh == auth()->id();
                    })
                    ->action(function (RelationManager $livewire) {
                        $nota = $livewire->ownerRecord;

                        try {
                            $hasMutasi = \App\Models\VeneerMutasi::where('id_nota_bk', $nota->id)->exists();
                            // Run the business service to deduct stock and set divalidasi_oleh
                            app(\App\Services\VeneerMutasiService::class)->processStockFromNota($nota);

                            Notification::make()
                                ->title('Nota berhasil divalidasi!')
                                ->body($hasMutasi ? 'Stok veneer telah dikurangi sesuai isi nota BK.' : 'Status nota telah diperbarui.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Validasi Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->after(function ($livewire) {
                        // Refresh komponen supaya status berubah
                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->url(function ($record) {
                        $nota = $record->nota;
                        if ($nota && $nota->mutasi) {
                            return route('filament.admin.resources.veneer-keluars.edit', $nota->mutasi->id);
                        }
                        return null;
                    })
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();
                        return $nota && $nota->divalidasi_oleh === null;
                    }),
                DeleteAction::make()
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();
                        // Hanya bisa delete manual jika tidak ada mutasi veneer terkait
                        return $nota && $nota->divalidasi_oleh === null && !$nota->mutasi()->exists();
                    }),
            ])
            ->toolbarActions([

            ]);
    }
}
