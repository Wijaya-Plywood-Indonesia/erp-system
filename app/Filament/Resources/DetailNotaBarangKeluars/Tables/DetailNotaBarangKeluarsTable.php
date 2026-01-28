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
                    ->disabled(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        // Disable jika SUDAH divalidasi
                        return $nota?->divalidasi_oleh !== null;
                    })
                    ->tooltip('Nota sudah divalidasi, tidak bisa menambah barang'),


                Action::make('validasi_nota')
                    ->label('Validasi Nota')
                    ->icon('heroicon-o-check')
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

                        $nota->update([
                            'divalidasi_oleh' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Nota berhasil divalidasi!')
                            ->success()
                            ->send();
                    })
                    ->after(function ($livewire) {
                        // Refresh komponen supaya status berubah
                        $livewire->dispatch('$refresh');
                    }),
                // Action::make('batalkan_validasi')
                //     ->label('Batalkan Validasi')
                //     ->icon('heroicon-o-x-circle')
                //     ->color('danger')
                //     ->requiresConfirmation()
                //     ->visible(function (RelationManager $livewire) {
                //         $nota = $livewire->ownerRecord;

                //         // Tombol muncul hanya jika nota SUDAH divalidasi
                //         return $nota->divalidasi_oleh != null;
                //     })
                //     ->action(function (RelationManager $livewire) {
                //         $nota = $livewire->ownerRecord;

                //         $nota->update([
                //             'divalidasi_oleh' => null,
                //         ]);

                //         Notification::make()
                //             ->title('Validasi berhasil dibatalkan.')
                //             ->danger()
                //             ->send();
                //     })
                //     ->after(fn($livewire) => $livewire->dispatch('$refresh')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        return $nota?->divalidasi_oleh === null;
                    }),
                DeleteAction::make()
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        return $nota?->divalidasi_oleh === null;
                    }),
            ])
            ->toolbarActions([

            ]);
    }
}
