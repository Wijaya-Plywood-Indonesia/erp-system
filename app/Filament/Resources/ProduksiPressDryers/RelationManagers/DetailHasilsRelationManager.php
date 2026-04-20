<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Models\DetailHasil;
use App\Models\StokVeneerKering;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DetailHasilsRelationManager extends RelationManager
{
    protected static ?string $title = 'Hasil';
    protected static string $relationship = 'detailHasils';

    // FUNGSI BARU UNTUK MEMUNCULKAN TOMBOL DI HALAMAN VIEW
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),

                // Relasi ke Ukuran (id_ukuran)
                Select::make('id_ukuran')
                    ->label('Ukuran Kayu')
                    ->options(function () {
                        $produksi = $this->getOwnerRecord();

                        return \App\Models\DetailMasuk::where('id_produksi_dryer', $produksi->id)
                            ->with('ukuran')
                            ->get()
                            ->pluck('ukuran.nama_ukuran', 'id_ukuran')
                            ->unique();
                    })
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_ukuran' => $state]);
                    })
                    ->default(fn() => session('last_ukuran'))
                    ->required(),

                // Relasi ke Jenis Kayu (id_jenis_kayu)
                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(function () {
                        $produksi = $this->getOwnerRecord();

                        return \App\Models\DetailMasuk::where('id_produksi_dryer', $produksi->id)
                            ->select('id_jenis_kayu')
                            ->distinct()
                            ->with('jenisKayu:id,nama_kayu')
                            ->get()
                            ->pluck('jenisKayu.nama_kayu', 'id_jenis_kayu');
                    })
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_jenis_kayu' => $state]);
                    })
                    ->default(fn() => session('last_jenis_kayu'))
                    ->required(),

                TextInput::make('kw')
                    ->label('Kualitas (KW)')

                    ->required()
                    ->placeholder('Cth: 1, 2, 3 dll.'),

                TextInput::make('isi')
                    ->label('Isi')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    /**
                     * Warna badge berdasarkan stokMasuk —
                     * hijau kalau sudah diserahkan, abu kalau belum
                     */
                    ->color(fn($record) => $record->stokMasuk ? 'success' : 'gray')
                    ->description(fn($record) => $record->stokMasuk ? 'Sudah Serah' : 'Belum Serah'),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),

                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('ukuran', function (Builder $q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('isi')
                    ->label('Isi'),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                /**
                 * TOMBOL SERAH KE GUDANG
                 * Muncul di halaman View karena isReadOnly() = false
                 * Hanya tampil kalau stokMasuk masih null (belum diserahkan)
                 */
                Action::make('serahKeGudang')
                    ->label('Serahkan Hasil')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Palet ke Gudang Kering?')
                    ->modalDescription('Setelah diserahkan, data ini akan masuk ke stok gudang dan tombol serah akan hilang.')
                    ->modalSubmitActionLabel('Ya, Serahkan Sekarang')
                    // Hanya muncul kalau belum ada di stok (belum diserahkan)
                    ->visible(fn($record) => is_null($record->stokMasuk))
                    ->action(function (DetailHasil $record) {
                        try {
                            DB::transaction(function () use ($record) {
                                $ukuran = $record->ukuran;

                                if (!$ukuran) {
                                    throw new \Exception("Gagal: Dimensi ukuran palet tidak ditemukan.");
                                }

                                // 1. Hitung Kubikasi (m3)
                                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $record->isi) / 1000000;

                                // 2. Ambil Snapshot Saldo Terakhir
                                $lastSnapshot = StokVeneerKering::snapshotTerakhir(
                                    $record->id_ukuran,
                                    $record->id_jenis_kayu,
                                    $record->kw
                                );

                                // 3. Masukkan ke Stok Gudang Kering
                                StokVeneerKering::create([
                                    'id_detail_hasil_dryer' => $record->id,
                                    'id_ukuran'             => $record->id_ukuran,
                                    'id_jenis_kayu'         => $record->id_jenis_kayu,
                                    'kw'                    => $record->kw,
                                    'jenis_transaksi'       => 'masuk',
                                    'tanggal_transaksi'     => now(),
                                    'qty'                   => $record->isi,
                                    'm3'                    => round($m3, 4),
                                    'stok_m3_sebelum'       => $lastSnapshot['stok_m3'] ?? 0,
                                    'stok_m3_sesudah'       => ($lastSnapshot['stok_m3'] ?? 0) + $m3,
                                    'keterangan'            => "MASUK DARI DRYER: No. Palet {$record->no_palet}",
                                ]);

                                // 4. Setelah StokVeneerKering::create() berhasil,
                                //    stokMasuk tidak lagi null → tombol otomatis hilang
                            });

                            Notification::make()
                                ->title('Penyerahan Berhasil')
                                ->body("Palet {$record->no_palet} telah dipindahkan ke stok gudang.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Terjadi Kesalahan Sistem')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
