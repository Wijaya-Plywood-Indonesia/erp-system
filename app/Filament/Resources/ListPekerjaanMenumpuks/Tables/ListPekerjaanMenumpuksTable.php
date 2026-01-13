<?php

namespace App\Filament\Resources\ListPekerjaanMenumpuks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\CreateAction;



class ListPekerjaanMenumpuksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Menampilkan detail barang lengkap: Jenis | Ukuran | Grade
                TextColumn::make('hasilPilihPlywood.barangSetengahJadiHp.id')
                    ->label('Detail Barang')
                    ->formatStateUsing(function ($record) {
                        $barang = $record->hasilPilihPlywood->barangSetengahJadiHp;
                        return ($barang->jenisBarang->nama_jenis_barang ?? '-') . ' | ' .
                               ($barang->ukuran->nama_ukuran ?? '-') . ' | ' .
                               ($barang->grade->nama_grade ?? '-');
                    })
                    ->description(function ($record) {
                        // REVISI: Menggunakan 'tanggal_produksi' sesuai Model ProduksiPilihPlywood
                        $tanggal = $record->hasilPilihPlywood->produksiPilihPlywood->tanggal_produksi ?? null;
                        
                        return $tanggal 
                            ? "Sumber: Hasil Pilih Plywood (" . \Carbon\Carbon::parse($tanggal)->format('d M Y') . ")"
                            : "Sumber: Tanggal tidak ditemukan";
                    })
                    ->searchable(),

                TextColumn::make('hasilPilihPlywood.jenis_cacat')
                    ->label('Jenis Cacat')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('jumlah_asal')
                    ->label('Jumlah Barang')
                    ->alignCenter(),

                TextColumn::make('jumlah_selesai')
                    ->label('Selesai')
                    ->alignCenter(),

                TextColumn::make('jumlah_belum_selesai')
                    ->label('Sisa')
                    ->color('warning')
                    ->weight('bold')
                    ->alignCenter(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'selesai' => 'success',
                        'belum selesai' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'belum selesai' => 'Belum Selesai',
                    'selesai' => 'Selesai',
                ])
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Pekerjaan'),
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
}