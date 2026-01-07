<?php

namespace App\Filament\Resources\HasilPilihPlywoods\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Grouping\Group; // Tambahkan ini

class HasilPilihPlywoodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Tambahkan Grouping di sini
            ->groups([
                Group::make('id_barang_setengah_jadi_hp')
                    ->label('Barang')
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn () => '')
                    ->getTitleFromRecordUsing(fn ($record) => 
                        ($record->barangSetengahJadiHp->jenisBarang->nama_jenis_barang ?? '-') . ' | ' .
                        ($record->barangSetengahJadiHp->ukuran->nama_ukuran ?? '-') . ' | ' .
                        ($record->barangSetengahJadiHp->grade->nama_grade ?? '-')
                    )
            ])
            ->defaultGroup('id_barang_setengah_jadi_hp') // Otomatis ter-group saat dibuka
            ->columns([
                // Ubah kolom barang agar menampilkan detail lengkap seperti di Select
                TextColumn::make('barangSetengahJadiHp.id')
                    ->label('Detail Barang')
                    ->formatStateUsing(fn ($record) => 
                        ($record->barangSetengahJadiHp->jenisBarang->nama_jenis_barang ?? '-') . ' | ' .
                        ($record->barangSetengahJadiHp->ukuran->nama_ukuran ?? '-') . ' | ' .
                        ($record->barangSetengahJadiHp->grade->nama_grade ?? '-')
                    ),

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
                    ->summarize(
                        Sum::make()->label('Total Cacat')
                    ),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Cacat'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}