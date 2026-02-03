<?php

namespace App\Filament\Resources\Jurnal2s\Tables;

use Filament\Actions\BulkActionGroup;
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
                    ->label('Kubikasi'),

                TextColumn::make('harga')
                    ->label('Harga'),

                TextColumn::make('total')
                    ->label('Total'),

                TextColumn::make('user_id')
                    ->label('User'),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y'),
            ])
            ->filters([
                Filter::make('hari_ini')
                    ->label('Hari Ini')
                    ->query(fn (Builder $query) =>
                        $query->whereDate('created_at', now()->toDateString())
                    ),
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
