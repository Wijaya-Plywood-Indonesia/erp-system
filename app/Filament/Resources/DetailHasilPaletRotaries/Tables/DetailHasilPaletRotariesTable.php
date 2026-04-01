<?php

namespace App\Filament\Resources\DetailHasilPaletRotaries\Tables;

use App\Filament\Resources\ProduksiRotaries\ProduksiRotaryResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetailHasilPaletRotariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('timestamp_laporan')
                    ->label('Waktu Laporan')
                    ->dateTime()
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // <--- Hidden by default

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
                            // Asumsi: Anda ingin mencari berdasarkan kolom dimensi asli 
                            // atau gabungan tebal, lebar, panjang
                            $q->where('tebal', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('panjang', 'like', "%{$search}%");

                            // ATAU jika kolomnya memang bernama 'dimensi' tapi error, 
                            // pastikan ejaannya benar di database.
                        });
                    }),

                TextColumn::make('kw')
                    ->label('KW')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('palet')
                    ->label('Palet')
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
                        DB::table('detail_hasil_palet_rotary_serah_terima_pivot')->insert([
                            'id_detail_hasil_palet_rotary' => $record->id,
                            'diserahkan_oleh'              => Auth::user()->name,
                            'diterima_oleh'                => '-',
                            'tipe'                         => 'rotary',
                            'status'                       => 'Serah Barang',
                            'created_at'                   => now(),
                            'updated_at'                   => now(),
                        ]);
                    })
                    ->successNotificationTitle('Palet berhasil diserahkan'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])
            ]);
    }
}
