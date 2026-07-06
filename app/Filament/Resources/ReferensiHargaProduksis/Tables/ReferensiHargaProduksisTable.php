<?php

namespace App\Filament\Resources\ReferensiHargaProduksis\Tables;

use App\Models\KategoriBarang;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReferensiHargaProduksisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->placeholder('-')
                ,
                TextColumn::make('harga')
                    ->label('Harga')
                    ->money('IDR')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('sub_anak_akun')
                    ->label('Sub Anak Akun')
                    ->placeholder('-')
                    ->getStateUsing(function ($record) {
                        if (!$record->subAnakAkun) {
                            return '-';
                        }

                        return "{$record->subAnakAkun->kode_sub_anak_akun} - {$record->subAnakAkun->nama_sub_anak_akun}";
                    }),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('ukuran.dimensi')
                    ->label('Ukuran')
                    ->placeholder('-'),

                TextColumn::make('kategoriBarang.nama_kategori')
                    ->label('Kategori Barang')
                    ->placeholder('-'),

                TextColumn::make('grade.nama_grade')
                    ->label('Grade')
                    ->placeholder('-'),

                TextColumn::make('kw_range')
                    ->label('KW')
                    ->getStateUsing(fn($record) => self::formatRange($record->kw_min, $record->kw_max))
                    ->placeholder('-'),

                TextColumn::make('t_range')
                    ->label('Tebal')
                    ->getStateUsing(fn($record) => self::formatRange($record->t_min, $record->t_max, 'mm'))
                    ->placeholder('-'),


            ])
            ->filters([
                SelectFilter::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->relationship('jenisKayu', 'nama_kayu'),

                SelectFilter::make('id_kategori_barang')
                    ->label('Kategori Barang')
                    ->options(
                        KategoriBarang::query()
                            ->pluck('nama_kategori', 'id')
                    ),

                SelectFilter::make('id_grade')
                    ->label('Grade')
                    ->relationship('grade', 'nama_grade'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Gabungkan pasangan nilai min/max jadi satu string range.
     * Contoh: (1, 4, 'mm') -> "1 - 4 mm"
     *         (5, null)    -> "5"  (kalau cuma salah satu terisi)
     *         (null, null) -> null (biar placeholder '-' yang jalan)
     */
    protected static function formatRange($min, $max, ?string $suffix = null): ?string
    {
        if (is_null($min) && is_null($max)) {
            return null;
        }

        $suffix = $suffix ? " {$suffix}" : '';

        if (!is_null($min) && !is_null($max)) {
            if ($min == $max) {
                return "{$min}{$suffix}";
            }

            return "{$min} - {$max}{$suffix}";
        }

        return ($min ?? $max) . $suffix;
    }
}