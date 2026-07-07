<?php



namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Models\VeneerJadiMutasiKeluar;
use App\Models\VeneerJadiMutasiKeluarPalet;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MutasiMasukRelationManager extends RelationManager
{
    protected static string $relationship = 'mutasiMasuk';
    protected static ?string $title = 'Serah Terima';

    protected function modifyQueryUsing(Builder $query): Builder
    {
        return $query
            // 🌟 Menggunakan subquery untuk mengecek status 'diterima_by' di tabel induk mutasiKeluar
            ->orderByRaw('
            (
                SELECT CASE WHEN mk.diterima_by IS NULL THEN 0 ELSE 1 END 
                FROM veneer_jadi_mutasi_keluars mk 
                WHERE mk.id = veneer_jadi_mutasi_keluar_palets.id_mutasi_keluar
                LIMIT 1
            ) ASC
        ');
    }

    public function table(Table $table): Table
    {
        return $table
            // ->defaultSort('id', 'desc')
            ->recordTitleAttribute('kw_grade')
            ->columns([
                TextColumn::make('mutasiKeluar.created_at')
                    ->label('Tanggal Masuk')
                    ->getStateUsing(fn($record) => $record->mutasiKeluar->created_at->format('d/m/Y H:i'))
                    ->color('gray'),
                TextColumn::make('mutasiKeluar.jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->getStateUsing(fn($record) => ((float)$record->mutasiKeluar->panjang + 0) . 'x' . ((float)$record->mutasiKeluar->lebar + 0) . 'x' . ((float)$record->mutasiKeluar->tebal + 0))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('mutasiKeluar', function (Builder $q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('mutasiKeluar.kw_grade')
                    ->label('KW')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),
                TextColumn::make('nomor_palet')
                    ->label('Nomor Palet')
                    ->alignCenter()
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('jumlah_lembar')
                    ->label('Jumlah Lembar')
                    ->formatStateUsing(fn($state) => number_format($state) . ' Lbr')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),
                TextColumn::make('kubikasi')
                    ->label('Kubikasi')
                    ->color('warning')
                    ->getStateUsing(function ($record) {
                        $mk = $record->mutasiKeluar;
                        return number_format(($mk->panjang * $mk->lebar * $mk->tebal * $record->jumlah_lembar) / 10000000, 4);
                    })
                    ->alignRight(),
                TextColumn::make('mutasiKeluar.operator.name')
                    ->label('Penyerah')
                    ->color('gray'),
                TextColumn::make('mutasiKeluar.penerima.name')
                    ->label('Penerima')
                    ->color('gray')
                    ->placeholder('-'),
                TextColumn::make('mutasiKeluar.keterangan')
                    ->label('Keterangan')
                    ->wrap()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('sumber_asal')
                    ->label('Asal Material')
                    ->options([
                        'gudang' => 'Gudang Veneer Jadi',
                        'sanding' => 'Produksi Sanding',
                    ])
                    ->default('gudang')
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'gudang') {
                            $query->whereHas('mutasiKeluar', fn($q) => $q->whereRaw('LOWER(tujuan) LIKE ?', ['%hotpress%']));
                        } elseif ($data['value'] === 'sanding') {
                            $query->whereHas('mutasiKeluar', fn($q) => $q->whereRaw('LOWER(tujuan) LIKE ?', ['%sanding%']));
                        }
                    }),
            ])
            ->actions([
                Action::make('terima_material')
                    ->label('TERIMA')
                    ->button()
                    ->color('warning') // Tombol warna emas/oranye solid
                    ->visible(fn(VeneerJadiMutasiKeluarPalet $record) => is_null($record->mutasiKeluar->diterima_by))
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Penerimaan Material')
                    ->modalDescription('Apakah Anda yakin barang sudah dihitung fisik dan sesuai dengan dokumen dokumen? Tindakan ini akan langsung mendaftarkan palet ke antrean produksi Hotpress.')
                    ->action(function (VeneerJadiMutasiKeluarPalet $record) {
                        $mk = $record->mutasiKeluar;
                        $kubikasiAsli = ($mk->panjang * $mk->lebar * $mk->tebal * $record->jumlah_lembar) / 10000000;
                        $record->update([
                            'diterima_by' => Auth::id(),
                            'diterima_at' => now(),
                            'tebal' => $mk->tebal,
                            'stok_kubikasi' => $kubikasiAsli,
                        ]);
                        $mk->update([
                            'diterima_by' => Auth::id(),
                            'diterima_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Material Berhasil Diterima')
                            ->body('Stok masuk terdaftar pada antrean produksi Hotpress.')
                            ->send();
                    }),

                Action::make('done_label')
                    ->label('✓ SELESAI')
                    ->color('success')
                    ->disabled()
                    ->visible(fn(VeneerJadiMutasiKeluarPalet $record) => ! is_null($record->mutasiKeluar->diterima_by)),
            ]);
    }
}
