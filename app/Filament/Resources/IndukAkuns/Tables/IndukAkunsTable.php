<?php

namespace App\Filament\Resources\IndukAkuns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IndukAkunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_induk_akun')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_induk_akun')
                    ->label('Nama Induk Akun')
                    ->searchable()
                    ->wrap()
                    ->sortable(),

                BadgeColumn::make('saldo_normal')
                    ->label('Saldo Normal')
                    ->colors([
                        'success' => 'debet',
                        'danger' => 'kredit',
                    ])
                    ->sortable(),

                BadgeColumn::make('status')
                    ->colors([
                        'success' => 'aktif',
                        'danger' => 'nonaktif',
                    ])
                    ->sortable(),

                TextColumn::make('anakAkuns_count')
                    ->counts('anakAkuns')
                    ->label('Jumlah Anak Akun')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
                SelectFilter::make('status')
                    ->options([
                        'aktif' => 'Aktif',
                        'nonaktif' => 'Nonaktif',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
