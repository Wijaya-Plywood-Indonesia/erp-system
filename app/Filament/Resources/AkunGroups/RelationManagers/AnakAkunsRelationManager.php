<?php

namespace App\Filament\Resources\AkunGroups\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnakAkunsRelationManager extends RelationManager
{
    protected static string $relationship = 'anakAkuns';
    protected static ?string $title = 'Anak Akun';
    protected static ?string $recordTitleAttribute = 'nama_anak_akun';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_anak_akun')
            ->columns([
                TextColumn::make('kode_anak_akun')
                    ->label('Kode')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nama_anak_akun')
                    ->label('Nama Akun')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelect(function ($select) {
                        return $select
                            ->getOptionLabelFromRecordUsing(
                                fn($record) =>
                                "{$record->kode_anak_akun} - {$record->nama_anak_akun}"
                            )
                            ->searchable()
                            ->preload();
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Hapus'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Hapus Terpilih'),
                ]),
            ]);
    }
}