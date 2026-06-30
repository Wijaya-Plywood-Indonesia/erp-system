<?php

namespace App\Filament\Resources\BahanPenolongRotaries\Tables;

use App\Filament\Resources\BahanPenolongRotaries\Schemas\BahanPenolongRotaryForm;
use App\Models\BahanPenolongProduksi;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BahanPenolongRotariesTable
{
    public static function configure(Table $table): Table
    {
        $bahanOptions = BahanPenolongRotaryForm::getBahanOptions();
        return $table
            ->columns([
                TextColumn::make('bahanPenolong.nama_bahan_penolong')
                    ->label('Nama Bahan')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->bahanPenolong ? 
                        $record->bahanPenolong->nama_bahan_penolong . ' (' . $record->bahanPenolong->satuan . ')' : 
                        $state
                    ),

                TextColumn::make('jumlah')
                    ->label('Banyaknya'),
            ])
            ->filters([
                // SelectFilter::make('nama_bahan')
                //     ->options($bahanOptions)
                //     ->multiple(),
            ])
            ->headerActions([
                CreateAction::make()
                    // Hidden jika sudah divalidasi
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    )
                    ->using(function (array $data, string $model, $livewire): \Illuminate\Database\Eloquent\Model {
                        $ownerRecord = $livewire->ownerRecord;

                        $existing = $model::where('id_produksi', $ownerRecord->id)
                            ->where('bahan_penolong_id', $data['bahan_penolong_id'])
                            ->first();

                        if ($existing) {
                            $existing->increment('jumlah', $data['jumlah']);
                            return $existing;
                        }

                        return $model::create(array_merge($data, ['id_produksi' => $ownerRecord->id]));
                    }),
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
