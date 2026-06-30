<?php

namespace App\Filament\Resources\DetailMesins\Tables;

use App\Models\DetailMesin;
use App\Models\ProduksiPressDryer; // Pastikan import Model Parent
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class DetailMesinsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mesin.nama_mesin')
                    ->label('Mesin Dryer')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('jam_kerja_mesin')
                    ->label('Jam Kerja Mesin')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Detail')
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi')

                    // --- PENYESUAIAN PENTING ---
                    // Ini adalah "Jaring Pengaman" agar nilai tidak pernah 0.
                    ->mutateFormDataUsing(function (array $data, $livewire): array {
                        $shift = '';

                        // Cek 1: Apakah kita di Relation Manager? (Punya ownerRecord)
                        if (isset($livewire->ownerRecord) && $livewire->ownerRecord instanceof ProduksiPressDryer) {
                            $shift = $livewire->ownerRecord->shift ?? '';
                        }
                        // Cek 2: Apakah user memilih Produksi via Form? (Form Hybrid Anda)
                        elseif (isset($data['id_produksi_dryer'])) {
                            $produksi = ProduksiPressDryer::find($data['id_produksi_dryer']);
                            $shift = $produksi->shift ?? '';
                        }

                        // Logika Utama: Paksa isi jam kerja
                        $data['jam_kerja_mesin'] = (strtolower($shift) === 'pagi') ? 11 : 12;

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi')
                    // Opsional: Terapkan juga di Edit agar konsisten
                    ->mutateFormDataUsing(function (array $data, $livewire): array {
                        $shift = '';
                        if (isset($livewire->ownerRecord) && $livewire->ownerRecord instanceof ProduksiPressDryer) {
                            $shift = $livewire->ownerRecord->shift ?? '';
                        } elseif (isset($data['id_produksi_dryer'])) {
                            $produksi = ProduksiPressDryer::find($data['id_produksi_dryer']);
                            $shift = $produksi->shift ?? '';
                        }

                        if ($shift) {
                            $data['jam_kerja_mesin'] = (strtolower($shift) === 'pagi') ? 11 : 12;
                        }
                        return $data;
                    }),

                DeleteAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih')
                        ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
                ]),
            ]);
    }
}