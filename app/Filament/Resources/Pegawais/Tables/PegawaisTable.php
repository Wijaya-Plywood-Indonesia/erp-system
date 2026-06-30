<?php

namespace App\Filament\Resources\Pegawais\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PegawaisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_pegawai')
                    ->searchable(),
                TextColumn::make('nama_pegawai')
                    ->searchable(),
                TextColumn::make('no_telepon_pegawai')
                    ->searchable(),
                TextColumn::make('jenis_kelamin_pegawai')
                    ->formatStateUsing(fn(string $state): string => $state == '1' ? 'Laki-laki' : 'Perempuan')
                    ->badge()
                    ->color(fn(string $state): string => $state == '1' ? 'blue' : 'pink'),

                TextColumn::make('tanggal_masuk')
                    ->date()
                    ->sortable(),
                IconColumn::make('foto')
                    ->label('Foto')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn($state) => $state ? 'Foto sudah diupload' : 'Belum ada foto'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
