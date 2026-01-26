<?php

namespace App\Filament\Resources\LainLains\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;

class LainLainsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pegawai')
                    ->label('Pegawai')
                    ->getStateUsing(
                        fn($record) =>
                        $record->pegawai
                            ? "{$record->pegawai->kode_pegawai} - {$record->pegawai->nama_pegawai}"
                            : '-'
                    )
                    ->sortable()
                    ->searchable(),

                // Menampilkan jam saja (format 24 jam)
                TextColumn::make('masuk')
                    ->label('Masuk')
                    ->dateTime('H:i'), // Gunakan 'H:i' untuk jam:menit

                // Menampilkan jam saja (format 24 jam)
                TextColumn::make('pulang')
                    ->label('Pulang')
                    ->dateTime('H:i'), // Gunakan 'H:i' untuk jam:menit

                TextColumn::make('ijin')
                    ->label('Ijin')
                    ->default('-')
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->default('-')
                    ->color('primary')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('hasil')
                    ->label('Hasil')
                    ->default('-')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
