<?php

namespace App\Filament\Resources\ProduksiStiks\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Exceptions\Halt;

class ProduksiStiksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('validasiTerakhir.status')
                    ->label('Status Validasi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'divalidasi'   => 'success',
                        'ditangguhkan' => 'warning',
                        'ditolak'      => 'danger',
                        default        => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'divalidasi'   => 'heroicon-m-check-circle',
                        'ditolak'      => 'heroicon-m-x-circle',
                        'ditangguhkan' => 'heroicon-m-exclamation-circle',
                        default        => 'heroicon-m-clock',
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('kendala')
                    ->label('Kendala')
                    ->limit(30)
                    ->placeholder('Tidak ada kendala')
                    ->tooltip(fn ($record): ?string => $record->kendala)
                    ->toggleable(),

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
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($q, $date) =>
                                    $q->whereDate('tanggal_produksi', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn ($q, $date) =>
                                    $q->whereDate('tanggal_produksi', '<=', $date)
                            );
                    }),
            ])

            ->recordActions([
                Action::make('kelola_kendala')
                    ->label(fn ($record) => $record->kendala ? 'Edit Kendala' : 'Tambah Kendala')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->color(fn ($record) => $record->kendala ? 'info' : 'gray')
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi')
                    ->schema([
                        Textarea::make('kendala')
                            ->label('Catatan Kendala Lapangan')
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

                EditAction::make()
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi'),

                ViewAction::make(),

                DeleteAction::make()
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi')
                    ->before(function ($record) {

                        $hasRelation =
                            $record->detailPegawaiStik()->exists()
                            || $record->detailMasukStik()->exists()
                            || $record->detailHasilStik()->exists()
                            || $record->validasiStik()->exists();

                        if ($hasRelation) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi Stik ini masih memiliki data didalamnya yang terkait.')
                                ->danger()
                                ->send();

                            // ⛔ HENTIKAN PROSES DELETE
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
                                    $record->detailPegawaiStik()->exists()
                                    || $record->detailMasukStik()->exists()
                                    || $record->detailHasilStik()->exists()
                                    || $record->validasiStik()->exists();

                                if ($hasRelation) {
                                    Notification::make()
                                        ->title('Gagal menghapus data terpilih')
                                        ->body('Salah satu data Produksi Stik masih memiliki relasi.')
                                        ->danger()
                                        ->send();

                                    throw new Halt();
                                }
                            }
                        }),
                ]),
            ]);
    }
}
