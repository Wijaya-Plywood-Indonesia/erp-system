<?php

namespace App\Filament\Resources\ProduksiSandings\Tables;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProduksiSandingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal Repair')
                    ->formatStateUsing(
                        fn($state) =>
                        Carbon::parse($state)
                            ->locale('id')
                            ->translatedFormat('l, j F Y')
                    )
                    ->sortable()
                    ->searchable(),
                TextColumn::make('mesin.nama_mesin')
                    ->label('Nama Mesin')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('kendala')
                    ->label('Kendala')
                    ->placeholder('Tidak Ada / Belum Menemukan Kendala')
                    ->wrap()
                    ->limit(50)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('shift')
                    ->label('Shift')
                    ->badge()
                    ->icon(fn(string $state): string => match ($state) {
                        'PAGI' => 'heroicon-o-sun',
                        'MALAM' => 'heroicon-o-moon',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'PAGI' => 'success',
                        'MALAM' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'PAGI' => 'Pagi',
                        'MALAM' => 'Malam',
                        default => $state,
                    })
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
                Action::make('kendala')
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
                ViewAction::make()
                    ->label('')
                    ->tooltip('Lihat Data'),
                EditAction::make()
                    ->label('')
                    ->tooltip('Edit Data')
                    ->visible(fn($record) => $record->validasiTerakhir?->status !== 'divalidasi'),
                DeleteAction::make()
                    ->label('')
                    ->tooltip('Hapus Data')
                    ->visible(fn($record) => $record->validasiTerakhir?->status !== 'divalidasi'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(
                            fn($records) =>
                            $records->every(fn($r) => $r->validasiTerakhir?->status !== 'divalidasi')
                        ),
                ]),
            ]);
    }
}
