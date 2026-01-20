<?php

namespace App\Filament\Resources\ProduksiHotPresses\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class ProduksiHotPressesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_produksi', 'desc')

            ->columns([
                TextColumn::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('kendala')
                    ->label('Kendala')
                    ->limit(40)
                    ->placeholder('Tidak ada kendala')
                    ->tooltip(fn ($record) => $record->kendala)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('validasiTerakhir.status')
                    ->label('Validasi')
                    ->colors([
                        'success' => 'divalidasi',
                        'warning' => 'ditangguhkan',
                        'danger'  => 'ditolak',
                    ])
                    ->icons([
                        'heroicon-o-check-circle'       => 'divalidasi',
                        'heroicon-o-x-circle'           => 'ditolak',
                        'heroicon-o-exclamation-circle' => 'ditangguhkan',
                    ])
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                Filter::make('tanggal_produksi')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($q, $date) => $q->whereDate('tanggal_produksi', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn ($q, $date) => $q->whereDate('tanggal_produksi', '<=', $date)
                            );
                    }),
            ])

            ->recordActions([
                /* ================= KENDALA ================= */
                Action::make('kelola_kendala')
                    ->label(fn ($record) => $record->kendala ? 'Edit Kendala' : 'Tambah Kendala')
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

                EditAction::make()
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi'),

                ViewAction::make(),

                /* ================= DELETE ================= */
                DeleteAction::make()
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi')
                    ->before(function ($record) {

                        $hasRelation =
                            $record->detailPegawaiHp()->exists()
                            || $record->veneerBahanHp()->exists()
                            || $record->platformBahanHp()->exists()
                            || $record->platformHasilHp()->exists()
                            || $record->triplekHasilHp()->exists()
                            || $record->bahanPenolongHp()->exists()
                            || $record->rencanaKerjaHp()->exists()
                            || $record->validasiHp()->exists();

                        if ($hasRelation) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi HotPress ini masih memiliki data terkait.')
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
                        ->before(function ($records) {

                            foreach ($records as $record) {
                                $hasRelation =
                                    $record->detailPegawaiHp()->exists()
                                    || $record->veneerBahanHp()->exists()
                                    || $record->platformBahanHp()->exists()
                                    || $record->platformHasilHp()->exists()
                                    || $record->triplekHasilHp()->exists()
                                    || $record->bahanPenolongHp()->exists()
                                    || $record->rencanaKerjaHp()->exists()
                                    || $record->validasiHp()->exists();

                                if ($hasRelation) {
                                    Notification::make()
                                        ->title('Gagal menghapus data terpilih')
                                        ->body('Salah satu Produksi HotPress masih memiliki relasi.')
                                        ->danger()
                                        ->send();

                                    // ⛔ HENTIKAN BULK DELETE
                                    throw new Halt();
                                }
                            }
                        }),
                ]),
            ]);
    }
}
