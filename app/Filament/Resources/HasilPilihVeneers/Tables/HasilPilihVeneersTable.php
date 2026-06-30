<?php

namespace App\Filament\Resources\HasilPilihVeneers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class HasilPilihVeneersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => 
                $query->with(['modalPilihVeneer.ukuran', 'modalPilihVeneer.jenisKayu', 'pegawaiPilihVeneers.pegawai'])
            )
            // GROUPING BERDASARKAN PEGAWAI
            ->groups([
                Group::make('id') 
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(function ($record) {
                        if ($record->pegawaiPilihVeneers->isEmpty()) {
                            return 'Pegawai: -';
                        }
                        return 'Pegawai: ' . $record->pegawaiPilihVeneers
                            ->pluck('pegawai.nama_pegawai')
                            ->implode(' & ');
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('id')
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('modalPilihVeneer.ukuran.dimensi')
                    ->label('Ukuran Modal')
                    ->description(fn ($record) => "Bahan: " . ($record->modalPilihVeneer->jenisKayu->nama_kayu ?? '-')),

                TextColumn::make('kw')
                    ->label('KW Hasil')
                    ->badge(),

                TextColumn::make('jumlah')
                    ->label('Jumlah'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
                DeleteAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
                ]),
            ]);
    }
}