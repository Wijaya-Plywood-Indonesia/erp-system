<?php

namespace App\Filament\Resources\DetailMasuks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DetailMasuksTable
{
    public static function configure(
        Table $table,
        bool $adaPaletDiterima = false,
        string $tipe = 'dryer' // ✅ parameter tipe (untuk keperluan masa depan)
    ): Table {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with([
                'jenisKayu',
                'ukuran',
                'detailPaletRotary.produksi.mesin',
            ]))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->badge()
                    ->color(
                        fn($record) =>
                        $record->getRawOriginal('no_palet') < 0 ? 'warning' : 'primary'
                    )
                    ->searchable(false),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('ukuran_display')
                    ->label('Ukuran')
                    ->getStateUsing(fn($record) => $record->ukuran?->dimensi ?? '-')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('ukuran', function ($q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('isi')
                    ->label('Isi')
                    ->numeric(),
            ])
            ->filters([])
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
