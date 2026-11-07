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
                // HP: nama barang dengan jumlah dan keterangan di bawahnya (tanpa geser ke samping).
                TextColumn::make('ringkas')->label('Barang')->hiddenFrom('md')
                    ->getStateUsing(fn ($record) => $record?->nama_barang)->wrap()->weight('bold')
                    ->description(fn ($record) => $record ? rtrim(rtrim(number_format((float) $record->jumlah, 3, '.', ''), '0'), '.') . ' ' . $record->satuan
                        . ($record->keterangan ? ' · ' . $record->keterangan : '') : null),
                TextColumn::make('nama_barang')->label('Barang')->wrap()->visibleFrom('md'),
                TextColumn::make('jumlah')->numeric()->visibleFrom('md'),
                TextColumn::make('satuan')->visibleFrom('md'),
                TextColumn::make('keterangan')->wrap()->visibleFrom('md'),
            ])
            ->headerActions([TambahItemGroup::make(true)])
            ->recordActions([
                DeleteAction::make()->iconButton()->visible(fn () => ! $this->getOwnerRecord()->terkunci()),
            ]);
    }
}