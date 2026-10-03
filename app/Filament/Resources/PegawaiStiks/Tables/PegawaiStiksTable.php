<?php

namespace App\Filament\Resources\PegawaiStiks\Tables;

use App\Filament\Support\PindahPegawaiTableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class PegawaiStiksTable
{
    public static function configure(Table $table): Table
    {
        // Tanggal produksi Stik yang sedang dibuka
        $tanggalStik = fn($livewire) => $livewire->getOwnerRecord()->tanggal_produksi;

        return $table
            ->columns([
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Pegawai')
                    ->sortable()
                    ->formatStateUsing(
                        fn($record) => $record->pegawai
                            ? $record->pegawai->kode_pegawai . ' - ' . $record->pegawai->nama_pegawai
                            : '—'
                    )
                    ->badge()
                    ->searchable(
                        query: fn($query, $search) => $query->whereHas(
                            'pegawai',
                            fn($q) => $q
                                ->where('nama_pegawai', 'like', "%{$search}%")
                                ->orWhere('kode_pegawai', 'like', "%{$search}%")
                        )
                    ),

                TextColumn::make('tugas')
                    ->label('Tugas')
                    ->searchable(),

                TextColumn::make('masuk')
                    ->label('Masuk')
                    ->dateTime('d M Y H:i'),

                TextColumn::make('pulang')
                    ->label('Pulang')
                    ->dateTime('d M Y H:i'),

                TextColumn::make('ijin')
                    ->label('Izin')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                PindahPegawaiTableActions::recordAction('stik', $tanggalStik),
                PindahPegawaiTableActions::batalAction('stik'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    PindahPegawaiTableActions::bulkAction('stik', $tanggalStik),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}