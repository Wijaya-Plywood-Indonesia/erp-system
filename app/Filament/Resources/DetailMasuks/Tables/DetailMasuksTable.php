<?php

namespace App\Filament\Resources\DetailMasuks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;

class DetailMasuksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()->formatStateUsing(function ($state) {

                        if (!$state) return '-';

                        if (str_contains((string) $state, '-')) return $state;

                        $palet = \App\Models\DetailHasilPaletRotary::with('produksi.mesin')->find($state);

                        \Illuminate\Support\Facades\Log::channel('single')->info('no_palet resolve', [
                            'find_result'  => $palet?->id,
                            'kode_palet'   => $palet?->kode_palet,
                        ]);

                        return $palet ? $palet->kode_palet : $state;
                    }),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('Ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(false)
                    ->placeholder('Ukuran'),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('isi')
                    ->label('Isi'),

            ])
            ->filters([
                // Tempat filter jika Anda membutuhkannya
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
