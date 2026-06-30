<?php

namespace App\Filament\Resources\DetailKomposisis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;

// Models
use App\Models\Komposisi;

class DetailKomposisisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /**
             * ======================
             * 🔥 GROUPING KOMPOSISI
             * ======================
             */
            ->groups([
                Group::make('id_komposisi')
                    ->label('Komposisi')
                    ->getTitleFromRecordUsing(function ($record) {
                        $bsj = $record->komposisi?->barangSetengahJadiHp;

                        if (!$bsj) {
                            return 'Komposisi Tidak Diketahui';
                        }

                        $kategori = $bsj->grade?->kategoriBarang?->nama_kategori ?? '-';
                        $ukuran  = $bsj->ukuran?->nama_ukuran ?? '-';
                        $jenis = $bsj->jenisBarang?->nama_jenis_barang ?? '-';
                        $grade = $bsj->grade?->nama_grade ?? '-';

                        return "{$kategori} | {$ukuran} | {$jenis} | {$grade}";
                    })
                    ->collapsible(true), // <-- PERUBAHAN DI SINI: Menyebabkan grup tertutup secara default
            ])

            /**
             * ======================
             * 📋 COLUMNS (DETAIL)
             * ======================
             */
            ->columns([

                // Barang Veneer (child row)
                TextColumn::make('barang_setengah_jadi_hp_detail')
                    ->label('Bahan (Veneer)')
                    ->getStateUsing(function ($record) {
                        $bsj = $record->barangSetengahJadiHp;

                        if (!$bsj) {
                            return '—';
                        }

                        $ukuran = $bsj->ukuran?->nama_ukuran ?? '-';
                        $grade = $bsj->grade?->nama_grade ?? '-';
                        $jenis = $bsj->jenisBarang?->nama_jenis_barang ?? '-';

                        return "{$jenis} | {$ukuran} | {$grade}";
                    })
                    ->searchable(),

                TextColumn::make('lapisan')
                    ->label('Lapisan')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'faceback' => 'success',
                        'core'  => 'warning',
                        default => 'gray',
                    }),
            ])

            /**
             * ======================
             * 🔎 FILTER
             * ======================
             */
            ->filters([
                SelectFilter::make('id_komposisi')
                    ->label('Komposisi')
                    ->options(
                        Komposisi::with([
                            'barangSetengahJadiHp.ukuran',
                            'barangSetengahJadiHp.grade.kategoriBarang',
                            'barangSetengahJadiHp.jenisBarang'
                        ])->get()->mapWithKeys(function ($k) {
                            $bsj = $k->barangSetengahJadiHp;

                            if (!$bsj) {
                                return [];
                            }

                            $kategori = $bsj->grade?->kategoriBarang?->nama_kategori ?? '-';
                            $ukuran = $bsj->ukuran?->nama_ukuran ?? '-';
                            $jenis = $bsj->jenisBarang?->nama_jenis_barang ?? '-';
                            $grade = $bsj->grade?->nama_grade ?? '-';

                            return [
                                $k->id => "{$kategori} | {$ukuran} | {$jenis} | {$grade}"
                            ];
                        })
                    )
                    ->searchable()
                    ->preload(),
            ])

            /**
             * ======================
             * ✏️ ACTIONS
             * ======================
             */
            ->actions([
                EditAction::make(),
            ])

            /**
             * ======================
             * 🧹 BULK
             * ======================
             */
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])

            ->defaultGroup('id_komposisi'); 
    }
}