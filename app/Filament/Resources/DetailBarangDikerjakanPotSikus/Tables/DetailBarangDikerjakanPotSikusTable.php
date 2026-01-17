<?php

namespace App\Filament\Resources\DetailBarangDikerjakanPotSikus\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class DetailBarangDikerjakanPotSikusTable
{
    public static function configure(Table $table): Table
    {
        return $table

            /*
            |=====================================================
            | 🔥 GROUP BY PEGAWAI (FIX FINAL)
            |=====================================================
            */
            ->groups([
                Group::make('id_pegawai_pot_siku')
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(fn ($record) =>
                        $record->pegawaiPotSiku?->pegawai?->nama_pegawai
                        ?? 'Pegawai Tidak Diketahui'
                    )
                    ->collapsible(true), // default tertutup
            ])

            /*
            |=====================================================
            | 📋 COLUMNS
            |=====================================================
            */
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable(),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('Ukuran.nama_ukuran')
                    ->label('Ukuran'),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)'),

                TextColumn::make('tinggi')
                    ->label('Tinggi'),
            ])

            /*
            |=====================================================
            | ➕ HEADER ACTIONS
            |=====================================================
            */
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn ($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            /*
            |=====================================================
            | ✏️ RECORD ACTIONS
            |=====================================================
            */
            ->recordActions([
                EditAction::make()
                    ->hidden(fn ($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(fn ($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            /*
            |=====================================================
            | 🧹 BULK ACTIONS
            |=====================================================
            */
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn ($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ])

            /*
            |=====================================================
            | 📌 DEFAULT GROUP
            |=====================================================
            */
            ->defaultGroup('id_pegawai_pot_siku');
    }
}
