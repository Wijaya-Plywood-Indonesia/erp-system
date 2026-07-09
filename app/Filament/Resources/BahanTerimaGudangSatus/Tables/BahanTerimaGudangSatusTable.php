<?php

namespace App\Filament\Resources\BahanTerimaGudangSatus\Tables;

use App\Models\SerahTerimaGudangSatu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BahanTerimaGudangSatusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No Palet'),

                TextColumn::make('barangSetengahJadiHp.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grade_display')
                    ->label('Grade')
                    ->getStateUsing(
                        fn ($record) => ($record->barangSetengahJadiHp?->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                            .' | '.
                            ($record->barangSetengahJadiHp?->grade?->nama_grade ?? '-')
                    )
                    ->sortable(),

                TextColumn::make('barangSetengahJadiHp.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->sortable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->alignCenter(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! empty($data['id_serah_terima_gudang_satu'])) {
                            $serahTerima = SerahTerimaGudangSatu::find($data['id_serah_terima_gudang_satu']);
                            $data['id_barang_setengah_jadi_hp'] = $serahTerima?->barangSetengahJadi?->id;
                        }

                        return $data;
                    })
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! empty($data['id_serah_terima_gudang_satu'])) {
                            $serahTerima = SerahTerimaGudangSatu::find($data['id_serah_terima_gudang_satu']);
                            $data['id_barang_setengah_jadi_hp'] = $serahTerima?->barangSetengahJadi?->id;
                        }

                        return $data;
                    })
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
