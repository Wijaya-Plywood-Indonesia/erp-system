<?php

namespace App\Filament\Resources\DokumenKayus\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DokumenKayusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dokumen_legal')
                    ->searchable(),
                TextColumn::make('nama_legal')
                    ->searchable(),

                TextColumn::make('upload_dokumen')
                    ->label('Dokumen Legal')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Ada File' : 'Kosong')
                    ->color(fn($state) => $state ? 'success' : 'danger'),

                TextColumn::make('upload_ktp')
                    ->label('KTP')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Ada File' : 'Kosong')
                    ->color(fn($state) => $state ? 'success' : 'danger'),

                TextColumn::make('foto_lokasi')
                    ->label('Foto Lokasi')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Ada File' : 'Kosong')
                    ->color(fn($state) => $state ? 'success' : 'danger'),

                TextColumn::make('nama_tempat')
                    ->label('Lokasi Lahan')
                    ->searchable(),
                TextColumn::make('alamat_lengkap')
                    ->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('no_dokumen_legal')
                    ->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latitude')
                    ->numeric()
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('longitude')
                    ->numeric()
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),

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
