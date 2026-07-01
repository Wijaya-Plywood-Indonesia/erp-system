<?php

namespace App\Filament\Resources\AnakAkuns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnakAkunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_anak_akun')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_anak_akun')
                    ->label('nama')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('indukAkun.nama_induk_akun')
                    ->wrap()
                    ->label('Induk Akun')
                    ->sortable(),

                TextColumn::make('parentAkun.nama_anak_akun')
                    ->wrap()
                    ->label('Parent')
                    ->placeholder('-'),

                BadgeColumn::make('saldo_normal')
                    ->colors([
                        'success' => 'debet',
                        'danger' => 'kredit',
                    ]),

                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'aktif',
                        'danger' => 'nonaktif',
                    ]),
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
