<?php

namespace App\Filament\Resources\JurnalUmums\Tables;

use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class JurnalUmumsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordClasses(function (Model $record) {
                $date = Carbon::parse($record->tgl);
                $isWednesday = $date->isWednesday();
                $isSynced = $record->status === 'Sudah Sinkron';

                /**
                 * HIGHLIGHT HIJAU:
                 * Hanya untuk data Rabu yang sudah sinkron.
                 * Data Kamis tetap muncul (putih) agar tidak membingungkan.
                 */
                if ($isWednesday && $isSynced) {
                    return 'bg-green-50/80 dark:bg-green-900/10 border-l-4 border-green-500 shadow-sm';
                }

                return null;
            })

            ->columns([
                TextColumn::make('tgl')
                    ->date()
                    ->sortable(),
                TextColumn::make('jurnal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('no_akun')
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
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Sudah Sinkron' => 'success', // Akan berwarna Hijau
                        'Belum Sinkron' => 'gray',  // Akan berwarna Merah
                    })
                    ->searchable(),
                TextColumn::make('synced_at')
                    ->label('Waktu Sinkron')
                    ->dateTime('d M Y H:i')
                    ->toggleable(true),

                TextColumn::make('synced_by')
                    ->label('Disinkron Oleh')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(true),
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
