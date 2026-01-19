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
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProduksiSandingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal Produksi')
                    ->formatStateUsing(
                        fn ($state) =>
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
                    ->placeholder('Tidak ada kendala')
                    ->wrap()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->kendala)
                    ->searchable(),

                TextColumn::make('shift')
                    ->label('Shift')
                    ->badge()
                    ->icon(fn (string $state): string => match ($state) {
                        'PAGI'  => 'heroicon-o-sun',
                        'MALAM' => 'heroicon-o-moon',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'PAGI'  => 'success',
                        'MALAM' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'PAGI'  => 'Pagi',
                        'MALAM' => 'Malam',
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->recordActions([
                /* ================= KENDALA ================= */
                Action::make('kelola_kendala')
                    ->label(fn ($record) => $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->color(fn ($record) => $record->kendala ? 'info' : 'gray')
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi')
                    ->schema([
                        Textarea::make('kendala')
                            ->label('Catatan Kendala')
                            ->required()
                            ->rows(4),
                    ])
                    ->mountUsing(fn ($form, $record) =>
                        $form->fill(['kendala' => $record->kendala])
                    )
                    ->action(function (array $data, $record): void {
                        $record->update([
                            'kendala' => trim($data['kendala']),
                        ]);

                        Notification::make()
                            ->title('Kendala berhasil disimpan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Manajemen Kendala')
                    ->modalWidth('lg'),

                ViewAction::make()
                    ->label('')
                    ->tooltip('Lihat Data'),

                EditAction::make()
                    ->label('')
                    ->tooltip('Edit Data')
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi'),

                /* ================= DELETE ================= */
                DeleteAction::make()
                    ->label('')
                    ->tooltip('Hapus Data')
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi')
                    ->before(function ($record) {

                        $hasRelation =
                            $record->modalSandings()->exists()
                            || $record->hasilSandings()->exists()
                            || $record->pegawaiSandings()->exists()
                            || $record->validasiSanding()->exists();

                        if ($hasRelation) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi Sanding ini masih memiliki data terkait.')
                                ->danger()
                                ->send();

                            // ⛔ HENTIKAN DELETE TOTAL
                            throw new Halt();
                        }
                    }),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn ($records) =>
                            $records->every(
                                fn ($r) => $r->validasiTerakhir?->status !== 'divalidasi'
                            )
                        )
                        ->before(function ($records) {

                            foreach ($records as $record) {
                                $hasRelation =
                                    $record->modalSandings()->exists()
                                    || $record->hasilSandings()->exists()
                                    || $record->pegawaiSandings()->exists()
                                    || $record->validasiSanding()->exists();

                                if ($hasRelation) {
                                    Notification::make()
                                        ->title('Gagal menghapus data terpilih')
                                        ->body('Salah satu Produksi Sanding masih memiliki relasi.')
                                        ->danger()
                                        ->send();

                                    // ⛔ STOP BULK DELETE
                                    throw new Halt();
                                }
                            }
                        }),
                ]),
            ]);
    }
}
