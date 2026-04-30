<?php

namespace App\Filament\Resources\BahanPenolongHps\Tables;

use App\Filament\Resources\BahanPenolongHps\Schemas\BahanPenolongHpForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BahanPenolongHpsTable
{
    public static function configure(Table $table): Table
    {
        // Ambil options dari Schema Class
        $bahanOptions = BahanPenolongHpForm::getBahanOptions();

        return $table
            ->columns([
                TextColumn::make('nama_bahan')
                    ->searchable()
                    ->label('Nama Bahan')
                    ->formatStateUsing(function ($state, $record) use ($bahanOptions) {
                        if ($record->masterBahan) {
                            return $record->masterBahan->nama_bahan_penolong;
                        }
                        $label = $bahanOptions[$state] ?? $state;
                        if (preg_match('/^(.*)\s\(.*\)$/', $label, $matches)) {
                            return $matches[1];
                        }
                        return $label;
                    }),
                TextColumn::make('jumlah')
                    ->label('Banyaknya')
                    ->formatStateUsing(function ($state, $record) use ($bahanOptions) {
                        if ($record->masterBahan) {
                            return $state . ' ' . $record->masterBahan->satuan;
                        }
                        $label = $bahanOptions[$record->nama_bahan] ?? '';
                        if (preg_match('/^.*\s\((.*)\)$/', $label, $matches)) {
                            return $state . ' ' . $matches[1];
                        }
                        return $state;
                    }),
            ])
            ->filters([
                SelectFilter::make('nama_bahan')
                    ->options($bahanOptions)
                    ->multiple(),
            ])
            ->headerActions([
                CreateAction::make()
                    // Hidden jika sudah divalidasi
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    // Hidden jika sudah divalidasi
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    // Hidden jika sudah divalidasi
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        // Hidden jika sudah divalidasi
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}