<?php

namespace App\Filament\Resources\HasilPilihPlywoods\Tables;

use App\Models\SerahTerimaGudangSatu;
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

                // Kolom perhitungan total yang dikerjakan (Bagus + Cacat)
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
                    ->label('Status Serah')
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
                    ->visible(fn ($record) => ! $record->serahTerimaGudangSatu)
                    ->action(function ($record) {
                        try {
                            SerahTerimaGudangSatu::create([
                                'id_hasil_pilih_plywood' => $record->id,
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

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
