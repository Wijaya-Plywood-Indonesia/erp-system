<?php

namespace App\Filament\Resources\ProduksiKedis\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class ProduksiKedisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tgl Masuk')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('rencana_bongkar')
                    ->label('Rencana Bongkar')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('tanggal_actual_bongkar')
                    ->label('Realisasi Bongkar')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Belum Bongkar')
                    ->color(fn ($record) => 
                        (!$record->tanggal_actual_bongkar || !$record->rencana_bongkar) ? 'gray' : (
                            $record->tanggal_actual_bongkar->lt($record->rencana_bongkar) ? 'info' : (
                                $record->tanggal_actual_bongkar->eq($record->rencana_bongkar) ? 'success' : 'warning'
                            )
                        )
                    ),

                TextColumn::make('mesin.nama_mesin')
                    ->label('Mesin Kedi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        if ($record->detailMasukKedi()->doesntExist()) return 'Belum Masuk';
                        if ($record->detailBongkarKedi()->doesntExist()) return 'Belum Bongkar';
                        if (!$record->isBongkarDivalidasi()) return 'Belum Validasi';
                        return 'Sudah Validasi';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Belum Masuk' => 'gray',
                        'Belum Bongkar' => 'info',
                        'Belum Validasi' => 'warning',
                        'Sudah Validasi' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('kendala')
                    ->label('Kendala Produksi')
                    ->getStateUsing(
                        fn($record) =>
                        blank($record->getRawOriginal('kendala'))
                            ? 'Tidak ada kendala'
                            : $record->getRawOriginal('kendala')
                    )
                    ->wrap(),

                TextColumn::make('validasi')
                    ->label('Validasi')
                    ->badge()
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->getStateUsing(function ($record) {
                        return $record->validasiTerakhir?->status ?? 'Belum Validasi';
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'divalidasi' => 'success',
                            'disetujui' => 'success',
                            'ditolak' => 'danger',
                            'ditangguhkan' => 'warning',
                            default => 'gray',
                        };
                    })
                    ->icons([
                        'heroicon-o-check-circle' => fn ($state) => in_array($state, ['divalidasi', 'disetujui']),
                    ]),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                // Filter tanggal dipertahankan untuk pencarian spesifik
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
                                fn (Builder $q, $date) => $q->whereDate('tanggal', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $q, $date) => $q->whereDate('tanggal', '<=', $date)
                            );
                    }),
            ])
            ->recordActions([
                Action::make('kelola_kendala')
                    ->label('Kendala')
                    ->icon(fn($record) => $record->kendala ? 'heroicon-o-pencil-square' : 'heroicon-o-plus')
                    ->color(fn($record) => $record->kendala ? 'info' : 'warning')
                    ->schema([
                        Textarea::make('kendala')
                            ->label('Kendala')
                            ->required()
                            ->rows(4),
                    ])
                    ->mountUsing(
                        fn($form, $record) =>
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
                    ->modalHeading(
                        fn($record) =>
                        $record->kendala ? 'Perbarui Kendala' : 'Tambah Kendala'
                    )
                    ->modalSubmitActionLabel('Simpan'),

                Action::make('proses_bongkar')
                    ->label('Bongkar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'masuk' && $record->detailMasukKedi()->exists())
                    ->schema([
                        DatePicker::make('tanggal_actual_bongkar')
                            ->label('Tanggal Bongkar Aktual')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        $record->update([
                            'status' => 'bongkar',
                            'tanggal_actual_bongkar' => $data['tanggal_actual_bongkar'],
                            'tanggal_bongkar' => $data['tanggal_actual_bongkar'], // Also update the old one for compatibility
                        ]);

                        Notification::make()
                            ->title('Proses bongkar dimulai')
                            ->success()
                            ->send();

                        return redirect()->to(\App\Filament\Resources\ProduksiKedis\ProduksiKediResource::getUrl('view', ['record' => $record]) . '?relation=1');
                    })
                    ->modalHeading('Mulai Proses Bongkar')
                    ->modalSubmitActionLabel('Mulai Bongkar'),

                EditAction::make()
                    ->visible(fn($record) => 
                        $record->status === 'masuk' && 
                        !$record->detailMasukKedi()->exists() &&
                        $record->validasiTerakhir?->status !== 'divalidasi'
                    ),

                ViewAction::make(),

                DeleteAction::make()
                    ->visible(fn($record) => 
                        $record->status === 'masuk' && 
                        !$record->detailMasukKedi()->exists() &&
                        $record->validasiTerakhir?->status !== 'divalidasi'
                    )
                    ->before(function ($record) {
                        $hasRelation =
                            $record->detailMasukKedi()->exists()
                            || $record->detailBongkarKedi()->exists()
                            || $record->detailPegawaiKedi()->exists()
                            || $record->validasiKedi()->exists();

                        if ($hasRelation) {
                            Notification::make()
                                ->title('Data tidak dapat dihapus')
                                ->body('Produksi Kedi ini masih memiliki data terkait.')
                                ->danger()
                                ->send();

                            throw new Halt();
                        }
                    }),
            ])
            // Grouping dihapus agar tidak bentrok dengan Tabs
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(
                            fn($records) =>
                            $records->every(
                                fn($r) => $r->validasiTerakhir?->status !== 'divalidasi'
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
                                        ->title('Gagal menghapus data')
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