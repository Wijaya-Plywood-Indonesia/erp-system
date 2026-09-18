<?php

namespace App\Filament\Resources\ValidasiKedis\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;

class ValidasiKedisTable
{
    /**
     * Terkunci kalau produksi Kedi ini sudah divalidasi (bongkar) —
     * SUPER ADMIN saja yang tetap bisa create/edit/delete setelah itu.
     * Ini yang mencegah 1 produksi divalidasi lebih dari sekali (lihat
     * REVISI di ProductionValidationObserver).
     */
    public static function isLockedForCurrentUser($livewire): bool
    {
        $sudahDivalidasi = (bool) $livewire->ownerRecord?->isBongkarDivalidasi();

        $isSuperAdmin = Auth::user()?->hasRole(['super_admin', 'Super Admin']) ?? false;

        return $sudahDivalidasi && ! $isSuperAdmin;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('role')
                    ->label('Jabatan')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'divalidasi' => 'success',
                        'disetujui' => 'success',
                        'ditolak' => 'danger',
                        'ditangguhkan' => 'warning',
                        default => 'gray',
                    })
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
                //
            ])

            ->headerActions([
                // Create Action — HILANG kalau sudah divalidasi (kecuali super admin)
                CreateAction::make()
                    ->hidden(fn ($livewire) => static::isLockedForCurrentUser($livewire))
                    // Sama seperti di ValidasiPressDryersTable: relasi `validasiBongkar`
                    // yang sudah ter-cache di $livewire->ownerRecord perlu di-unset
                    // supaya tombol langsung ke-hidden tanpa reload manual.
                    ->after(function ($livewire) {
                        $livewire->ownerRecord?->unsetRelation('validasiBongkar');
                    }),
            ])
            ->recordActions([
                // Edit Action — HILANG kalau sudah divalidasi (kecuali super admin)
                EditAction::make()
                    ->hidden(fn ($livewire) => static::isLockedForCurrentUser($livewire))
                    ->after(function ($livewire) {
                        $livewire->ownerRecord?->unsetRelation('validasiBongkar');
                    }),

                // Delete Action — HILANG kalau sudah divalidasi (kecuali super admin)
                DeleteAction::make()
                    ->hidden(fn ($livewire) => static::isLockedForCurrentUser($livewire))
                    ->after(function ($livewire) {
                        $livewire->ownerRecord?->unsetRelation('validasiBongkar');
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