<?php

namespace App\Filament\Resources\PegawaiTurunKayus\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PegawaiTurunKayusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('turunKayu.tanggal')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Pekerja')
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
                TextColumn::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->time('H:i'),
                TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->time('H:i'),
                TextColumn::make('ket')
                    ->label('Keterangan'),
            ])
            ->filters([
                SelectFilter::make('id_turun_kayu')
                    ->label('Tanggal')
                    ->relationship('turunKayu', 'tanggal')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

}
