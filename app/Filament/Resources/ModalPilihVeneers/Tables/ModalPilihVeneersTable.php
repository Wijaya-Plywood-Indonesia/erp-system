<?php

namespace App\Filament\Resources\ModalPilihVeneers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use App\Models\Ukuran;
use App\Models\JenisKayu;

class ModalPilihVeneersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable(),

                TextColumn::make('jenis_veneer')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn ($state) => $state === 'jadi' ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state === 'jadi' ? 'Veneer Jadi' : 'Veneer Kering'),

                TextColumn::make('jenis_kayu_display')
                    ->label('Jenis Kayu')
                    ->getStateUsing(function ($record) {
                        // Veneer Jadi: ambil dari relasi stokVeneerJadi
                        if ($record->jenis_veneer === 'jadi' || $record->id_stok_veneer_jadi) {
                            return $record->stokVeneerJadi?->jenisKayu?->nama_kayu ?? '-';
                        }

                        // Veneer Kering: ambil dari id_jenis_kayu langsung
                        if ($record->id_jenis_kayu) {
                            return JenisKayu::find($record->id_jenis_kayu)?->nama_kayu ?? '-';
                        }

                        return '-';
                    })
                    ->placeholder('-'),

                TextColumn::make('dimensi')
                    ->label('Ukuran')
                    ->getStateUsing(function ($record) {
                        // Veneer Jadi: dimensi dari stokVeneerJadi
                        if ($record->jenis_veneer === 'jadi' || $record->id_stok_veneer_jadi) {
                            $stok = $record->stokVeneerJadi;
                            if ($stok) {
                                return floatval($stok->panjang) . ' x ' . floatval($stok->lebar) . ' x ' . floatval($stok->tebal);
                            }
                        }

                        // Veneer Kering: dimensi dari tabel ukurans via id_ukuran
                        if ($record->id_ukuran) {
                            $ukuran = Ukuran::find($record->id_ukuran);
                            if ($ukuran) {
                                return floatval($ukuran->panjang) . ' x ' . floatval($ukuran->lebar) . ' x ' . floatval($ukuran->tebal);
                            }
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
                    )
                    ->after(function (\App\Models\ModalPilihVeneer $record) {
                        $record->hasilPilihVeneers()->create([
                            'id_produksi_pilih_veneer' => $record->id_produksi_pilih_veneer,
                            'jenis_veneer' => 'jadi',
                            'kw' => null,
                            'no_palet' => null,
                            'jumlah' => null,
                        ]);
                    }),
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
