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
use Illuminate\Database\Eloquent\Builder; // Tambahkan ini

class HargaKayusTable
{
    private const ROLE_ADMIN = ['admin', 'super_admin', 'Super Admin'];

    public static function configure(Table $table): Table
    {
        return $table
            ->paginated(false)
            /**
             * LOGIKA PENGURUTAN (SORTING) MULTI-KOLOM
             * Urutan: Jenis Kayu (A-Z) -> Panjang -> Diameter Min -> Diameter Max -> Grade
             */
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->join('jenis_kayus', 'harga_kayus.id_jenis_kayu', '=', 'jenis_kayus.id')
                    ->orderBy('harga_kayus.grade', 'asc') // Grade 1 (A) muncul pertama
                    ->orderBy('jenis_kayus.nama_kayu', 'asc')
                    ->orderBy('harga_kayus.panjang', 'asc')
                    ->orderBy('harga_kayus.diameter_terkecil', 'asc')
                    ->orderBy('harga_kayus.diameter_terbesar', 'asc')
                    ->select('harga_kayus.*');
            })
            ->columns([
                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),

                TextColumn::make('panjang')
                    ->label('Panjang')
                    ->badge()
                    /**
                     * PEMBERIAN WARNA BADGE PANJANG:
                     * 130 menggunakan warna abu-abu (gray)
                     * 260 menggunakan warna hijau (success)
                     */
                    ->color(fn($state): string => match ((int) $state) {
                        130 => 'gray',
                        260 => 'success',
                        default => 'info',
                    })
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

                TextColumn::make('updated_by')
                    ->label('Diperbarui Oleh')
                    ->placeholder('-'),

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
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
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

                        HargaKayuLog::create([
                            'id_harga_kayu' => $record->id,
                            'harga_lama'    => $hargaLama,
                            'harga_baru'    => $hargaBaru,
                            'petugas'       => Auth::user()->name,
                            'aksi'          => 'Persetujuan Harga',
                        ]);

                        $record->update([
                            'harga_beli'  => $hargaBaru,
                            'harga_baru'  => null,
                            'status'      => 'disetujui',
                            'approved_by' => Auth::user()->name,
                        ]);

                        Notification::make()->title('Harga disetujui')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
