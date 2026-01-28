<?php

namespace App\Filament\Resources\ModalGrajiStiks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModalGrajiStiksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ukuran.dimensi')
                    ->label('Ukuran')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('jumlah_bahan')
                    ->label('Jumlah Bahan')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('nomor_palet')
                    ->label('No. Palet')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->copyable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime('d M Y H:i')
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
            // --- HEADER ACTIONS ---
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Modal')
                    ->icon('heroicon-o-plus')
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()), // LOGIKA LOCKING
            ])
            // --- RECORD ACTIONS ---
            ->actions([
                EditAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()), // LOGIKA LOCKING
                DeleteAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()), // LOGIKA LOCKING
            ])
            // --- BULK ACTIONS ---
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()), // LOGIKA LOCKING
                ]),
            ]);
    }
}
