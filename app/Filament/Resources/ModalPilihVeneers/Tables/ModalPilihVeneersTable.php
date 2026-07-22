<?php

namespace App\Filament\Resources\ModalPilihVeneers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;

class ModalPilihVeneersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable(),

                TextColumn::make('stokVeneerJadi.jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('-'),

                // 3. UKURAN (Diambil dari kolom panjang, lebar, tebal di ModalPilihVeneer)
                TextColumn::make('dimensi')
                    ->label('Ukuran')
                    ->getStateUsing(function ($record) {
                        if ($record->panjang && $record->lebar && $record->tebal) {
                            $p = floatval($record->panjang);
                            $l = floatval($record->lebar);
                            $t = floatval($record->tebal);
                            return "{$p} x {$l} x {$t}";
                        }

                        // Fallback ke stokVeneerJadi jika nilai di record kosong
                        if ($record->stokVeneerJadi) {
                            $p = floatval($record->stokVeneerJadi->panjang);
                            $l = floatval($record->stokVeneerJadi->lebar);
                            $t = floatval($record->stokVeneerJadi->tebal);
                            return "{$p} x {$l} x {$t}";
                        }

                        return '-';
                    }),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Create Action — HILANG jika status sudah divalidasi
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                // Edit Action — HILANG jika status sudah divalidasi
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                // Delete Action — HILANG jika status sudah divalidasi
                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
