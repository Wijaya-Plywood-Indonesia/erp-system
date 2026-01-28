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
                        $nota = $livewire->getOwnerRecord();

                        if (!$nota)
                            return false;

                        return
                            $nota->divalidasi_oleh === null &&
                            $nota->dibuat_oleh !== auth()->id();
                    })
                    ->action(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        $nota->update([
                            'divalidasi_oleh' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Nota berhasil divalidasi!')
                            ->success()
                            ->send();
                    })
                    ->after(fn($livewire) => $livewire->dispatch('$refresh')),

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
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
