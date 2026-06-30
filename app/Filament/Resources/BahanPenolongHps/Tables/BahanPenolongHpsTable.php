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
        return $table
            ->columns([
                TextColumn::make('nama_bahan')
                    ->label('Nama Bahan')
                    ->formatStateUsing(function ($state) {
                        $bahan = \App\Models\BahanPenolongProduksi::where('nama_bahan_penolong', $state)->first();
                        return $state . ($bahan ? " ({$bahan->satuan})" : "");
                    })
                    ->searchable(),

                TextColumn::make('jumlah')
                    ->label('Banyaknya'),
            ])
            ->filters([
                //
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

                        $existing = $model::where('id_produksi_hp', $ownerRecord->id)
                            ->where('nama_bahan', $data['nama_bahan'])
                            ->first();

                        if ($existing) {
                            $existing->increment('jumlah', $data['jumlah']);
                            return $existing;
                        }

                        return $model::create(array_merge($data, ['id_produksi_hp' => $ownerRecord->id]));
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