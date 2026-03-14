<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PenggunaanLahanRotariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lahan_display')
                    ->label('Lahan')
                    ->getStateUsing(
                        fn($record) =>
                        "{$record->lahan->kode_lahan} - {$record->lahan->nama_lahan}"
                    )
                    ->sortable(query: function ($query, string $direction) {
                        $query->join('lahans', 'penggunaan_lahan_rotaries.id_lahan', '=', 'lahans.id')
                            ->orderBy('lahans.kode_lahan', $direction)
                            ->select('penggunaan_lahan_rotaries.*');
                    }) // optional
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('lahan', function ($q) use ($search) {
                            $q->where('kode_lahan', 'like', "%{$search}%")
                            ->orWhere('nama_lahan', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('Belum Daftar Jenis Kayu'),

                TextColumn::make('jumlah_batang')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(), // 👈 ini yang munculkan tombol "Tambah"
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
