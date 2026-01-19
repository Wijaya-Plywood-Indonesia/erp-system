<?php

namespace App\Filament\Resources\ProduksiKedis\Tables;

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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Grouping\Group;

class ProduksiKedisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                TextColumn::make('kendala')
                    ->label('Kendala Produksi')
                    ->getStateUsing(fn ($record) =>
                        blank($record->getRawOriginal('kendala'))
                            ? 'Tidak ada kendala'
                            : $record->getRawOriginal('kendala')
                    ),

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
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->defaultSort('tanggal', 'desc')

            ->filters([
                Filter::make('tanggal')
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
                                fn (Builder $q, $date) =>
                                    $q->whereDate('tanggal', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $q, $date) =>
                                    $q->whereDate('tanggal', '<=', $date)
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
                    ->mountUsing(fn ($form, $record) =>
                        $form->fill(['kendala' => $record->kendala ?? ''])
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
                    ->modalHeading(fn ($record) =>
                        $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala'
                    )
                    ->modalSubmitActionLabel('Simpan'),

                EditAction::make()
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi'),

                ViewAction::make(),

                DeleteAction::make()
                    ->visible(fn ($record) => $record->validasiTerakhir?->status !== 'divalidasi')
                    ->before(function ($record) {

                        $hasRelation =
                            $record->detailMasukKedi()->exists()
                            || $record->detailBongkarKedi()->exists()
                            || $record->detailPegawaiKedi()->exists()
                            || $record->validasiKedi()->exists();

                        if ($hasRelation) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi Kedi ini masih memiliki data didalamnya yang terkait.')
                                ->danger()
                                ->send();

                            throw new Halt();
                        }
                    }),
            ])

            ->groups([
                Group::make('status')
                    ->label('Status')
                    ->collapsible()
                    ->getTitleFromRecordUsing(fn ($record) => ucfirst($record->status)),
            ])
            ->defaultGroup('status')
            ->groupingSettingsHidden()

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
                                    $record->detailMasukKedi()->exists()
                                    || $record->detailBongkarKedi()->exists()
                                    || $record->detailPegawaiKedi()->exists()
                                    || $record->validasiKedi()->exists();

                                if ($hasRelation) {
                                    Notification::make()
                                        ->title('Gagal menghapus data terpilih')
                                        ->body('Salah satu Produksi Kedi masih memiliki relasi.')
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
