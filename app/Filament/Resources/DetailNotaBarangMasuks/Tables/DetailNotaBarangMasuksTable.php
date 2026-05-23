<?php

namespace App\Filament\Resources\DetailNotaBarangMasuks\Tables;

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

class DetailNotaBarangMasuksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id_nota_bm')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nama_barang')
                    ->searchable(),
                TextColumn::make('jumlah')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('satuan')
                    ->searchable(),
                TextColumn::make('keterangan')
                    ->searchable(),
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
                        $nota = $livewire->getOwnerRecord();

                        if (!$nota)
                            return false;

                        return
                            $nota->divalidasi_oleh === null &&
                            $nota->dibuat_oleh !== auth()->id();
                    })
                    ->action(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        try {
                            $hasMutasi = \App\Models\VeneerMutasi::where('id_nota_bm', $nota->id)->exists();
                            // Run the business service to add stock and set divalidasi_oleh
                            app(\App\Services\VeneerMutasiService::class)->processStockFromNota($nota);

                            Notification::make()
                                ->title('Nota berhasil divalidasi!')
                                ->body($hasMutasi ? 'Stok veneer telah ditambahkan sesuai isi nota BM.' : 'Status nota telah diperbarui.')
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
                    ->after(fn($livewire) => $livewire->dispatch('$refresh')),


            ])

            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->url(function ($record) {
                        $nota = $record->nota;
                        if ($nota && $nota->mutasi) {
                            return route('filament.admin.resources.veneer-masuks.edit', $nota->mutasi->id);
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
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
