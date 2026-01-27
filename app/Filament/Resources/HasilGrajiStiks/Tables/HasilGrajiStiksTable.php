<?php

namespace App\Filament\Resources\HasilGrajiStiks\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class HasilGrajiStiksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                // Menampilkan Ukuran (Dimensi) dari relasi berjenjang: Hasil -> Modal -> Ukuran
                TextColumn::make('modalGrajiStik.ukuran.dimensi')
                    ->label('Ukuran')
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),

                // Menampilkan Bahan Awal sebagai referensi
                TextColumn::make('modalGrajiStik.jumlah_bahan')
                    ->label('Bahan Awal')
                    ->numeric()
                    ->color('gray')
                    ->sortable(),

                // Menampilkan Hasil Produksi
                TextColumn::make('hasil_graji')
                    ->label('Hasil Gergaji')
                    ->numeric()
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize(
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->label('Total Hasil')
                    ),

                TextColumn::make('created_at')
                    ->label('Waktu Input')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                // Tombol Tambah disembunyikan jika data divalidasi
                CreateAction::make()
                    ->label('Tambah Hasil Baru')
                    ->icon('heroicon-m-plus')
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()),
            ])
            ->actions([
                // Tombol Edit & Delete disembunyikan jika data divalidasi
                EditAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()),
                DeleteAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Aksi masal disembunyikan jika data divalidasi
                    DeleteBulkAction::make()
                        ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()),
                ]),
            ]);
    }
}
