<?php

namespace App\Filament\Resources\DetailDempuls\Tables;

use App\Models\SerahTerimaTriplekJadi;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class DetailDempulsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /**
             * =====================================
             * OPTIMASI QUERY
             * =====================================
             */
            ->modifyQueryUsing(
                fn (Builder $query) => $query->with([
                    'pegawais',
                    'barangSetengahJadi.ukuran',
                    'barangSetengahJadi.jenisBarang',
                    'barangSetengahJadi.grade.kategoriBarang',
                    'serahTerimaTriplekCacat.hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                    'serahTerimaTriplekCacat.hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                    'serahTerimaTriplekCacat.hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang',
                ])
            )

            /**
             * =====================================
             * GROUP BY PEGAWAI
             * =====================================
             */
            ->groups([
                Group::make('id')
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(function ($record) {
                        if ($record->pegawais->isEmpty()) {
                            return 'Pegawai: -';
                        }

                        return 'Pegawai: '.
                            $record->pegawais
                                ->pluck('nama_pegawai')
                                ->implode(' & ');
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('id')

            /**
             * =====================================
             * COLUMNS
             * =====================================
             */
            ->columns([
                TextColumn::make('barang')
                    ->label('Barang')
                    ->getStateUsing(function ($record) {
                        // Prioritaskan barang dari serah terima cacat (sumber sebenarnya),
                        // fallback ke barangSetengahJadi langsung kalau relasi cacat kosong.
                        $b = $record->serahTerimaTriplekCacat?->barangSetengahJadi
                            ?? $record->barangSetengahJadi;

                        if (! $b) {
                            return '-';
                        }

                        return ($b->grade?->kategoriBarang?->nama_kategori ?? '-').' | '.
                            ($b->ukuran?->nama_ukuran ?? '-').' | '.
                            ($b->grade?->nama_grade ?? '-').' | '.
                            ($b->jenisBarang?->nama_jenis_barang ?? '-');
                    })
                    ->wrap(),

                TextColumn::make('modal')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('hasil')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($record) => $record->hasil < $record->modal ? 'danger' : 'success'),

                TextColumn::make('sisa_serah_terima')
                    ->label('Sisa (Serah Terima)')
                    ->getStateUsing(fn ($record) => $record->serahTerimaTriplekCacat?->sisa ?? '-')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nomor_palet')
                    ->label('No. Palet')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status_serah')
                    ->label('Status Serah')
                    ->getStateUsing(fn ($record) => $record->diserahkan_at ? 'Sudah Diserahkan' : 'Belum Diserahkan')
                    ->badge()
                    ->color(fn ($record) => $record->diserahkan_at ? 'success' : 'gray'),
            ])

            /**
             * =====================================
             * ACTIONS
             * =====================================
             */
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('serahkan')
                    ->label('Serahkan')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->diserahkan_at === null && $record->hasil > 0)
                    ->schema(function ($record) {
                        $record->loadMissing([
                            'barangSetengahJadi.ukuran',
                            'barangSetengahJadi.jenisBarang',
                            'barangSetengahJadi.grade',
                        ]);

                        $b = $record->serahTerimaTriplekCacat?->barangSetengahJadi
                            ?? $record->barangSetengahJadi;

                        return [
                            Grid::make(2)->schema([
                                Placeholder::make('info_barang')
                                    ->label('Barang')
                                    ->content($b?->jenisBarang?->nama_jenis_barang ?? '-'),
                                Placeholder::make('info_grade')
                                    ->label('Grade')
                                    ->content($b?->grade?->nama_grade ?? '-'),
                                Placeholder::make('info_ukuran')
                                    ->label('Ukuran')
                                    ->content($b?->ukuran?->nama_ukuran ?? '-'),
                                Placeholder::make('info_palet')
                                    ->label('No. Palet')
                                    ->content((string) ($record->nomor_palet ?? '-')),
                                Placeholder::make('info_hasil')
                                    ->label('Hasil (Jadi Bagus)')
                                    ->content(new HtmlString('<strong>'.number_format((float) $record->hasil).' lembar</strong>')),
                            ]),
                        ];
                    })
                    ->modalHeading(fn ($record) => 'Serahkan Hasil Dempul — Palet '.$record->nomor_palet)
                    ->modalDescription(fn ($record) => 'Barang hasil perbaikan Dempul ('.number_format((float) $record->hasil).' lembar) akan diserahkan ke Pilih Plywood sebagai barang bagus.')
                    ->modalSubmitActionLabel('Serahkan')
                    ->modalWidth('md')
                    ->requiresConfirmation(false)
                    ->action(function ($record) {
                        try {
                            DB::transaction(function () use ($record) {
                                // Ambil ulang + lock, hindari race condition (double serah/klik ganda)
                                $fresh = $record->newQuery()->lockForUpdate()->find($record->id);

                                if ($fresh->diserahkan_at !== null) {
                                    throw new \RuntimeException('Palet ini sudah diserahkan sebelumnya. Muat ulang halaman.');
                                }

                                if ((float) $fresh->hasil <= 0) {
                                    throw new \RuntimeException('Hasil (jumlah jadi bagus) tidak valid, tidak bisa diserahkan.');
                                }

                                if ($fresh->serahTerimaTriplekJadi()->exists()) {
                                    throw new \RuntimeException('Data serah terima untuk palet ini sudah ada.');
                                }

                                $fresh->update([
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diserahkan_at' => now(),
                                ]);

                                SerahTerimaTriplekJadi::create([
                                    'id_detail_dempul' => $fresh->id,
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'status' => 'Menunggu Diterima',
                                ]);
                            });

                            Notification::make()
                                ->title('Berhasil diserahkan')
                                ->body('Palet '.$record->nomor_palet.' ('.number_format((float) $record->hasil).' lembar) diserahkan ke Pilih Plywood.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->hidden(fn ($record) => (bool) $record->diserahkan_at),

                DeleteAction::make()
                    ->hidden(fn ($record) => (bool) $record->diserahkan_at),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
