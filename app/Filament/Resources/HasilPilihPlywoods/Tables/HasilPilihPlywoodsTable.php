<?php

namespace App\Filament\Resources\HasilPilihPlywoods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Grouping\Group;

class HasilPilihPlywoodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('id_barang_setengah_jadi_hp')
                    ->label('Barang')
                    ->collapsible()
                    ->getTitleFromRecordUsing(function ($record) {
                        // Judul Barang Utama
                        $barang = ($record->barangSetengahJadiHp->jenisBarang->nama_jenis_barang ?? '-') . ' | ' .
                                 ($record->barangSetengahJadiHp->ukuran->nama_ukuran ?? '-') . ' | ' .
                                 ($record->barangSetengahJadiHp->grade->nama_grade ?? '-');
                        
                        // Nama Pegawai (hanya muncul di header group)
                        $pegawais = $record->pegawais->pluck('nama_pegawai')->unique()->implode(', ');
                        
                        // Menampilkan: Barang [Pemeriksa: Nama Pegawai]
                        return $barang . ($pegawais ? " — [Pegawai: {$pegawais}]" : "");
                    })
            ])
            ->defaultGroup('id_barang_setengah_jadi_hp')
            ->columns([

                TextColumn::make('jenis_cacat')
                    ->label('Jenis Cacat')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'reject' => 'danger',
                        'reparasi' => 'warning',
                        'selesai' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->alignCenter()
                    ->weight('bold')
                    ->summarize(
                        Sum::make()->label('Total')
                    ),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->wrap()
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn ($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
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

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteAction::make()
                        ->hidden(fn ($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}