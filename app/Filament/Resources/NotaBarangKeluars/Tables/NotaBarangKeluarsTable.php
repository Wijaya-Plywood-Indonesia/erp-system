<?php

namespace App\Filament\Resources\NotaBarangKeluars\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class NotaBarangKeluarsTable
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
                TextColumn::make('pembuat.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('validator.name')
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
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('print')
                    ->label('Cetak Nota')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn($record) => route('nota-bk.print', $record))
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
