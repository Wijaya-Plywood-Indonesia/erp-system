<?php

namespace App\Filament\Resources\SubAnakAkuns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubAnakAkunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_sub_anak_akun')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_sub_anak_akun')
                    ->label('Nama Sub Akun')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('anakAkun.kode_anak_akun')
                    ->label('Kode Anak')
                    ->sortable(),

                TextColumn::make('anakAkun.nama_anak_akun')
                    ->label('Nama Anak Akun')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('anakAkun.indukAkun.nama_induk_akun')
                    ->label('Induk Akun')
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

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->label('Tanggal Dibuat')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
