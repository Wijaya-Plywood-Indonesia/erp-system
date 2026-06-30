<?php

namespace App\Filament\Resources\Jurnal2s\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class Jurnal2sTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->defaultSort('modif100', 'asc')
    ->defaultSort('no_akun', 'asc')
            ->columns([
                TextColumn::make('modif100')
                    ->label('Modif 100'),

                TextColumn::make('no_akun')
                    ->label('No Akun'),

                TextColumn::make('nama_akun')
                    ->label('Nama Akun'),

                TextColumn::make('banyak')
                    ->label('Banyak'),

                TextColumn::make('kubikasi')
                    ->label('Kubikasi')
                    ->numeric(decimalPlaces: 4),

                TextColumn::make('harga')
                    ->label('Harga'),

                TextColumn::make('total')
                    ->label('Total')
                    ->numeric(decimalPlaces: 0),

                TextColumn::make('user_id')
                    ->label('Dibuat Oleh'),

                TextColumn::make('status_sinkron')
                    ->label('Status')
                    ->badge()
                    ->color(
                        fn($state) =>
                        $state === 'sudah sinkron' ? 'success' : 'warning'
                    ),
                TextColumn::make('synced_at')
                    ->label('Waktu Sinkron')
                    ->dateTime('d M Y H:i')
                    ->toggleable(true),

                TextColumn::make('synced_by')
                    ->label('Disinkron Oleh')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(true),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                Filter::make('hari_ini')
                    ->label('Hari Ini')
                    ->query(
                        fn(Builder $query) =>
                        $query->whereDate('created_at', now()->toDateString())
                    ),
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
