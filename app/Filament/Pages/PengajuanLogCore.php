<?php

namespace App\Filament\Pages;

use App\Models\LogLogCore;
use App\Models\PengajuanLogCore as ModelsPengajuanLogCore;
use App\Models\StokLogCore;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Contracts\HasTable;
use UnitEnum;

class PengajuanLogCore extends Page implements HasTable
{
    use HasPageShield, InteractsWithTable;
    protected string $view = 'filament.pages.pengajuan-log-core';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-archive-box';
    protected static string|UnitEnum|null $navigationGroup = 'Pengajuan';
    protected static ?string $navigationLabel = 'Pengajuan Log Core';
    protected static ?string $title = 'Pengajuan Log Core';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make('buatPengajuan')
                ->label('Pengajuan Baru')
                ->model(ModelsPengajuanLogCore::class)
                ->form([
                    Select::make('stok_log_core_id')
                        ->label('Spesifikasi Log Core')
                        ->options(function () {
                            // Format gabungan: "260 - Sengon" berdasarkan relasi stok & jenis kayu
                            return StokLogCore::with('jenisKayu')->get()->mapWithKeys(function ($stok) {
                                $namaKayu = $stok->jenisKayu ? $stok->jenisKayu->nama_kayu : 'Tanpa Jenis';
                                return [$stok->id => "{$stok->panjang} - {$namaKayu} (Stok: {$stok->stok_qty})"];
                            });
                        })
                        ->searchable()
                        ->required()
                        ->extraAttributes(['class' => 'rounded-sm']),

                    TextInput::make('jumlah')
                        ->label('Jumlah')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->extraAttributes(['class' => 'rounded-sm']),

                    Textarea::make('keterangan')
                        ->label('Keterangan / Tujuan Penggunaan')
                        ->required()
                        ->rows(3)
                        ->extraAttributes(['class' => 'rounded-sm']),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    // Otomatis mencatat user yang sedang login sebagai pembuat
                    $data['created_by'] = Auth::id();
                    $data['status'] = 'menunggu';
                    return $data;
                })
                ->successNotificationTitle('Pengajuan berhasil dikirim dan menunggu validasi.'),
        ];
    }

    // 2. TABEL DATA (Table Builder)
    public function table(Table $table): Table
    {
        return $table
            ->query(ModelsPengajuanLogCore::query()->latest())
            ->striped()
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('stokLogCore')
                    ->label('Spesifikasi Log Core')
                    ->formatStateUsing(function ($record) {
                        $panjang = $record->stokLogCore?->panjang ?? '-';
                        $namaKayu = $record->stokLogCore?->jenisKayu?->nama_kayu ?? '-';
                        return "{$panjang} - {$namaKayu}";
                    })
                    ->searchable(['stokLogCore.panjang']),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->tooltip(fn(TextColumn $column): ?string => $column->getState()),

                TextColumn::make('pembuat.name')
                    ->label('Diajukan Oleh'),

                TextColumn::make('validator.name')
                    ->label('Divalidasi Oleh')
                    ->default('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'menunggu',
                        'success' => 'disetujui',
                        'danger' => 'ditolak',
                    ]),
            ])
            ->filters([
                // Tambahkan filter jika diperlukan (misal filter status)
            ])
            ->actions([
                // Aksi Validasi Setuju (Hanya muncul untuk Admin Barang & status masih 'menunggu')
                Action::make('setujui')
                    ->label('Setuju')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan')
                    ->modalDescription('Apakah Anda yakin ingin menyetujui pengajuan ini? Stok barang akan otomatis terpotong.')
                    ->modalSubmitActionLabel('Ya, Setujui')
                    ->visible(
                        fn($record) =>
                        $record->status === 'menunggu' && (
                            Auth::user()->hasRole('super_admin') ||
                            $record->created_by !== Auth::id()
                        )
                    )
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            // 1. Update Status Pengajuan
                            $record->update([
                                'status' => 'disetujui',
                                'validated_by' => Auth::id(),
                                'validated_at' => now(),
                            ]);

                            $stok = $record->stokLogCore;
                            $qtyKeluar = $record->jumlah;

                            $stokQtyBefore = $stok->stok_qty;
                            $nilaiStokBefore = $stok->nilai_stok;

                            $hargaSatuan = $stok->stok_qty > 0 ? ($stok->nilai_stok / $stok->stok_qty) : 0;
                            $nilaiKeluar = $qtyKeluar * $hargaSatuan;

                            $stokQtyAfter = $stokQtyBefore - $qtyKeluar;
                            $nilaiStokAfter = $nilaiStokBefore - $nilaiKeluar;

                            // 2. Kurangi Stok pada Tabel StokLogCore
                            $stok->update([
                                'stok_qty' => $stokQtyAfter,
                                'nilai_stok' => $nilaiStokAfter,
                            ]);

                            // 3. Catat Mutasi ke LogLogCore
                            $logBaru = LogLogCore::create([
                                'id_jenis_kayu' => $stok->id_jenis_kayu,
                                'panjang' => $stok->panjang,
                                'tanggal' => now(),
                                'tipe_transaksi' => 'keluar_pengajuan',
                                'keterangan' => 'Pengajuan disetujui: ' . $record->keterangan,
                                'referensi_type' => PengajuanLogCore::class,
                                'referensi_id' => $record->id,
                                'qty' => $qtyKeluar,
                                'harga_satuan' => $hargaSatuan,
                                'nilai' => $nilaiKeluar,
                                'stok_qty_before' => $stokQtyBefore,
                                'nilai_stok_before' => $nilaiStokBefore,
                                'stok_qty_after' => $stokQtyAfter,
                                'nilai_stok_after' => $nilaiStokAfter,
                                'id_validator' => Auth::id(),
                                'tanggal_validasi' => now(),
                            ]);

                            $stok->update(['id_last_log' => $logBaru->id]);
                        });
                    })
                    ->successNotificationTitle('Pengajuan berhasil disetujui dan stok dipotong.'),

                // Aksi Tolak (Hanya muncul untuk Admin Barang & status masih 'menunggu')
                Action::make('tolak')
                    ->label('Tolak')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Penolakan')
                    ->modalDescription('Apakah Anda yakin ingin menolak pengajuan ini?')
                    ->modalSubmitActionLabel('Ya, Tolak')
                    ->visible(
                        fn($record) =>
                        $record->status === 'menunggu' && (
                            Auth::user()->hasRole('super_admin') ||
                            $record->created_by !== Auth::id()
                        )
                    )
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'ditolak',
                            'validated_by' => Auth::id(),
                            'validated_at' => now(),
                        ]);
                    })
                    ->successNotificationTitle('Pengajuan telah ditolak.'),
            ])
            ->bulkActions([
                // Aksi Hapus Massal (Hanya muncul untuk Super Admin)
                DeleteBulkAction::make('hapusMassal')
                    ->label('Hapus Terpilih')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->visible(fn() => Auth::user()->hasRole('super_admin'))
                    ->successNotificationTitle('Data pengajuan yang dipilih berhasil dihapus.'),
            ]);
    }
}
