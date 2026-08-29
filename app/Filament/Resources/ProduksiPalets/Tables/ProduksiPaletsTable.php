<?php

namespace App\Filament\Resources\ProduksiPalets\Tables;

use App\Services\ValidasiProduksiPaletService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProduksiPaletsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->formatStateUsing(function ($state) {
                        if (!$state)
                            return '-';

                        return Carbon::parse($state)
                            ->locale('id')
                            ->translatedFormat('l , d F Y');
                    })
                    ->sortable(),
                TextColumn::make('keterangan')
                    ->label('Kendala')
                    ->limit(50)
                    ->tooltip(fn($record): ?string => $record->keterangan)
                    ->placeholder('Tidak ada kendala')
                    ->wrap(),
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
                Action::make('isi_kendala')
                    ->label('Isi Kendala')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->modalHeading('Catat Kendala Produksi')
                    ->modalSubmitActionLabel('Simpan Kendala')
                    ->form([
                        Textarea::make('keterangan')
                            ->label('Kendala Produksi')
                            ->placeholder('Tuliskan kendala yang terjadi selama produksi...')
                            ->rows(4)
                            ->required(),
                    ])
                    ->fillForm(fn($record): array => [
                        'keterangan' => $record->keterangan,
                    ])
                    ->action(function ($record, array $data): void {
                        $record->update([
                            'keterangan' => $data['keterangan'],
                        ]);

                        Notification::make()
                            ->title('Kendala Berhasil Disimpan')
                            ->success()
                            ->send();
                    }),
                ViewAction::make()->hidden(fn($record) => $record && ValidasiProduksiPaletService::isLocked($record)),
                EditAction::make()->hidden(fn($record) => $record && ValidasiProduksiPaletService::isLocked($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->hidden(fn($record) => $record && ValidasiProduksiPaletService::isLocked($record)),
                ]),
            ]);
    }
}
