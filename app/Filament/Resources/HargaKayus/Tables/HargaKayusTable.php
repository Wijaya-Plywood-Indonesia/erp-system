<?php

namespace App\Filament\Resources\HargaKayus\Tables;

use App\Models\HargaKayuLog;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HargaKayusTable
{
    private const ROLE_ADMIN = ['admin', 'super_admin', 'Super Admin'];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jenisKayu.nama_kayu')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('panjang')
                    ->numeric()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('diameter_terkecil')
                    ->label('Min')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('diameter_terbesar')
                    ->label('Max')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('grade')
                    ->label('A / B')
                    ->formatStateUsing(fn($state) => match ((int) $state) {
                        1 => 'Grade A',
                        2 => 'Grade B',
                        default => '-',
                    })
                    ->badge()
                    ->color(fn($state) => match ((int) $state) {
                        1 => 'success',
                        2 => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('harga_beli')
                    ->label('Harga Beli')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('harga_baru')
                    ->label('Harga Baru')
                    ->money('IDR', locale: 'id')
                    ->placeholder('-')
                    ->color('warning')
                    ->weight('bold'),

                /**
                 * PENYELARASAN: Menampilkan Siapa yang Mengusulkan (Update)
                 */
                TextColumn::make('updated_by')
                    ->label('Diperbarui Oleh')
                    ->placeholder('-'),

                /**
                 * PENYELARASAN: Menampilkan Siapa yang Menyetujui/Menolak
                 */
                TextColumn::make('approved_by')
                    ->label('Disetujui/Ditolak Oleh')
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (Model $record) {
                        if ($record->harga_baru !== null && $record->harga_baru > 0) {
                            return 'pending';
                        }
                        return $record->status ?? 'initial';
                    })
                    ->formatStateUsing(function ($state, Model $record) {
                        $date = $record->updated_at?->format('d/m/Y H:i');

                        return match ($state) {
                            'pending'   => 'Menunggu Persetujuan',
                            'disetujui' => "Disetujui - {$date}",
                            'ditolak'   => "Ditolak - {$date}",
                            'initial'   => 'Aktif (Data Lama)',
                            default     => '-',
                        };
                    })
                    ->color(fn($state) => match ($state) {
                        'pending'   => 'warning',
                        'disetujui' => 'success',
                        'ditolak'   => 'danger',
                        default     => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('grade')
                    ->label('Pilih Grade')
                    ->options([
                        1 => 'Grade A',
                        2 => 'Grade B',
                    ]),
            ])
            ->recordActions([
                /**
                 * ACTION: SETUJUI (APPROVE)
                 */
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    /**
                     * [PENYESUAIAN LOGIKA OTORISASI]
                     * Tombol muncul jika ada harga baru DAN (User adalah Admin ATAU User bukan pengusul harga)
                     */
                    ->visible(function (Model $record) {
                        $user = Auth::user();
                        if (!$user) return false;

                        $isAdmin = $user->hasAnyRole(self::ROLE_ADMIN);
                        $isBukanPengusul = $user->name !== $record->updated_by;

                        return $record->harga_baru > 0 && ($isAdmin || $isBukanPengusul);
                    })
                    ->action(function (Model $record) {
                        $hargaLama = $record->harga_beli;
                        $hargaBaru = $record->harga_baru;

                        // 1. BUAT BARIS BARU DI LOG (Historical Record)
                        HargaKayuLog::create([
                            'id_harga_kayu' => $record->id,
                            'harga_lama'    => $hargaLama,
                            'harga_baru'    => $hargaBaru,
                            'petugas'       => Auth::user()->name,
                            'aksi'          => 'Persetujuan Harga',
                        ]);

                        // 2. UPDATE TABEL MASTER
                        $record->update([
                            'harga_beli'  => $hargaBaru,
                            'harga_baru'  => null,
                            'status'      => 'disetujui',
                            'approved_by' => Auth::user()->name,
                        ]);

                        Notification::make()->title('Harga disetujui & riwayat dicatat')->success()->send();
                    }),

                /**
                 * ACTION: TOLAK
                 */
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    /**
                     * [PENYESUAIAN LOGIKA OTORISASI]
                     * Logika sama dengan approve: Admin bebas, User lain dilarang menolak ajuan sendiri.
                     */
                    ->visible(function (Model $record) {
                        $user = Auth::user();
                        if (!$user) return false;

                        $isAdmin = $user->hasAnyRole(self::ROLE_ADMIN);
                        $isBukanPengusul = $user->name !== $record->updated_by;

                        return $record->harga_baru > 0 && ($isAdmin || $isBukanPengusul);
                    })
                    ->action(function (Model $record) {
                        HargaKayuLog::create([
                            'id_harga_kayu' => $record->id,
                            'harga_lama'    => $record->harga_beli,
                            'harga_baru'    => $record->harga_baru,
                            'petugas'       => Auth::user()->name,
                            'aksi'          => 'Penolakan Harga',
                        ]);

                        $record->update([
                            'harga_baru'  => null,
                            'status'      => 'ditolak',
                            'approved_by' => Auth::user()->name,
                        ]);

                        Notification::make()->title('Pengajuan Harga Ditolak')->danger()->send();
                    }),

                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
