<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers;

use App\Models\HasilPilihPlywood;
use App\Models\ProduksiDempul;
use App\Models\ProduksiPilihPlywood;
use App\Models\ProduksiTembeltriplek;
use App\Models\SerahTerimaTriplekCacat;
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

class SerahTerimaTriplekCacatRelationManager extends RelationManager
{
    private const ROLE_ADMIN = ['super_admin', 'Super Admin', 'admin_kayu'];

    protected static string $relationship = 'serahTerimaTriplekCacat';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return match (get_class($ownerRecord)) {
            ProduksiPilihPlywood::class => 'Serah Hasil Pilih Plywood (Cacat)',
            ProduksiDempul::class => 'Terima Barang Cacat',
            ProduksiTembeltriplek::class => 'Terima Barang Cacat',
            default => 'Serah Terima Triplek Cacat',
        };
    }

    protected function getTipe(): string
    {
        return match (get_class($this->getOwnerRecord())) {
            ProduksiPilihPlywood::class => 'pilih_plywood',
            ProduksiDempul::class => 'dempul',
            ProduksiTembeltriplek::class => 'tembel_triplek',
            default => 'unknown',
        };
    }

    /**
     * Ambil data ringkas dari record untuk ditampilkan di preview modal terima.
     */
    protected function getPreviewData($record): array
    {
        $hasil = $record->hasilPilihPlywood;
        $bsj = $record->barangSetengahJadi;

        return [
            'jenis_barang' => $bsj?->jenisBarang?->nama_jenis_barang ?? '-',
            'grade' => $bsj?->grade?->nama_grade ?? '-',
            'ukuran' => $bsj?->ukuran?->nama_ukuran ?? '-',
            'jumlah_bagus' => $hasil?->jumlah_bagus ?? '-',
            'kondisi' => $hasil?->kondisi ?? '-',
            'ket' => $hasil?->ket ?? '-',
        ];
    }

    public function table(Table $table): Table
    {
        $tipe = $this->getTipe();
        $ownerId = $this->getOwnerRecord()->id;

        $eagerLoads = [
            'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
            'hasilPilihPlywood.barangSetengahJadiHp.grade',
            'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
        ];

        return $table
            ->modifyQueryUsing(function ($query) use ($tipe, $ownerId, $eagerLoads) {
                // Reset constraint bawaan dari relasi dasar (tanpa mengganti objek $query,
                // karena Filament butuh instance query yang sama untuk proses selanjutnya)
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                $query->with($eagerLoads);

                if ($tipe === 'pilih_plywood') {
                    $hasilIds = HasilPilihPlywood::where('id_produksi_pilih_plywood', $ownerId)->pluck('id');

                    return $query
                        ->whereIn('id_hasil_pilih_plywood', $hasilIds)
                        ->orderBy('created_at', 'desc');
                }

                if ($tipe === 'dempul' || $tipe === 'tembel_triplek') {
                    $kolomProduksi = $tipe === 'dempul' ? 'id_produksi_dempul' : 'id_produksi_tembel_triplek';

                    // Tampilkan barang yang MASIH BELUM DIAMBIL siapapun (race antara Dempul & Tembel Triplek),
                    // ATAU yang sudah diambil oleh produksi ini sendiri (riwayat).
                    return $query
                        ->where(function ($q) use ($ownerId, $kolomProduksi) {
                            $q->where('diterima_oleh', '-')
                                ->orWhere($kolomProduksi, $ownerId);
                        })
                        ->orderBy('diterima_oleh', 'asc')
                        ->orderBy('created_at', 'desc');
                }

                return $query;
            })
            ->columns([
                TextColumn::make('tujuan_label')
                    ->label('Tujuan')
                    ->state(fn ($record) => $record->diterima_oleh === '-' ? 'Belum Ditentukan' : $record->labelTujuan)
                    ->badge()
                    ->color(fn ($record) => match ($record->tujuan) {
                        'dempul' => 'warning',
                        'tembel_triplek' => 'purple',
                        default => 'gray',
                    }),

                TextColumn::make('jenis_barang')
                    ->label('Jenis Barang')
                    ->state(fn ($record) => $record->barangSetengahJadi?->jenisBarang?->nama_jenis_barang ?? '-'),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(fn ($record) => $record->barangSetengahJadi?->grade?->nama_grade ?? '-'),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(fn ($record) => $record->barangSetengahJadi?->ukuran?->nama_ukuran ?? '-'),

                TextColumn::make('jumlah')
                    ->label('Jumlah Lembar')
                    ->state(fn ($record) => $record->jumlah ?? '-')
                    ->alignCenter(),

                TextColumn::make('sisa')
                    ->label('Sisa')
                    ->state(fn ($record) => $record->sisa ?? '-')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                        'Terima Dempul', 'Terima Tembel Triplek' => 'success',
                        'Menunggu Diterima' => 'warning',
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
                    ->modalHeading('Terima barang cacat ini?')
                    ->modalDescription('Barang ini juga tersedia di produksi lain. Begitu Anda terima, barang tidak akan bisa diterima di tempat lain.')
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

                                    Placeholder::make('preview_jumlah_bagus')
                                        ->label('Jumlah Bagus')
                                        ->content($preview['jumlah_bagus']),

                                    Placeholder::make('preview_kondisi')
                                        ->label('Kondisi')
                                        ->content($preview['kondisi']),

                                    Placeholder::make('preview_ket')
                                        ->label('Keterangan')
                                        ->content($preview['ket']),
                                ]),
                        ];
                    })
                    // Muncul untuk SEMUA row yang belum diterima siapapun, di kedua tab (race).
                    // Tidak lagi dibatasi oleh kolom `tujuan` karena tujuan baru ditentukan saat diterima.
                    ->visible(fn ($record) => $record->diterima_oleh === '-')
                    ->action(function ($record) use ($ownerId, $tipe) {
                        try {
                            DB::transaction(function () use ($record, $ownerId, $tipe) {
                                // Lock row: siapa yang sampai transaksi ini duluan, dia yang menang.
                                $fresh = SerahTerimaTriplekCacat::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Barang ini sudah diambil produksi lain.');
                                }

                                if ($tipe === 'dempul') {
                                    $fresh->update([
                                        'tujuan' => 'dempul',
                                        'diterima_oleh' => Auth::user()->name.' - Dempul',
                                        'id_produksi_dempul' => $ownerId,
                                        'status' => 'Terima Dempul',
                                    ]);

                                    return;
                                }

                                if ($tipe === 'tembel_triplek') {
                                    $fresh->update([
                                        'tujuan' => 'tembel_triplek',
                                        'diterima_oleh' => Auth::user()->name.' - Tembel Triplek',
                                        'id_produksi_tembel_triplek' => $ownerId,
                                        'status' => 'Terima Tembel Triplek',
                                    ]);
                                }
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
                        ->visible(fn () => Auth::user()->hasAnyRole(self::ROLE_ADMIN)),
                ]),
            ]);
    }
}
