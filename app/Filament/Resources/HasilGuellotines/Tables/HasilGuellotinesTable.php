<?php

namespace App\Filament\Resources\HasilGuellotines\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;

class HasilGuellotinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
            fn($query) =>
            $query->with([
                'pegawaiGuellotines.pegawai', // Ini harus sesuai dengan model
                'jenisKayu',
                'ukuran',
            ])
        )
        ->groups([
            Group::make('id') 
                ->label('Pegawai')
                ->getTitleFromRecordUsing(function ($record) {
                    // Cek apakah relasi pivot terisi
                    if ($record->pegawaiGuellotines->isEmpty()) {
                        return 'Pegawai: -';
                    }

                    return 'Pegawai: ' .
                        $record->pegawaiGuellotines
                        ->pluck('pegawai.nama_pegawai')
                        ->implode(' , ');
                })
                ->collapsible(),
        ])
        ->defaultGroup('id')

            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable(),

                
                TextColumn::make('Ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(false)
                    ->placeholder('Ukuran'),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('N/A'),


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
