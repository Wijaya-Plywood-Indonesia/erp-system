<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Support\Exceptions\Halt;

class ProduksiGrajiTripleksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status Produksi')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('kendala')
                    ->label('Kendala Produksi')
                    ->getStateUsing(fn ($record) =>
                        blank($record->kendala) ? 'Tidak ada kendala' : $record->kendala
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                BadgeColumn::make('validasiTerakhir.status')
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

            ->defaultSort('tanggal_produksi', 'desc')

            ->filters([
                Filter::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal')->native(false),
                        DatePicker::make('until')->label('Sampai Tanggal')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) =>
                        $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('tanggal_produksi', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('tanggal_produksi', '<=', $d))
                    ),

                SelectFilter::make('status')
                    ->label('Status Produksi')
                    ->options([
                        'graji manual'   => 'Graji Manual',
                        'graji otomatis' => 'Graji Otomatis',
                    ]),
            ])

            ->recordActions([
                Action::make('kelola_kendala')
                    ->label(fn ($record) => $record->kendala ? 'Edit Kendala' : 'Tambah Kendala')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->color(fn ($record) => $record->kendala ? 'info' : 'gray')
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi')
                    ->schema([
                        Textarea::make('kendala')
                            ->label('Kendala Produksi')
                            ->required()
                            ->rows(4),
                    ])
                    ->mountUsing(fn ($form, $record) =>
                        $form->fill(['kendala' => $record->kendala])
                    )
                    ->action(function (array $data, $record) {
                        $record->update([
                            'kendala' => trim($data['kendala']),
                        ]);

                        Notification::make()
                            ->title('Kendala berhasil disimpan')
                            ->success()
                            ->send();
                    }),

                EditAction::make()
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi'),

                ViewAction::make(),

                // 🗑️ DELETE — SAMA DENGAN PRODUKSI STIK
                DeleteAction::make()
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi')
                    ->before(function ($record) {

                        $hasRelation =
                            $record->pegawaiGrajiTriplek()->exists()
                            || $record->masukGrajiTriplek()->exists()
                            || $record->hasilGrajiTriplek()->exists()
                            || $record->validasiGrajiTriplek()->exists();

                        if ($hasRelation) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi Graji Triplek ini masih memiliki data yang terkait.')
                                ->danger()
                                ->send();

                            // ⛔ HENTIKAN DELETE
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
                                    $record->pegawaiGrajiTriplek()->exists()
                                    || $record->masukGrajiTriplek()->exists()
                                    || $record->hasilGrajiTriplek()->exists()
                                    || $record->validasiGrajiTriplek()->exists();

                                if ($hasRelation) {
                                    Notification::make()
                                        ->title('Gagal menghapus data terpilih')
                                        ->body('Salah satu Produksi Graji Triplek masih memiliki relasi.')
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
