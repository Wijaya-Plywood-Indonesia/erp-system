<?php

namespace App\Filament\Resources\ValidasiPressDryers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;

class ValidasiPressDryersTable
{
    /**
     * Terkunci kalau produksi ini sudah punya validasi berstatus
     * "divalidasi" — SUPER ADMIN saja yang tetap bisa create/edit/delete
     * setelah itu (misal buat koreksi). Ini juga yang mencegah 1 produksi
     * divalidasi lebih dari sekali oleh role-role lain (lihat REVISI di
     * ProductionValidationObserver soal potong stok cuma boleh sekali).
     */
    public static function isLockedForCurrentUser($livewire): bool
    {
        $sudahDivalidasi = $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi';

        $isSuperAdmin = Auth::user()?->hasRole(['super_admin', 'Super Admin']) ?? false;

        return $sudahDivalidasi && ! $isSuperAdmin;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('role')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Create Action — HILANG kalau sudah divalidasi (kecuali super admin)
                CreateAction::make()
                    ->hidden(fn ($livewire) => static::isLockedForCurrentUser($livewire))
                    // Model owner (dan tabel) di-refresh setelah create supaya
                    // tombol Create/Edit/Delete langsung ikut ter-hidden tanpa
                    // perlu reload halaman manual — tanpa ini, relasi
                    // `validasiTerakhir` yang sudah ter-cache di $livewire->ownerRecord
                    // masih nunjuk ke data SEBELUM row baru ini dibuat.
                    ->after(function ($livewire) {
                        $livewire->ownerRecord?->unsetRelation('validasiTerakhir');
                    }),
            ])
            ->recordActions([
                // Edit Action — HILANG kalau sudah divalidasi (kecuali super admin)
                EditAction::make()
                    ->hidden(fn ($livewire) => static::isLockedForCurrentUser($livewire))
                    ->after(function ($livewire) {
                        $livewire->ownerRecord?->unsetRelation('validasiTerakhir');
                    }),

                // Delete Action — HILANG kalau sudah divalidasi (kecuali super admin)
                DeleteAction::make()
                    ->hidden(fn ($livewire) => static::isLockedForCurrentUser($livewire))
                    ->after(function ($livewire) {
                        $livewire->ownerRecord?->unsetRelation('validasiTerakhir');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn ($livewire) => static::isLockedForCurrentUser($livewire)),
                ]),
            ]);
    }
}