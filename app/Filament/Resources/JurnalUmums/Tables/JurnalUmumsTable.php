<?php

namespace App\Filament\Resources\JurnalUmums\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JurnalUmumsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tgl')
                    ->date()
                    ->sortable(),
                TextColumn::make('jurnal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('no_akun')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('no-dokumen')
                    ->searchable(),
                TextColumn::make('mm')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('keterangan')
                    ->searchable(),
                TextColumn::make('map')
                    ->searchable(),
                TextColumn::make('hit_kbk')
                    ->searchable(),
                TextColumn::make('banyak')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('m3')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('harga')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('synced_at')
                    ->label('Waktu Sinkron')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('syncedBy.name')
    ->label('Disinkron Oleh')
    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
