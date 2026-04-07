<?php

namespace App\Filament\Resources\ProduksiRepairs\RelationManagers;

use App\Models\BahanPenolongProduksi;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BahanPenolongRepairRelationManager extends RelationManager
{
    protected static string $relationship = 'BahanPenolongRepair';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bahan_penolong_id')
                    ->label('Nama Bahan')
                    ->options(
                        fn() =>
                        BahanPenolongProduksi::where('kategori_produksi', 'repair')
                            ->get()
                            ->mapWithKeys(fn($item) => [
                                $item->id =>
                                $item->nama_bahan_penolong . ' (' . $item->satuan . ')'
                            ])
                            ->toArray()
                    )
                    ->searchable()
                    ->required(),

                TextInput::make('jumlah')
                    ->label('Banyak')
                    ->required()
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('bahanPenolong.nama_bahan_penolong')
                    ->label('Nama Bahan')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->bahanPenolong->nama_bahan_penolong .
                            ' (' . $record->bahanPenolong->satuan . ')'
                    ),

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
