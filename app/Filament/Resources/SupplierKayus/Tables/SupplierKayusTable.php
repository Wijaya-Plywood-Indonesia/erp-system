<?php

namespace App\Filament\Resources\SupplierKayus\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupplierKayusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // TextColumn::make('status_supplier')
                //     ->searchable(),

                TextColumn::make('status_supplier')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ((string) $state) {
                        '1' => 'Aktif',
                        '0' => 'Tidak Aktif',
                        default => 'Tidak Diketahui',
                    })
                    ->icon(fn($state) => match ((string) $state) {
                        '1' => 'heroicon-o-check-circle',
                        '0' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn($state) => match ((string) $state) {
                        '1' => 'success',
                        '0' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('nama_supplier')
                    ->searchable(),
                TextColumn::make('upload_ktp')
                    ->label('File KTP')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Ada File' : 'Kosong')
                    ->color(fn($state) => $state ? 'success' : 'danger'),

                TextColumn::make('no_telepon')
                    ->icon('heroicon-o-phone')
                    ->searchable(),
                // TextColumn::make('nik')
                //     ->searchable(),

                TextColumn::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->formatStateUsing(fn($state) => match ((string) $state) {
                        '1' => 'Laki-laki',
                        '0' => 'Perempuan',
                        default => 'Tidak Diketahui',
                    })
                    ->color(fn($state): string => match ((string) $state) {
                        '1' => 'success',
                        '0' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('jenis_bank')
                    ->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('no_rekening')
                    ->searchable()->toggleable(isToggledHiddenByDefault: true),

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
