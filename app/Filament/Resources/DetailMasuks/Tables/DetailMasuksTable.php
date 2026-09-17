<?php

namespace App\Filament\Resources\DetailMasuks\Tables;

use App\Services\ProduksiLockService;
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
        string $tipe = 'dryer' // 'dryer' | 'stik'
    ): Table {
        // Nama relasi validasi berbeda antara Press Dryer dan Stik.
        $relasiValidasi = $tipe === 'stik' ? 'validasiStik' : 'validasiPressDryers';

        return $table
            ->modifyQueryUsing(fn($query) => $query->with([
                'jenisKayu',
                'ukuran',
            ]))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->badge()
                    ->color('primary')
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
                // HILANG jika sudah divalidasi, KECUALI Super Admin.
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        ProduksiLockService::isLocked($livewire->ownerRecord, $relasiValidasi)
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        ProduksiLockService::isLocked($livewire->ownerRecord, $relasiValidasi)
                    ),
                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        ProduksiLockService::isLocked($livewire->ownerRecord, $relasiValidasi)
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            ProduksiLockService::isLocked($livewire->ownerRecord, $relasiValidasi)
                        ),
                ]),
            ]);
    }
}