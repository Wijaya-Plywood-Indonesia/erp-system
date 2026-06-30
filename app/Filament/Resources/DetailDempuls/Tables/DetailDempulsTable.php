<?php

namespace App\Filament\Resources\DetailDempuls\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action; // Custom Action
use Filament\Notifications\Notification;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;

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
                fn(Builder $query) =>
                $query->with([
                    'pegawais',
                    'barangSetengahJadi.ukuran',
                    'barangSetengahJadi.jenisBarang',
                    'barangSetengahJadi.grade.kategoriBarang',
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

                        return 'Pegawai: ' .
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
                        $b = $record->barangSetengahJadi;
                        if (!$b) return '-';

                        return ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                            ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                            ($b->grade?->nama_grade ?? '-') . ' | ' .
                            ($b->jenisBarang?->nama_jenis_barang ?? '-');
                    })
                    ->wrap(),

                TextColumn::make('modal')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('hasil')
                    ->numeric()
                    ->sortable()
                    ->color(fn($record) => $record->hasil < $record->modal ? 'danger' : 'success'),

                TextColumn::make('nomor_palet')
                    ->label('No. Palet')
                    ->numeric()
                    ->sortable(),
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
                DeleteAction::make()
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
