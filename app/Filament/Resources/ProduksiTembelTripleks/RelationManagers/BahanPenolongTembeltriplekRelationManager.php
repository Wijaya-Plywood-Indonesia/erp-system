<?php

namespace App\Filament\Resources\ProduksiTembelTripleks\RelationManagers;

use App\Models\BahanPenolongProduksi;

// Custom Schema & Table
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;

// Form Components
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

// Table Columns & Custom Actions
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class BahanPenolongTembeltriplekRelationManager extends RelationManager
{
    protected static string $relationship = 'bahanPenolongTembeltriplek';

    protected static ?string $title = 'Bahan Penolong';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('nama_bahan')
                    ->label('Nama Bahan')
                    ->options(
                        fn() =>
                        BahanPenolongProduksi::where('kategori_produksi', 'tembel_triplek')
                            ->get()
                            ->mapWithKeys(fn($item) => [
                                $item->nama_bahan_penolong => $item->nama_bahan_penolong . ' (' . $item->satuan . ')'
                            ])
                            ->toArray()
                    )
                    ->required()
                    ->native(false)
                    ->searchable(),

                TextInput::make('jumlah')
                    ->label('Banyak')
                    ->required()
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_bahan')
            ->columns([
                TextColumn::make('nama_bahan')
                    ->label('Nama Bahan')
                    ->formatStateUsing(function ($state) {
                        $bahan = BahanPenolongProduksi::where('nama_bahan_penolong', $state)->first();
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
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTembeltriplek()->latest()->first()?->status === 'divalidasi'
                    )
                    ->using(function (array $data, string $model, $livewire): \Illuminate\Database\Eloquent\Model {
                        $ownerRecord = $livewire->ownerRecord;

                        $existing = $model::where('id_produksi_tembel_triplek', $ownerRecord->id)
                            ->where('nama_bahan', $data['nama_bahan'])
                            ->first();

                        if ($existing) {
                            $existing->increment('jumlah', $data['jumlah']);
                            return $existing;
                        }

                        return $model::create(array_merge($data, ['id_produksi_tembel_triplek' => $ownerRecord->id]));
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTembeltriplek()->latest()->first()?->status === 'divalidasi'
                    ),
                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTembeltriplek()->latest()->first()?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTembeltriplek()->latest()->first()?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}