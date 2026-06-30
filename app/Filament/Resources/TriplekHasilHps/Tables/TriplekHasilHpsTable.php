<?php

namespace App\Filament\Resources\TriplekHasilHps\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;

class TriplekHasilHpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                /*
                 * MESIN
                 */
                TextColumn::make('mesin.nama_mesin')
                    ->label('Mesin')
                    ->searchable()
                    ->placeholder('-'),

                /*
                 * NO PALET
                 */
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable(),

                /*
                 * JENIS BARANG
                 */
                TextColumn::make('barangSetengahJadi.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->placeholder('-'),

                /*
                 * GRADE
                 */
                TextColumn::make('barangSetengahJadi.grade.nama_grade')
                    ->label('Grade')
                    ->searchable()
                    ->placeholder('-'),

                /*
                 * UKURAN
                 */
                TextColumn::make('barangSetengahJadi.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('barangSetengahJadi.ukuran', function ($q) use ($search) {
                            $q->whereRaw(
                                "CONCAT(panjang, 'mm x ', lebar, 'mm x ', tebal, 'mm') LIKE ?",
                                ["%{$search}%"]
                            );
                        });
                    })
                    ->placeholder('-'),

                /*
                 * ISI
                 */
                TextColumn::make('isi')
                    ->label('Jumlah Lembar'),
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
