<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Pegawai')
                    ->formatStateUsing(function ($record) {
                        if (!$record->pegawai) {
                            return '-';
                        }
                        $kode = $record->pegawai->kode_pegawai;
                        $nama = $record->pegawai->nama_pegawai;

                        return $kode && $nama ? "{$kode} - {$nama}" : ($nama ?: $kode ?: '-');
                    })
                    ->searchable(query: function ($query, $search) {
                        $query->orWhereHas('pegawai', function ($q) use ($search) {
                            $q->where('nama_pegawai', 'like', "%{$search}%")
                                ->orWhere('kode_pegawai', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->sortable()
                    ->searchable()
                    ->badge(), // biar tiap role tampil dalam bentuk badge warna-warni
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