<?php

namespace App\Filament\Resources\DetailTurusanKayus\Tables;

use App\Models\DetailTurusanKayu;
use App\Models\HargaKayu;
use App\Models\JenisKayu;
use App\Models\Lahan;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth; // Tambahkan ini

class DetailTurusanKayusTable
{
    /**
     * Role yang memiliki hak akses bypass LOCK
     */
    private const ROLE_ADMIN = ['super_admin', 'Super Admin'];

    public static function configure(Table $table, $livewire = null): Table
    {
        // 1. LOGIKA LOCK: Cek status Nota melalui Owner Record (KayuMasuk)
        $isLocked = false;
        if ($livewire && method_exists($livewire, 'getOwnerRecord')) {
            $ownerRecord = $livewire->getOwnerRecord();
            $isLocked = $ownerRecord &&
                $ownerRecord->notaKayu &&
                $ownerRecord->notaKayu->status !== 'Belum Diperiksa';
        }

        // 2. LOGIKA ADMIN: Cek apakah user yang login adalah admin
        $isAdmin = Auth::user()?->hasAnyRole(self::ROLE_ADMIN) ?? false;

        /**
         * 3. LOGIKA IZIN AKSI:
         * Tombol muncul jika (TIDAK TERKUNCI) ATAU (USER ADALAH ADMIN)
         */
        $canPerformAction = ! $isLocked || $isAdmin;

        $ownerRecord = null;
        if ($livewire && method_exists($livewire, 'getOwnerRecord')) {
            $ownerRecord = $livewire->getOwnerRecord();
        }

        $findHargaKayu = function (int $jenisKayuId, int $panjang, $grade, int $diameter) {
            return HargaKayu::where('id_jenis_kayu', $jenisKayuId)
                ->where('panjang', $panjang)
                ->where('grade', $grade)
                ->where('diameter_terkecil', '<=', $diameter)
                ->where('diameter_terbesar', '>=', $diameter)
                ->first();
        };

        return $table
            ->columns([
                TextColumn::make('nomer_urut')
                    ->label('NO')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('keterangan_kayu')
                    ->label('Kayu')
                    ->getStateUsing(function ($record) {
                        $namaKayu = $record->jenisKayu?->nama_kayu ?? '-';
                        $panjang = $record->panjang ?? '-';
                        $clean = trim(strtoupper((string) $record->grade));
                        $gradeInt = is_numeric($clean) ? intval($clean) : $clean;
                        $grade = match ($gradeInt) {
                            1, '1', 'A' => 'A',
                            2, '2', 'B' => 'B',
                            default => '-',
                        };

                        return "{$namaKayu} {$panjang} ({$grade})";
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->where('panjang', 'like', "%{$search}%")
                            ->orWhere('grade', 'like', "%{$search}%")
                            ->orWhereHas('jenisKayu', fn($q) => $q->where('nama_kayu', 'like', "%{$search}%"));
                    }),

                TextColumn::make('diameter')
                    ->label('D')
                    ->numeric()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('harga')
                    ->label('Harga')
                    ->numeric()
                    ->sortable()
                    ->alignRight()
                    ->color('primary')
                    ->weight('bold')
                    ->formatStateUsing(fn($state) => $state > 0 ? number_format($state, 0, ',', '.') : '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ✅ DIUBAH: tidak lagi hitung manual, mengikuti pola NotaKayuController
                // yang membaca langsung dari $item->kubikasi (accessor model).
                // Ini memastikan nilai kubikasi di Table SELALU sama dengan yang
                // dipakai/ditampilkan di Nota Kayu, karena sumbernya satu (model accessor).
                TextColumn::make('kubikasi')
                    ->label('Kubikasi')
                    ->state(fn($record) => $record->kubikasi)
                    ->numeric(decimalPlaces: 6)
                    ->suffix(' m³')
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('createdBy.name')
                    ->label('Dibuat Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nomer_urut', 'desc')
            ->groups([
                Group::make('lahan.kode_lahan')
                    ->label('Lahan')
                    ->collapsible()
                    ->getTitleFromRecordUsing(function ($record, $records = null) {
                        $kode = $record->lahan?->kode_lahan ?? '-';
                        $nama = $record->lahan?->nama_lahan ?? '-';
                        $jenis_kayu = $record->jenisKayu?->nama_kayu ?? '-';

                        // ✅ DIUBAH: kubikasi diambil dari accessor model ($r->kubikasi)
                        // bukan dihitung ulang manual, agar konsisten dengan Nota Kayu.
                        if ($records instanceof Collection && $records->isNotEmpty()) {
                            $totalBatang   = $records->count();
                            $totalKubikasi = $records->sum(fn($r) => $r->kubikasi);        // ← round() dihapus
                        } else {
                            $parentId = $record->id_kayu_masuk ?? $record->kayu_masuk_id;
                            $query = DetailTurusanKayu::where('id_kayu_masuk', $parentId)
                                ->where('lahan_id', $record->lahan_id)
                                ->get();
                            $totalBatang   = $query->count();
                            $totalKubikasi = $query->sum(fn($r) => $r->kubikasi);          // ← round() dihapus
                        }
                        return "{$kode} {$nama} {$jenis_kayu} - {$totalBatang} batang (" .
                            number_format($totalKubikasi, 4, ',', '.') . ' m³)';
                    }),
            ])
            ->defaultGroup('lahan.kode_lahan')
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Kayu')
                    ->createAnother(true)
                    /**
                     * Gunakan variable $canPerformAction untuk bypass Lock jika admin
                     */
                    ->visible($canPerformAction)
                    ->after(function ($record) {
                        Notification::make()
                            ->title("Batang D: {$record->diameter} cm | No {$record->nomer_urut} ditambahkan")
                            ->success()->send();
                    }),

                Action::make('offlineInput')
                    ->label('Mode Offline')
                    ->icon('heroicon-m-signal-slash')
                    ->color('warning')
                    ->visible($canPerformAction)
                    ->modalHeading('Input Turusan (Tanpa Sinyal)')
                    ->modalWidth('2xl')
                    ->modalContent(fn() => view('filament.components.offline-turusan-modal', [
                        'parentId' => $ownerRecord?->id,
                        'optionsLahan' => Lahan::get()->mapWithKeys(fn($l) => [$l->id => "{$l->kode_lahan} - {$l->nama_lahan}"]),
                        'optionsJenis' => JenisKayu::get()->mapWithKeys(fn($j) => [$j->id => "{$j->kode_kayu} - {$j->nama_kayu}"]),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),

                Action::make('sinkron_semua_harga')
                    ->label('Sinkron Semua Harga')
                    ->icon('heroicon-m-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sinkronisasi Semua Harga')
                    ->modalDescription('Sistem akan memperbarui harga SEMUA batang kayu pada data ini berdasarkan harga master yang sudah disetujui.')
                    ->visible($canPerformAction)
                    ->action(function () use ($ownerRecord, $findHargaKayu) {
                        // Jika tidak ada ownerRecord, hentikan
                        if (! $ownerRecord) {
                            Notification::make()
                                ->title('Gagal')
                                ->body('Data induk tidak ditemukan.')
                                ->danger()
                                ->send();

                            return;
                        }

                        /**
                         * Ambil SEMUA detail kayu milik KayuMasuk ini.
                         * Kita eager load jenisKayu supaya tidak N+1 query
                         * (bayangkan 500 batang kayu → 500 query ke DB, sangat lambat!).
                         * Dengan with('jenisKayu'), cukup 2 query saja.
                         */
                        $allRecords = DetailTurusanKayu::where('id_kayu_masuk', $ownerRecord->id)
                            ->with('jenisKayu')
                            ->get();

                        $berhasil = 0;
                        $gagal = 0;
                        $tidakAda = [];

                        foreach ($allRecords as $record) {
                            $hargaMaster = $findHargaKayu(
                                (int) $record->jenis_kayu_id,
                                (int) $record->panjang,
                                $record->grade,
                                (int) $record->diameter,
                            );

                            if ($hargaMaster) {
                                $record->update(['harga' => $hargaMaster->harga_beli]);
                                $berhasil++;
                            } else {
                                $namaKayu = $record->jenisKayu?->nama_kayu ?? "ID:{$record->jenis_kayu_id}";
                                $tidakAda[] = "No.{$record->nomer_urut} {$namaKayu} D:{$record->diameter}";
                                $gagal++;
                            }
                        }

                        // Laporan hasil
                        if ($berhasil > 0) {
                            Notification::make()
                                ->title("Sinkronisasi Selesai: {$berhasil} batang diperbarui")
                                ->body(
                                    $gagal > 0
                                        ? "{$gagal} tidak ditemukan harganya: " . implode(', ', array_slice($tidakAda, 0, 5)) . ($gagal > 5 ? '...' : '')
                                        : 'Semua harga berhasil diperbarui dari master.'
                                )
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Tidak Ada Harga yang Diperbarui')
                                ->body('Tidak ada harga master yang cocok dan sudah disetujui.')
                                ->warning()
                                ->send();
                        }
                    }),

            ])
            ->recordActions([
                EditAction::make()->visible($canPerformAction),
                DeleteAction::make()->visible($canPerformAction),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('update_lahan')
                        ->label('Update Lahan')
                        ->icon('heroicon-o-map')
                        ->schema([
                            Select::make('lahan_id')->options(Lahan::pluck('kode_lahan', 'id'))->required(),
                        ])
                        ->action(fn(array $data, Collection $records) => $records->each->update(['lahan_id' => $data['lahan_id']]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('update_panjang')
                        ->label('Update Panjang')
                        ->icon('heroicon-o-arrows-up-down')
                        ->schema([
                            Select::make('panjang')->label('Panjang Baru')->options([130 => '130', 260 => '260'])->required(),
                        ])
                        ->action(fn(array $data, Collection $records) => $records->each->update(['panjang' => $data['panjang']]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('update_jenis_kayu')
                        ->label('Update Jenis Kayu')
                        ->icon('heroicon-o-tag')
                        ->schema([
                            Select::make('jenis_kayu_id')
                                ->label('Jenis Kayu Baru')
                                ->options(JenisKayu::pluck('nama_kayu', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            $records->each->update([
                                'jenis_kayu_id' => $data['jenis_kayu_id'],
                            ]);
                        })
                        ->deselectRecordsAfterCompletion(),
                ])->visible($canPerformAction),
            ]);
    }
}
