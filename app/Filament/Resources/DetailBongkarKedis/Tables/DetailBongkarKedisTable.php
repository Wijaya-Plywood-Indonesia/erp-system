<?php

namespace App\Filament\Resources\DetailBongkarKedis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DetailBongkarKedisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('ukuran.dimensi')
                    ->label('Ukuran')
                    ->sortable(),

                TextColumn::make('kw')
                    ->label('KW')
                    ->sortable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isBongkarDivalidasi()),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isBongkarDivalidasi()),
                DeleteAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isBongkarDivalidasi()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn($livewire) => $livewire->ownerRecord?->isBongkarDivalidasi()),
                ]),
            ]);
    }
}
