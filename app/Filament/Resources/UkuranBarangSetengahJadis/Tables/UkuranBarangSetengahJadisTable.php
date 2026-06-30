<?php

namespace App\Filament\Resources\UkuranBarangSetengahJadis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Models\Grade;

class UkuranBarangSetengahJadisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('grade.kategoriBarang.nama_kategori')
                    ->label('Kategori Barang')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('ukuran.dimensi')
                    ->label('Ukuran')
                    ->sortable()
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('ukuran', function ($q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%");
                        });
                    }),


                TextColumn::make('jenisBarang.kode_jenis_barang')
                    ->label('Jenis Barang')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('grade.nama_grade')
                    ->label('Grade')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                //Kategori Balang
                SelectFilter::make('kategori_barang')
                    ->label('Jenis Barang')
                    ->relationship('grade.kategoriBarang', 'nama_kategori'),

                // Filter by Jenis Barang
                SelectFilter::make('id_jenis_barang')
                    ->label('Jenis Barang')
                    ->relationship('jenisBarang', 'kode_jenis_barang'),

                // Filter by Grade
                SelectFilter::make('id_grade')
                    ->label('Grade')
                    ->options(
                        Grade::with('kategoriBarang')
                            ->get()
                            ->mapWithKeys(function ($grade) {
                                return [
                                    $grade->id => ($grade->kategoriBarang?->nama_kategori ?? '-') .
                                        ' | ' .
                                        $grade->nama_grade,
                                ];
                            })
                    )
                    ->searchable()
                    ->preload(),

            ])

            ->recordActions([
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
