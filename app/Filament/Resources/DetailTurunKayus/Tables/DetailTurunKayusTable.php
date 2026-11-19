<?php

namespace App\Filament\Resources\DetailTurunKayus\Tables;

use App\Models\DetailTurunKayu;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DetailTurunKayusTable
{
    protected const PRIVILEGED_ROLES = ['super_admin', 'Super Admin', 'admin_kayu'];

    protected static function isPrivileged(): bool
    {
        $user = Auth::user();

        return $user && $user->hasAnyRole(self::PRIVILEGED_ROLES);
    }

    protected static function canModify(DetailTurunKayu $record): bool
    {
        return $record->status !== 'selesai' || self::isPrivileged();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn ($query) => $query->with([
                    'kayuMasuk.penggunaanSupplier',
                    'kayuMasuk.penggunaanKendaraanSupplier',
                ])
            )
            ->columns([
                TextColumn::make('kayuMasuk.seri')
                    ->label('Seri')
                    ->default('—')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('kayuMasuk.penggunaanSupplier.nama_supplier')
                    ->label('Supplier')
                    ->default('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_supir')
                    ->label('Nama Supir')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jumlah_kayu')
                    ->label('Jumlah Kayu')
                    ->suffix(' btg')
                    ->sortable(),

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

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'selesai' => 'success',
                        'menunggu' => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('foto')
                    ->label('Foto')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Ada File' : 'Kosong')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->url(fn ($state): ?string => $state ? Storage::url($state) : null)
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (DetailTurunKayu $record): bool => self::canModify($record)),

                DeleteAction::make()
                    ->visible(fn (DetailTurunKayu $record): bool => self::canModify($record)),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->before(function (DeleteBulkAction $action, Collection $records) {
                        if (! self::isPrivileged() && $records->contains('status', 'selesai')) {
                            Notification::make()
                                ->title('Gagal menghapus')
                                ->body('Data berstatus "selesai" hanya bisa dihapus oleh admin.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
