<?php

namespace App\Filament\Resources\DetailTurunKayus\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use App\Models\DetailTurunKayu;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Storage;

class DetailTurunKayusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /** * MENGGUNAKAN modifyQueryUsing
             * Ini memastikan filter relasi (berdasarkan record induk/tanggal) tetap terjaga
             * sambil tetap melakukan eager loading untuk performa.
             */
            ->modifyQueryUsing(
                fn($query) =>
                $query->with([
                    'kayuMasuk.penggunaanSupplier',
                    'kayuMasuk.penggunaanKendaraanSupplier'
                ])
            )
            ->columns([
                // 1. SUPPLIER
                TextColumn::make('kayuMasuk.penggunaanSupplier.nama_supplier')
                    ->label('Supplier')
                    ->default('—')
                    ->searchable()
                    ->sortable(),

                // 2. NAMA SUPIR
                TextColumn::make('nama_supir')
                    ->label('Nama Supir')
                    ->searchable()
                    ->sortable(),

                // 3. JUMLAH KAYU
                TextColumn::make('jumlah_kayu')
                    ->label('Jumlah Kayu')
                    ->sortable(),

                // 4. NOPOL + JENIS
                TextColumn::make('kayuMasuk.penggunaanKendaraanSupplier.nopol_kendaraan')
                    ->label('Nopol & Jenis')
                    ->formatStateUsing(function ($record) {
                        $kendaraan = $record?->kayuMasuk?->penggunaanKendaraanSupplier;
                        return $kendaraan
                            ? "{$kendaraan->nopol_kendaraan} ({$kendaraan->jenis_kendaraan})"
                            : '—';
                    })
                    ->searchable()
                    ->sortable(),

                // 5. SERI
                TextColumn::make('kayuMasuk.seri')
                    ->label('Seri')
                    ->default('—')
                    ->searchable()
                    ->sortable(),

                // 6. STATUS
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'selesai' => 'success',
                        'menunggu' => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),

                // 7. FOTO
                TextColumn::make('foto')
                    ->label('Foto')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state ? 'Ada File' : 'Kosong')
                    ->color(fn($state) => $state ? 'success' : 'danger')
                    ->url(function ($state): ?string {
                        return $state ? Storage::url($state) : null;
                    })
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->filters([
                // Tambahkan filter jika diperlukan
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
