<?php

namespace App\Filament\Resources\Targets\Tables;

use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class TargetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /* PENTING: Kita paksa defaultSort ke 'id_mesin' atau 'created_at' 
               agar Filament tidak mencari 'id_target' yang tidak ditemukan di database.
            */
            ->defaultSort('created_at', 'desc') 
            ->columns([
                // Kolom Status menggunakan TextColumn dengan badge()
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'diajukan' => 'danger',
                        'disetujui' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'diajukan' => 'heroicon-o-x-circle',
                        'disetujui' => 'heroicon-o-check-circle',
                        default => 'heroicon-o-minus-circle',
                    }),

                TextColumn::make('mesin.nama_mesin')
                    ->label('Mesin')
                    ->searchable(),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),

                TextColumn::make('target')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('orang')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('jam')
                    ->numeric()
                    ->sortable(),

                // Kolom Kalkulasi (Hanya sortable jika kolom ini ada di database)
                TextColumn::make('targetperjam')
                    ->label('Tgt/Jam')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('targetperorang')
                    ->label('Tgt/Org')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('gaji')
                    ->money('IDR') // Format mata uang Rupiah
                    ->sortable(),

                TextColumn::make('potongan')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Tambahkan filter di sini jika diperlukan
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}