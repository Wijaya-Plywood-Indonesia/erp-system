<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables;

class ProduksiPilihPlywoodsTable
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
                    ->tooltip(fn(string $state): string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('kelola_kendala')
                    ->label(fn($record) => $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala')
                    ->icon(fn($record) => $record->kendala ? 'heroicon-o-pencil-square' : 'heroicon-o-plus')
                    ->color(fn($record) => $record->kendala ? 'info' : 'warning')

                    // ✅ Form style baru di Filament 4
                    ->schema([
                        Textarea::make('kendala')
                            ->label('Kendala')
                            ->required()
                            ->rows(4),
                    ])

                    // ✅ Saat modal dibuka — isi form dengan data kendala lama jika ada
                    ->mountUsing(function ($form, $record) {
                        $form->fill([
                            'kendala' => $record->kendala ?? '',
                        ]);
                    })

                    // ✅ Saat tombol Simpan ditekan
                    ->action(function (array $data, $record): void {
                        $record->update([
                            'kendala' => trim($data['kendala']),
                        ]);

                        Notification::make()
                            ->title($record->kendala ? 'Kendala diperbarui' : 'Kendala ditambahkan')
                            ->success()
                            ->send();
                    })

                    ->modalHeading(fn($record) => $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala')
                    ->modalSubmitActionLabel('Simpan'),
                // Hilang jika sudah divalidasi
                EditAction::make(),
                    // ->visible(fn($record) => $record->validasiTerakhir?->status !== 'divalidasi'),

                DeleteAction::make(),
                    // ->visible(fn($record) => $record->validasiTerakhir?->status !== 'divalidasi'),

                // View boleh tetap tampil
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        // ->visible(
                        //     fn($records) =>
                        //     $records->every(fn($r) => $r->validasiTerakhir?->status !== 'divalidasi')
                        // ),
                ]),
            ]);
    }
}
