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
            ->paginated(false) // tampil semua
            ->deferLoading()   // loading setelah render siap
            ->defaultSort('jurnal', 'asc')

            ->columns([
                TextColumn::make('tgl')
                    ->label('Tanggal')
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
                    ->limit(10)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('map')
                    ->searchable(),

                TextColumn::make('hit_kbk')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'm' => 'M3',
                        'b' => 'Banyak',
                        default => '-',
                    }),

                TextColumn::make('banyak')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('m3')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('harga')
                    ->label('Harga')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                // =========================
                // KOLOM TOTAL (BARU)
                // =========================
                TextColumn::make('total')
                    ->label('Total')
                    ->state(function ($record) {
                        $mode = strtolower($record->hit_kbk ?? '');
                        $harga = $record->harga ?? 0;

                        return match ($mode) {
                            'm' => $harga * ($record->m3 ?? 0),
                            'b' => $harga * ($record->banyak ?? 0),
                            default => $harga, // ⬅️ jika bukan m/b
                        };
                    })
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('created_by')
                    ->searchable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('synced_at')
                    ->label('Waktu Sinkron')
                    ->dateTime('d M Y H:i')
                    ->toggleable(true),

                TextColumn::make('synced_by')
                    ->label('Disinkron Oleh')
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
