<?php

namespace App\Filament\Resources\ProduksiSandingJoints\Tables;

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

class ProduksiSandingJointsTable
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

                TextColumn::make('kendala')
                    ->label('Kendala')
                    ->limit(50)
                    ->placeholder('Tidak ada kendala')
                    ->tooltip(fn ($record) => $record->kendala)
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
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn ($q, $date) =>
                                    $q->whereDate('tanggal_produksi', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
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
                            ->label('Catatan Kendala Produksi')
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
                            $record->pegawaiSandingJoint()->exists()
                            || $record->hasilSandingJoint()->exists()
                            || $record->validasiSandingJoint()->exists();

                        if ($hasRelation) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi Sanding Joint ini masih memiliki data terkait.')
                                ->danger()
                                ->send();

                            // ⛔ STOP DELETE
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
                                    $record->pegawaiSandingJoint()->exists()
                                    || $record->hasilSandingJoint()->exists()
                                    || $record->validasiSandingJoint()->exists();

                                if ($hasRelation) {
                                    Notification::make()
                                        ->title('Gagal menghapus data terpilih')
                                        ->body('Salah satu Produksi Sanding Joint masih memiliki relasi.')
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
