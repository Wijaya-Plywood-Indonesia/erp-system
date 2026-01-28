<?php

namespace App\Filament\Resources\NotaBarangMasuks\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotaBarangMasuksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('no_nota')
                    ->searchable(),
                TextColumn::make('tujuan_nota')
                    ->label('kepada')
                    ->searchable(),
                TextColumn::make('dibuatOleh.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('divalidasiOleh.name')
                    ->label('Divalidasi Oleh')
                    ->placeholder('Belum divalidasi')
                    ->badge()
                    ->color(fn($state) => filled($state) ? 'success' : 'danger')
                    ->sortable()
                    ->searchable(),
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
                Action::make('print')
                    ->label('Cetak Nota')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn($record) => route('nota-bm.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record->divalidasi_oleh !== null),
                ViewAction::make(),

                EditAction::make()
                    ->visible(fn($record) => $record->divalidasi_oleh === null),

                DeleteAction::make()
                    ->visible(fn($record) => $record->divalidasi_oleh === null)
                    ->before(function ($record) {
                        if ($record->detail()->exists()) {
                            abort(403, 'Tidak bisa menghapus nota karena masih ada detail yang terkait.');
                        }
                    })
                    ->failureNotificationTitle('Penghapusan gagal')
                    ->failureNotification(
                        fn() =>
                        'Nota masih memiliki detail. Hapus detailnya dulu.'
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
