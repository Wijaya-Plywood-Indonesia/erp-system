<?php

namespace App\Filament\Resources\HasilPilihPlywoods\Tables;

use App\Models\SerahTerimaGudangSatu;
use App\Models\SerahTerimaTriplekCacat;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HasilPilihPlywoodsTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->modifyQueryUsing(
                fn (Builder $query) => $query->with([
                    'pegawais',
                    'barangSetengahJadiHp.ukuran',
                    'barangSetengahJadiHp.jenisBarang',
                    'barangSetengahJadiHp.grade',
                    'serahTerimaGudangSatu',
                    'serahTerimaTriplekCacat',
                ])
            )

            /**
             * =====================================
             * DEFAULT GROUP BY PEGAWAI
             * =====================================
             */
            ->groups([
                Group::make('id') // ⚠️ kolom asli (AMAN)
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(function ($record) {
                        if ($record->pegawais->isEmpty()) {
                            return 'Pegawai: -';
                        }

                        return ''.
                            $record->pegawais
                                ->pluck('nama_pegawai')
                                ->implode(' & ');
                    })
                    ->collapsible(),
            ])

            // ⬇️ PENTING: langsung ter-group seperti dempul
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
                        $b = $record->barangSetengahJadiHp;
                        if (! $b) {
                            return '-';
                        }

                        return ($b->jenisBarang?->nama_jenis_barang ?? '-').' | '.
                            ($b->ukuran?->nama_ukuran ?? '-').' | '.
                            ($b->grade?->nama_grade ?? '-');
                    })
                    ->wrap(),

                TextColumn::make('jenis_cacat')
                    ->label('Jenis Cacat')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'reject' => 'danger',
                        'reparasi' => 'warning',
                        'selesai' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('jumlah')
                    ->label('Cacat')
                    ->numeric()
                    ->alignCenter()
                    ->color('danger')
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Total Cacat')),

                TextColumn::make('jumlah_bagus')
                    ->label('Bagus')
                    ->numeric()
                    ->alignCenter()
                    ->color('success')
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Total Bagus')),

                TextColumn::make('total_kerja')
                    ->label('Total Dikerjakan')
                    ->getStateUsing(fn ($record) => $record->jumlah + $record->jumlah_bagus)
                    ->alignCenter()
                    ->weight('bold'),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->wrap(),

                TextColumn::make('status_serah')
                    ->label('Status Serah (Bagus)')
                    ->getStateUsing(function ($record) {
                        $serah = $record->serahTerimaGudangSatu;

                        if (! $serah) {
                            return 'Belum Diserahkan';
                        }

                        return $serah->diterima_oleh === '-' ? 'Menunggu Diterima' : 'Diterima';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $serah = $record->serahTerimaGudangSatu;

                        if (! $serah) {
                            return 'gray';
                        }

                        return $serah->diterima_oleh === '-' ? 'warning' : 'success';
                    }),

                TextColumn::make('status_serah_cacat')
                    ->label('Status Serah (Cacat)')
                    ->getStateUsing(function ($record) {
                        $serah = $record->serahTerimaTriplekCacat;

                        if (! $serah) {
                            return 'Belum Diserahkan';
                        }

                        $tujuan = $serah->labelTujuan;

                        return $serah->diterima_oleh === '-'
                            ? "Menunggu Diterima ({$tujuan})"
                            : "Diterima ({$tujuan})";
                    })
                    ->badge()
                    ->color(function ($record) {
                        $serah = $record->serahTerimaTriplekCacat;

                        if (! $serah) {
                            return 'gray';
                        }

                        return $serah->diterima_oleh === '-' ? 'warning' : 'success';
                    }),
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
                Action::make('serah')
                    ->label('Serah ke Gudang 1')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan barang ini ke Gudang 1?')
                    ->modalDescription(fn ($record) => 'Jumlah bagus: '.($record->jumlah_bagus ?? 0).' pcs. Barang akan masuk antrian penerimaan Gudang 1.')
                    ->visible(fn ($record) => ! $record->serahTerimaGudangSatu && $record->jumlah_bagus > 0)
                    ->action(function ($record) {
                        try {
                            SerahTerimaGudangSatu::create([
                                'id_hasil_pilih_plywood' => $record->id,
                                'tujuan' => 'gudang_satu',
                                'diserahkan_oleh' => Auth::user()->name,
                                'diterima_oleh' => '-',
                                'status' => 'Menunggu',
                            ]);

                            Notification::make()
                                ->title('Berhasil diserahkan ke Gudang 1')
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

                Action::make('serah_cacat')
                    ->label('Serah Barang Cacat')
                    ->color('danger')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan barang cacat ini?')
                    ->modalDescription(fn ($record) => 'Jumlah cacat: '.($record->jumlah ?? 0).' pcs. Barang akan tersedia di Dempul dan Tembel Triplek — siapa yang menerima duluan yang dapat.')
                    ->visible(fn ($record) => ! $record->serahTerimaTriplekCacat && $record->jumlah > 0)
                    ->action(function ($record) {
                        try {
                            DB::transaction(function () use ($record) {
                                $fresh = $record->newQuery()->lockForUpdate()->find($record->id);

                                if ($fresh->serahTerimaTriplekCacat()->exists()) {
                                    throw new \RuntimeException('Barang cacat ini sudah pernah diserahkan.');
                                }

                                SerahTerimaTriplekCacat::create([
                                    'id_hasil_pilih_plywood' => $fresh->id,
                                    'tujuan' => null, // belum ditentukan — diisi saat diterima
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'status' => 'Menunggu Diterima',
                                ]);
                            });

                            Notification::make()
                                ->title('Berhasil diserahkan')
                                ->body('Barang tersedia di Dempul dan Tembel Triplek.')
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
                    ->hidden(fn ($record) => (bool) $record->serahTerimaGudangSatu
                        || ($record->serahTerimaTriplekCacat && $record->serahTerimaTriplekCacat->diterima_oleh !== '-')),

                DeleteAction::make()
                    ->hidden(fn ($record) => (bool) $record->serahTerimaGudangSatu
                        || (bool) $record->serahTerimaTriplekCacat),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
