<?php

namespace App\Filament\Resources\KayuPecahRotaries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;

class KayuPecahRotariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('lahan_display')
                    ->label('Lahan')
                    ->getStateUsing(function ($record) {
                        $lahan = $record->penggunaanLahan?->lahan;

                        return $lahan
                            ? "{$lahan->kode_lahan} - {$lahan->nama_lahan}"
                            : '-';
                    })
                    ->sortable([
                        'penggunaanLahan.lahan.kode_lahan'
                    ])
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('penggunaanLahan.lahan', function (Builder $q) use ($search) {
                            $q->where('kode_lahan', 'like', "%{$search}%")
                                ->orWhere('nama_lahan', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('ukuran')
                    ->label('ukuran')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('foto')
                    ->label('Foto')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn($state) => $state ? 'Foto sudah diupload' : 'Belum ada foto'),
                TextColumn::make('created_at')
                    ->label('Waktu Laporan')
                    ->dateTime()
                    ->sortable()
                //->toggleable(isToggledHiddenByDefault: true)
                ,
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(), // 👈 ini yang munculkan tombol "Tambah"
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
