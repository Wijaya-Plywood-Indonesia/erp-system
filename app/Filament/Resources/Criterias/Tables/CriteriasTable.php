<?php

namespace App\Filament\Resources\Criterias\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Grouping\Group;

class CriteriasTable
{
    /**
     * Konfigurasi Schema Tabel untuk Master Kriteria.
     * Menggunakan fitur Grouping Collapsible agar tampilan lebih rapi per kategori.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('urutan')
                    ->label('No')
                    ->sortable()
                    ->alignCenter()
                    ->rowIndex(),

                Tables\Columns\TextColumn::make('nama_kriteria')
                    ->label('Pertanyaan')
                    ->searchable()
                    ->wrap(),

                // Kolom kategori disembunyikan secara default karena sudah ada di Header Group
                Tables\Columns\TextColumn::make('kategoriBarang.nama_kategori')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bobot')
                    ->label('Bobot Nilai')
                    ->numeric(1)
                    ->badge()
                    ->color('amber')
                    ->alignCenter(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            // --- KONFIGURASI COLLAPSIBLE GROUPING ---
            ->groups([
                Group::make('id_kategori_barang')
                    ->label('Kategori Barang')
                    // Mengambil nama kategori dari relasi untuk judul group
                    ->getTitleFromRecordUsing(fn($record) => $record->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                    ->collapsible(), // Membuat group bisa diklik untuk buka/tutup
            ])
            // Menjadikan pengelompokan berdasarkan kategori sebagai tampilan standar
            ->defaultGroup('id_kategori_barang')

            ->defaultSort('urutan', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('id_kategori_barang')
                    ->label('Filter Kategori')
                    ->relationship('kategoriBarang', 'nama_kategori'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            // Fitur tambahan: Menghapus spasi kosong yang tidak perlu di mobile
            ->contentGrid([
                'md' => 1,
                'lg' => 1,
            ]);
    }
}
