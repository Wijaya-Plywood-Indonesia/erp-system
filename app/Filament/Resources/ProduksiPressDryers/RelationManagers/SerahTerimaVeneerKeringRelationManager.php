<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Models\ProduksiKedi;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiRepair;
use App\Models\SerahTerimaVeneerKering;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerahTerimaVeneerKeringRelationManager extends RelationManager
{
    private const ROLE_ADMIN = ['super_admin', 'Super Admin', 'admin_kayu'];

    protected static string $relationship = 'serahTerimaVeneerKering';

    protected static ?string $title = 'Serah Terima Veneer Kering';

    protected function getTipe(): string
    {
        return match (get_class($this->getOwnerRecord())) {
            ProduksiPressDryer::class => 'dryer',
            ProduksiKedi::class => 'kedi',
            ProduksiRepair::class => 'repair',
            default => 'unknown',
        };
    }

    public function table(Table $table): Table
    {
        $tipe = $this->getTipe();
        $ownerId = $this->getOwnerRecord()->id;

        return $table
            ->modifyQueryUsing(function ($query) use ($tipe, $ownerId) {
                if ($tipe === 'dryer' || $tipe === 'kedi') {
                    // Dryer & Kedi: hanya tampilkan riwayat miliknya sendiri
                    // (hasManyThrough sudah filter otomatis by FK owner)
                    return $query
                        ->with([
                            'detailHasil.ukuran',
                            'detailHasil.jenisKayu',
                            'detailBongkarKedi.ukuran',
                            'detailBongkarKedi.jenisKayu',
                        ])
                        ->orderBy('created_at', 'desc');
                }

                // Repair: reset constraint hasMany, tampilkan semua menunggu + riwayat sendiri
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                return $query
                    ->with([
                        'detailHasil.ukuran',
                        'detailHasil.jenisKayu',
                        'detailBongkarKedi.ukuran',
                        'detailBongkarKedi.jenisKayu',
                    ])
                    ->where(function ($q) use ($ownerId) {
                        $q->where('diterima_oleh', '-')
                            ->orWhere('id_produksi_repair', $ownerId);
                    })
                    ->orderBy('diterima_oleh', 'asc')
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('tipe_sumber')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'dryer' => 'Press Dryer',
                        'kedi' => 'Kedi',
                        default => '-',
                    })
                    ->color(fn ($state) => match ($state) {
                        'dryer' => 'info',
                        'kedi' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->getStateUsing(fn ($record) => match ($record->tipe_sumber) {
                        'dryer' => $record->detailHasil?->no_palet ?? '-',
                        'kedi' => $record->detailBongkarKedi?->no_palet ?? '-',
                        default => '-',
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->getStateUsing(fn ($record) => match ($record->tipe_sumber) {
                        'dryer' => $record->detailHasil?->ukuran?->nama_ukuran ?? '-',
                        'kedi' => $record->detailBongkarKedi?->ukuran?->nama_ukuran ?? '-',
                        default => '-',
                    }),

                TextColumn::make('jenis_kayu')
                    ->label('Jenis Kayu')
                    ->getStateUsing(fn ($record) => match ($record->tipe_sumber) {
                        'dryer' => $record->detailHasil?->jenisKayu?->nama_kayu ?? '-',
                        'kedi' => $record->detailBongkarKedi?->jenisKayu?->nama_kayu ?? '-',
                        default => '-',
                    })
                    ->badge(),

                TextColumn::make('kw')
                    ->label('KW')
                    ->getStateUsing(fn ($record) => match ($record->tipe_sumber) {
                        'dryer' => $record->detailHasil?->kw ?? '-',
                        'kedi' => $record->detailBongkarKedi?->kw ?? '-',
                        default => '-',
                    })
                    ->alignCenter(),

                TextColumn::make('isi')
                    ->label('Isi / Jumlah')
                    ->getStateUsing(fn ($record) => match ($record->tipe_sumber) {
                        'dryer' => $record->detailHasil?->isi ?? '-',
                        'kedi' => $record->detailBongkarKedi?->jumlah ?? '-',
                        default => '-',
                    })
                    ->alignCenter(),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->badge(),

                TextColumn::make('diterima_oleh')
                    ->label('Diterima Oleh')
                    ->badge()
                    ->color(fn ($state) => $state === '-' ? 'gray' : 'success')
                    ->formatStateUsing(fn ($state) => $state === '-' ? 'Menunggu' : $state),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Terima Veneer' => 'success',
                        'Serah Veneer' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->actions([
                Action::make('terima')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Terima Veneer Kering ini?')
                    // Hanya muncul kalau dibuka dari Repair DAN belum diterima
                    ->visible(fn ($record) => $tipe === 'repair' && $record->diterima_oleh === '-')
                    ->action(function ($record) use ($ownerId) {
                        DB::transaction(function () use ($record, $ownerId) {
                            $fresh = SerahTerimaVeneerKering::lockForUpdate()->find($record->id);

                            if (! $fresh || $fresh->diterima_oleh !== '-') {
                                Notification::make()
                                    ->title('Gagal: Veneer ini sudah diambil produksi lain')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $fresh->update([
                                'diterima_oleh' => Auth::user()->name.' - Produksi REPAIR',
                                'id_produksi_repair' => $ownerId,
                                'status' => 'Terima Veneer',
                            ]);
                        });

                        Notification::make()
                            ->title('Veneer Kering Berhasil Diterima')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->hasAnyRole(self::ROLE_ADMIN)),
                ]),
            ]);
    }
}
