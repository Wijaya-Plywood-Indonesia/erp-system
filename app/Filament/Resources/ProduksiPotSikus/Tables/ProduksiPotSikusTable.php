<?php

namespace App\Filament\Resources\ProduksiPotSikus\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

class ProduksiPotSikusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_produksi')
                    ->date()
                    ->sortable(),

                TextColumn::make('kendala')
                    ->label('Kendala')
                    ->limit(50)
                    ->tooltip(fn (string $state): string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal_produksi', 'desc')
            ->filters([
                Filter::make('tanggal_produksi')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->placeholder('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->placeholder('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date) =>
                                    $query->whereDate('tanggal_produksi', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date) =>
                                    $query->whereDate('tanggal_produksi', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                Action::make('kelola_kendala')
                    ->label(fn ($record) => $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala')
                    ->icon(fn ($record) => $record->kendala ? 'heroicon-o-pencil-square' : 'heroicon-o-plus')
                    ->color(fn ($record) => $record->kendala ? 'info' : 'warning')
                    ->schema([
                        Textarea::make('kendala')
                            ->label('Kendala')
                            ->required()
                            ->rows(4),
                    ])
                    ->mountUsing(function ($form, $record) {
                        $form->fill([
                            'kendala' => $record->kendala ?? '',
                        ]);
                    })
                    ->action(function (array $data, $record): void {
                        $record->update([
                            'kendala' => trim($data['kendala']),
                        ]);

                        Notification::make()
                            ->title('Kendala disimpan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn ($record) => $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala')
                    ->modalSubmitActionLabel('Simpan'),

                EditAction::make()
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi'),

                ViewAction::make(),

                DeleteAction::make()
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi')
                    ->before(function ($record) {

                        $hasDetail =
                            $record->pegawaiPotSiku()->exists()
                            || $record->detailBarangDikerjakanPotSiku()->exists()
                            || $record->validasiPotSiku()->exists();

                        if ($hasDetail) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi Pot Siku ini masih memiliki data didalamnya yang terkait.')
                                ->danger()
                                ->send();

                            // ⛔ Stop proses delete
                            throw new Halt();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(
                            fn ($records) =>
                                $records->every(
                                    fn ($r) => $r->validasiTerakhir?->status !== 'divalidasi'
                                )
                        ),
                ]),
            ]);
    }
}
