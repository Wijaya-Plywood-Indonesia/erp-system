<?php

namespace App\Filament\Resources\DetailHasilPaletRotaries\Tables;

use App\Filament\Resources\ProduksiRotaries\ProduksiRotaryResource;
use App\Services\Akuntansi\RotaryJurnalService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetailHasilPaletRotariesTable
{

    private const ADMIN_ROLES = ['admin', 'super_admin', 'Super Admin'];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('timestamp_laporan')
                    ->label('Waktu Laporan')
                    ->dateTime()
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('lahan_display')
                    ->label('Lahan')
                    ->getStateUsing(
                        fn($record) =>
                        $record->penggunaanLahan?->lahan
                            ? "{$record->penggunaanLahan->lahan->kode_lahan} - {$record->penggunaanLahan->lahan->nama_lahan}"
                            : '-'
                    )
                    ->sortable(query: function ($query, string $direction) {
                        $query->join('penggunaan_lahan_rotaries', 'detail_hasil_palet_rotaries.id_penggunaan_lahan', '=', 'penggunaan_lahan_rotaries.id')
                            ->join('lahans', 'penggunaan_lahan_rotaries.id_lahan', '=', 'lahans.id')
                            ->orderBy('lahans.kode_lahan', $direction)
                            ->select('detail_hasil_palet_rotaries.*');
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('penggunaanLahan.lahan', function ($q) use ($search) {
                            $q->where('kode_lahan', 'like', "%{$search}%")
                                ->orWhere('nama_lahan', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('setoranPaletUkuran.dimensi')
                    ->label('Ukuran')
                    ->sortable()
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('setoranPaletUkuran', function ($q) use ($search) {
                            $q->where('tebal', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('panjang', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('kw')
                    ->label('KW')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('palet')
                    ->label('Palet')
                    ->getStateUsing(fn($record) => $record->kode_palet)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('total_lembar')
                    ->label('Total Lembar')
                    ->numeric()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('serah')
                    ->label('Serah')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->tooltip('Serahkan palet ini')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Palet?')
                    ->modalDescription(fn($record) => "Palet {$record->palet} ({$record->total_lembar} lembar) akan diserahkan atas nama " . Auth::user()->name . ".")
                    ->modalSubmitActionLabel('Ya, Serahkan')
                    ->visible(
                        fn($record) => !DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('id_detail_hasil_palet_rotary', $record->id)
                            ->where('tipe', 'rotary')
                            ->exists()
                    )
                    ->action(function ($record) {
                        // 1. Catat serah terima
                        DB::table('detail_hasil_palet_rotary_serah_terima_pivot')->insert([
                            'id_detail_hasil_palet_rotary' => $record->id,
                            'diserahkan_oleh'              => Auth::user()->name,
                            'diterima_oleh'                => '-',
                            'tipe'                         => 'rotary',
                            'status'                       => 'Serah Barang',
                            'created_at'                   => now(),
                            'updated_at'                   => now(),
                        ]);


                        // 2. Tambah stok veneer basah (HPP = 0, diisi saat validasi)
                        $record->loadMissing(['ukuran', 'penggunaanLahan.lahan', 'produksi']);
                        app(RotaryJurnalService::class)->serahPalet($record);

                        Notification::make()
                            ->title('Palet berhasil diserahkan')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    /**
                     * LOGIKA VALIDASI EDIT:
                     * 1. Admin/Super Admin selalu bisa edit.
                     * 2. User biasa bisa edit jika: Data belum diserahkan ATAU statusnya masih 'Serah Barang'.
                     * 3. User biasa TIDAK BISA edit jika status sudah 'Terima Barang'.
                     */
                    ->visible(function ($record) {
                        $user = Auth::user();
                        $isAdmin = $user && $user->hasAnyRole(self::ADMIN_ROLES);

                        // Admin memiliki akses bypass, selalu bisa edit
                        if ($isAdmin) return true;

                        // Ambil status terbaru dari tabel pivot
                        $statusPivot = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('id_detail_hasil_palet_rotary', $record->id)
                            ->value('status');

                        // Jika status adalah 'Terima Barang', maka kunci akses (return false)
                        if ($statusPivot === 'Terima Barang') {
                            return false;
                        }

                        // Selain itu (Belum Serah atau baru Serah Barang), masih boleh edit
                        return true;
                    }),

                DeleteAction::make()
                    /**
                     * LOGIKA VALIDASI DELETE:
                     * Sama dengan edit, data dikunci jika sudah divalidasi/diterima oleh logistik.
                     */
                    ->visible(function ($record) {
                        $user = Auth::user();
                        $isAdmin = $user && $user->hasAnyRole(self::ADMIN_ROLES);

                        if ($isAdmin) return true;

                        $statusPivot = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('id_detail_hasil_palet_rotary', $record->id)
                            ->value('status');

                        // Kunci jika barang sudah benar-benar diterima pihak tujuan
                        if ($statusPivot === 'Terima Barang') {
                            return false;
                        }

                        return true;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn() => Auth::user()->hasAnyRole(self::ADMIN_ROLES)),
                ])
            ]);
    }
}
