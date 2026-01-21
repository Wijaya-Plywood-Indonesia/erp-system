<?php

namespace App\Filament\Resources\HasilPilihPlywoods\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class HasilPilihPlywoodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /**
             * =====================================
             * OPTIMASI QUERY (WAJIB, SAMA DEMPUL)
             * =====================================
             */
            ->modifyQueryUsing(fn (Builder $query) =>
                $query->with([
                    'pegawais',
                    'barangSetengahJadiHp.ukuran',
                    'barangSetengahJadiHp.jenisBarang',
                    'barangSetengahJadiHp.grade',
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

                        return 'Pegawai: ' .
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
                        if (!$b) return '-';

                        return
                            ($b->jenisBarang?->nama_jenis_barang ?? '-') . ' | ' .
                            ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
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
                        'reject'   => 'danger',
                        'reparasi' => 'warning',
                        'selesai'  => 'success',
                        default    => 'gray',
                    }),

                TextColumn::make('jumlah')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold')
                    ->summarize(
                        Sum::make()->label('Total')
                    ),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->wrap(),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
