<?php



namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Models\PlatformJadiMutasiKeluar;
use App\Models\SerahTerimaMasukHp;
use App\Models\VeneerJadiMutasiKeluar;
use App\Models\VeneerJadiMutasiKeluarPalet;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MutasiMasukRelationManager extends RelationManager
{
    protected static string $relationship = 'mutasiMasuk';
    protected static ?string $title = 'Serah Terima';

    protected function getTableQuery(): Builder
    {
        return SerahTerimaMasukHp::query()
            ->orderByRaw('CASE WHEN diterima_by IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('tanggal_keluar');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->recordTitleAttribute('kw_grade')
            ->columns([
                TextColumn::make('tanggal_keluar')
                    ->label('Tanggal Masuk')
                    ->dateTime('d/m/Y H:i')
                    ->color('gray'),
                TextColumn::make('sumber')
                    ->label('Sumber')
                    ->badge()
                    ->color(fn(string $state) => $state === 'veneer' ? 'info' : 'purple')
                    ->formatStateUsing(fn(string $state) => $state === 'veneer' ? 'Veneer Jadi' : 'Platform Jadi')
                    ->searchable(),
                TextColumn::make('jenis_nama')
                    ->label('Jenis Barang')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->getStateUsing(fn($record) => ((float) $record->panjang + 0) . 'x' . ((float) $record->lebar + 0) . 'x' . ((float) $record->tebal + 0))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('kw_grade')
                    ->label('KW')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->searchable(),
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
                    ->getStateUsing(fn(SerahTerimaMasukHp $record) => number_format($record->kubikasi(), 4))
                    ->alignRight(),
                TextColumn::make('operator.name')
                    ->label('Penyerah')
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('penerima.name')
                    ->label('Penerima')
                    ->color('gray')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->wrap()
                    ->color('gray')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('sumber')
                    ->label('Sumber Material')
                    ->options([
                        'veneer'        => 'Veneer Jadi',
                        'platform_jadi' => 'Platform Jadi',
                    ])
                    ->query(function (Builder $query, array $data) {
                        logger('FILTER DATA', $data);
                        if (! empty($data['value'])) {
                            $query->where('sumber', $data['value']);
                        }
                    }),
            ])
            ->actions([
                Action::make('terima_material')
                    ->label('TERIMA')
                    ->button()
                    ->color('warning')
                    ->visible(fn(SerahTerimaMasukHp $record) => is_null($record->diterima_by))
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Penerimaan Material')
                    ->modalDescription('Apakah Anda yakin barang sudah dihitung fisik dan sesuai dengan dokumen? Tindakan ini akan langsung mendaftarkan palet ke antrean produksi Hotpress.')
                    ->action(function (SerahTerimaMasukHp $record) {
                        logger('TERIMA CLICKED', ['id' => $record->id, 'sumber' => $record->sumber]);
                        $produksiHpId = $this->getOwnerRecord()->id;

                        if ($record->sumber === 'veneer') {
                            $palet = VeneerJadiMutasiKeluarPalet::findOrFail($record->id_asli);
                            $mk    = VeneerJadiMutasiKeluar::findOrFail($record->id_mutasi_keluar);

                            $kubikasiAsli = ($mk->panjang * $mk->lebar * $mk->tebal * $palet->jumlah_lembar) / 10000000;

                            $palet->update([
                                'diterima_by'   => Auth::id(),
                                'diterima_at'   => now(),
                                'tebal'         => $mk->tebal,
                                'stok_kubikasi' => $kubikasiAsli,
                            ]);
                            $mk->update([
                                'diterima_by'    => Auth::id(),
                                'diterima_at'    => now(),
                                'id_produksi_hp' => $produksiHpId,
                            ]);
                        } else {
                            $mk = PlatformJadiMutasiKeluar::findOrFail($record->id_mutasi_keluar);

                            $mk->update([
                                'diterima_by'    => Auth::id(),
                                'diterima_at'    => now(),
                                'id_produksi_hp' => $produksiHpId,
                            ]);
                        }

                        Notification::make()->success()->title('Material Berhasil Diterima')->send();
                    }),

                Action::make('done_label')
                    ->label('✓ SELESAI')
                    ->color('success')
                    ->disabled()
                    ->visible(fn(SerahTerimaMasukHp $record) => ! is_null($record->diterima_by)),
            ]);
    }
}
