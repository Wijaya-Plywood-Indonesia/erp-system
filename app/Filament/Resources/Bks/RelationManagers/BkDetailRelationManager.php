<?php

namespace App\Filament\Resources\Bks\RelationManagers;

use App\Filament\Resources\Bks\Actions\TambahItemGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BkDetailRelationManager extends RelationManager
{
    protected static string $relationship = 'detail';
    protected static ?string $title = 'Barang';

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->terkunci();
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_barang')->label('Barang')->wrap(),
                TextColumn::make('jumlah')->numeric(),
                TextColumn::make('satuan'),
                TextColumn::make('keterangan')->wrap(),
            ])
            ->headerActions([TambahItemGroup::make(true)])
            ->recordActions([
                DeleteAction::make()->iconButton()->visible(fn () => ! $this->getOwnerRecord()->terkunci()),
            ]);
    }
}