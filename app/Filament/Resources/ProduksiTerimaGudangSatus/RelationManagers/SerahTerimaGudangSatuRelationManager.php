<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers;

use App\Models\ProduksiNyusup;
use App\Models\SerahTerimaGudangSatu;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerahTerimaGudangSatuRelationManager extends RelationManager
{
    protected static string $relationship = 'serahTerima';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return $ownerRecord instanceof ProduksiNyusup
            ? 'Terima Barang untuk Nyusup'
            : 'Terima Barang dari Pilih Plywood';
    }

    /**
     * Menentukan apakah relation manager ini sedang dipakai di konteks Nyusup.
     */
    protected function isNyusupContext(): bool
    {
        return $this->getOwnerRecord() instanceof ProduksiNyusup;
    }

    /**
     * Nama kolom FK yang dipakai untuk "lengket"-kan record ke owner saat ini,
     * tergantung konteks resource mana yang memanggil relation manager ini.
     */
    protected function ownerForeignKey(): string
    {
        return $this->isNyusupContext()
            ? 'id_produksi_nyusup'
            : 'id_produksi_terima_gudang_satu';
    }

    /**
     * Ambil data ringkas dari record untuk ditampilkan di preview modal terima.
     */
    protected function getPreviewData($record): array
    {
        $hasil = $record->hasilPilihPlywood;
        $bsj = $record->barang_setengah_jadi;

        return [
            'jenis_barang' => $bsj?->jenisBarang?->nama_jenis_barang ?? '-',
            'grade' => $bsj?->grade?->nama_grade ?? '-',
            'ukuran' => $bsj?->ukuran?->nama_ukuran ?? '-',
            'kondisi' => $hasil?->kondisi ?? '-',
            'jenis_cacat' => $hasil?->jenis_cacat ?? '-',
            'jumlah' => $record->jumlah ?? '-',
            'dari_produksi' => $hasil?->produksiPilihPlywood?->tanggal_produksi ?? '-',
        ];
    }

    public function table(Table $table): Table
    {
        $ownerId = $this->getOwnerRecord()->id;
        $isNyusup = $this->isNyusupContext();
        $foreignKey = $this->ownerForeignKey();

        return $table
            ->modifyQueryUsing(function ($query) use ($ownerId, $isNyusup, $foreignKey) {
                // Reset constraint bawaan dari relasi dasar (WHERE id_produksi_terima_gudang_satu = ownerId),
                // supaya kondisi "masih menunggu" (diterima_oleh = '-') tidak ikut ke-AND-kan
                // dan bisa muncul walau kolom FK terkait masih NULL.
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                $query->with([
                    'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                    'hasilPilihPlywood.barangSetengahJadiHp.grade',
                    'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                    'hasilPilihPlywood.produksiPilihPlywood',
                ]);

                // Filter tujuan: kalau dipanggil dari ProduksiNyusup, hanya tampilkan
                // yang tujuan = 'nyusup'. Kalau dari ProduksiTerimaGudangSatu,
                // hanya tampilkan yang tujuan != 'nyusup' (mis. gudang_satu / null).
                $query->when(
                    $isNyusup,
                    fn ($q) => $q->where('tujuan', 'nyusup'),
                    fn ($q) => $q->where(function ($sub) {
                        $sub->whereNull('tujuan')->orWhere('tujuan', '!=', 'nyusup');
                    })
                );

                // Tampilkan yang masih menunggu (belum diterima siapapun, bisa diterima produksi manapun)
                // ATAU yang sudah diterima dan memang lengket ke produksi ini.
                return $query->where(function ($mainQuery) use ($ownerId, $foreignKey) {
                    $mainQuery->where('diterima_oleh', '-')
                        ->orWhere($foreignKey, $ownerId);
                })
                    ->orderBy('diterima_oleh', 'asc')
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('jenis_barang')
                    ->label('Jenis Barang')
                    ->state(fn ($record) => $record->barang_setengah_jadi?->jenisBarang?->nama_jenis_barang ?? '-'),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(fn ($record) => $record->barang_setengah_jadi?->grade?->nama_grade ?? '-'),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(fn ($record) => $record->barang_setengah_jadi?->ukuran?->nama_ukuran ?? '-'),

                TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->state(fn ($record) => $record->hasilPilihPlywood?->kondisi ?? '-')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('jumlah')
                    ->label('Jumlah Bagus')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('diterima_oleh')
                    ->label('Diterima Oleh')
                    ->badge()
                    ->color(fn ($state) => $state === '-' ? 'gray' : 'success')
                    ->formatStateUsing(fn ($state) => $state === '-' ? 'Menunggu' : $state),

                TextColumn::make('status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn ($state) => match ($state) {
                        'Diterima' => 'success',
                        'Menunggu' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->headerActions([
                // Tidak ada CreateAction — barang masuk otomatis dari sisi Pilih Plywood / Hasil Terima Gudang Satu.
            ])
            ->actions([
                Action::make('terima')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Terima barang ini?')
                    ->modalDescription('Periksa data berikut sebelum menerima.')
                    ->schema(function ($record) {
                        $preview = $this->getPreviewData($record);

                        return [
                            Grid::make(2)
                                ->schema([
                                    Placeholder::make('preview_jenis_barang')
                                        ->label('Jenis Barang')
                                        ->content($preview['jenis_barang']),

                                    Placeholder::make('preview_grade')
                                        ->label('Grade')
                                        ->content($preview['grade']),

                                    Placeholder::make('preview_ukuran')
                                        ->label('Ukuran')
                                        ->content($preview['ukuran']),

                                    Placeholder::make('preview_kondisi')
                                        ->label('Kondisi')
                                        ->content($preview['kondisi']),

                                    Placeholder::make('preview_jenis_cacat')
                                        ->label('Jenis Cacat')
                                        ->content($preview['jenis_cacat']),

                                    Placeholder::make('preview_jumlah')
                                        ->label('Jumlah Bagus')
                                        ->content($preview['jumlah']),

                                    Placeholder::make('preview_dari_produksi')
                                        ->label('Tgl Produksi Asal')
                                        ->content($preview['dari_produksi']),
                                ]),
                        ];
                    })
                    // Hanya muncul kalau memang masih menunggu (belum lengket ke produksi manapun).
                    ->visible(fn ($record) => $record?->diterima_oleh === '-')
                    ->action(function ($record) use ($ownerId, $foreignKey) {
                        try {
                            DB::transaction(function () use ($record, $ownerId, $foreignKey) {
                                // Lock + re-check: mencegah 2 produksi menerima barang yang sama
                                // secara bersamaan (race condition).
                                $fresh = SerahTerimaGudangSatu::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Barang ini sudah diambil produksi lain.');
                                }

                                // Begitu diterima, record ini lengket permanen ke produksi ini
                                // (kolom FK yang dipakai tergantung konteks: nyusup atau gudang satu).
                                $fresh->update([
                                    'diterima_oleh' => Auth::user()->name.' - Gudang Satu',
                                    $foreignKey => $ownerId,
                                    'status' => 'Diterima',
                                ]);

                                // Jika ada pencatatan stok/jurnal saat barang diterima,
                                // panggil service di sini, mengikuti pola StokTriplekMthService/StokPlatformMthService.
                            });

                            Notification::make()
                                ->title('Barang Berhasil Diterima')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih')
                        ->visible(fn () => Auth::user()->hasAnyRole(['super-admin', 'admin'])),
                ]),
            ]);
    }
}
