<?php

namespace App\Filament\Resources\MasukGrajiTripleks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;

class MasukGrajiTripleksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('barangSetengahJadiHp.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grade_display')
                    ->label('Grade')
                    ->getStateUsing(
                        fn($record) => ($record->barangSetengahJadiHp?->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                            . ' | ' .
                            ($record->barangSetengahJadiHp?->grade?->nama_grade ?? '-')
                    )
                    ->sortable(),

                TextColumn::make('barangSetengahJadiHp.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->sortable(),

                TextColumn::make('isi')
                    ->label('Jumlah')
                    ->alignCenter(),
            ])

            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->recordActions([
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
