<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Models\ProduksiKedi;
use App\Models\ProduksiPressDryer;
use App\Models\SerahTerimaVeneerBasah;
use App\Services\GudangVeneerBasahService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * PENTING: RelationManager Filament otomatis menambahkan constraint
 * "WHERE id_produksi_dryer/kedi = <pemilik>" dari relasi hasMany-nya
 * SEBELUM modifyQueryUsing() dijalankan. Karena baris "pool" (belum
 * diklaim siapa pun) punya id_produksi_dryer/kedi = NULL, constraint
 * itu akan selalu menyembunyikannya — makanya kita override
 * getTableQuery() untuk lepas dari batasan relasi tersebut sepenuhnya.
 */
class SerahTerimaVeneerBasahRelationManager extends RelationManager
{
    protected static string $relationship = 'serahTerimaVeneerBasah';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Terima Veneer Basah (dari Gudang)';
    }

    protected function getTipe(): string
    {
        return match (get_class($this->getOwnerRecord())) {
            ProduksiPressDryer::class => 'dryer',
            ProduksiKedi::class => 'kedi',
            default => 'unknown',
        };
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    /**
     * Query manual — TIDAK lewat relasi hasMany, supaya baris pool
     * yang id_produksi_dryer/kedi-nya masih NULL tetap ikut tampil.
     */
    protected function getTableQuery(): ?Builder
    {
        $tipe = $this->getTipe();
        $ownerId = $this->getOwnerRecord()->id;
        $kolomOwner = $tipe === 'dryer' ? 'id_produksi_dryer' : 'id_produksi_kedi';

        return SerahTerimaVeneerBasah::query()
            ->with(['detail.ukuran', 'detail.jenisKayu'])
            ->where('tujuan', $tipe)
            ->where(function (Builder $q) use ($kolomOwner, $ownerId) {
                // Pool bersama: semua yang masih Menunggu (siapa pun bisa terima)
                $q->where('status', 'Menunggu')
                    // + riwayat yang SUDAH terikat ke sesi produksi ini (Diterima/Ditolak)
                    ->orWhere($kolomOwner, $ownerId);
            })
            ->orderByRaw("FIELD(status, 'Menunggu', 'Diterima', 'Ditolak')")
            ->orderByDesc('created_at');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('detail.jenisKayu.nama_kayu')
                    ->label('Jenis Kayu'),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->getStateUsing(fn ($record) => $record->detail?->ukuran
                        ? "{$record->detail->ukuran->panjang}x{$record->detail->ukuran->lebar}x{$record->detail->ukuran->tebal}"
                        : '-'),

                TextColumn::make('detail.kw')
                    ->label('KW')
                    ->alignCenter(),

                TextColumn::make('detail.qty_lembar')
                    ->label('Lembar')
                    ->numeric(),

                TextColumn::make('detail.m3')
                    ->label('m3')
                    ->numeric(decimalPlaces: 4),

                TextColumn::make('diserahkan_oleh')
                    ->label('Dari Gudang')
                    ->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Diterima' => 'success',
                        'Ditolak' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('diterima_oleh')
                    ->label('Diterima Oleh')
                    ->formatStateUsing(fn ($state) => $state === '-' ? '-' : $state)
                    ->toggleable(),

                TextColumn::make('alasan_tolak')
                    ->label('Alasan Tolak')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('terima')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'Menunggu')
                    ->action(function ($record) {
                        try {
                            $tipe = $this->getTipe();
                            $ownerId = $this->getOwnerRecord()->id;

                            app(GudangVeneerBasahService::class)->terima(
                                row: $record,
                                idProduksiDryer: $tipe === 'dryer' ? $ownerId : null,
                                idProduksiKedi: $tipe === 'kedi' ? $ownerId : null,
                            );
                            Notification::make()->title('Veneer Basah Berhasil Diterima')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal Menerima')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('tolak')
                    ->label('Tolak')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'Menunggu')
                    ->form([
                        Textarea::make('alasan_tolak')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(GudangVeneerBasahService::class)->tolak($record, $data['alasan_tolak']);
                            Notification::make()->title('Veneer Basah Ditolak')->warning()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Gagal Menolak')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}