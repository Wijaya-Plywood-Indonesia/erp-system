<?php

namespace App\Filament\Resources\DetailKayuMasuks\Tables;

use App\Models\DetailKayuMasuk;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth; // Tambahkan ini

class DetailKayuMasuksTable
{
    /**
     * Role yang memiliki hak akses bypass LOCK
     */
    private const ROLE_ADMIN = ['super_admin', 'Super Admin'];

    public static function configure(Table $table, $livewire = null): Table
    {
        // 1. LOGIKA LOCK: Cek status Nota melalui Owner Record
        $isLocked = false;
        if ($livewire && method_exists($livewire, 'getOwnerRecord')) {
            $ownerRecord = $livewire->getOwnerRecord();
            $nota = $ownerRecord->notakayu;
            $isLocked = $nota && $nota->status !== 'Belum Diperiksa';
        }

        // 2. LOGIKA ADMIN: Cek apakah user yang login adalah admin
        $isAdmin = Auth::user()?->hasAnyRole(self::ROLE_ADMIN) ?? false;

        /**
         * 3. LOGIKA IZIN AKSI (BYPASS):
         * Tombol muncul jika (TIDAK TERKUNCI) ATAU (USER ADALAH ADMIN)
         */
        $canPerformAction = ! $isLocked || $isAdmin;

        $ownerRecord = null;
        if ($livewire && method_exists($livewire, 'getOwnerRecord')) {
            $ownerRecord = $livewire->getOwnerRecord();
        }

        return $table
            ->striped()
            ->recordClasses(function ($record) {
                $grade = (int) ($record->grade ?? 0);

                return match ($grade) {
                    1 => 'bg-opacity-5 filament-row-grade-a',
                    2 => 'bg-opacity-5 filament-row-grade-b',
                    default => null,
                };
            })
            ->columns([
                TextColumn::make('lahan.kode_lahan')
                    ->label('Lahan')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Kayu')
                    ->formatStateUsing(function ($state, $record) {
                        $namaKayu = $state ?? '-';
                        $panjang = $record->panjang ?? '-';
                        $raw = trim((string) ($record->grade ?? ''));
                        $rawUpper = strtoupper($raw);
                        $gradeNorm = is_numeric($rawUpper) ? (int) $rawUpper : $rawUpper;

                        $grade = match ($gradeNorm) {
                            1, '1', 'A' => 'A',
                            2, '2', 'B' => 'B',
                            default => '-',
                        };

                        return "{$namaKayu} {$panjang} ({$grade})";
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('jenisKayu', fn ($q) => $q->where('nama_kayu', 'like', "%{$search}%"))
                            ->orWhere('panjang', 'like', "%{$search}%")
                            ->orWhere('grade', 'like', "%{$search}%");
                    })
                    ->sortable(),

                TextColumn::make('diameter')
                    ->label('Diameter')
                    ->numeric()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jumlah_batang')
                    ->label('Batang')
                    ->numeric()
                    ->suffix(' btg')
                    ->searchable()
                    ->sortable(),

                // ✅ DIUBAH: ambil langsung dari accessor model ($record->kubikasi),
                // bukan hitung ulang manual. Ini menyamakan pola dengan
                // DetailTurusanKayusTable, memastikan nilai kubikasi di Table
                // SELALU sama dengan yang dipakai/ditampilkan di Nota Kayu,
                // karena sumbernya satu (model accessor).
                TextColumn::make('kubikasi')
                    ->label('Kubikasi')
                    ->state(fn($record) => $record->kubikasi)
                    ->numeric(decimalPlaces: 6)
                    ->suffix(' m³')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignRight(),

                TextColumn::make('createdBy.name')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('updatedBy.name')
                    ->label('Diubah Oleh')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('lahan.kode_lahan')
                    ->label('Lahan')
                    ->collapsible()
                    ->orderQueryUsing(function ($query, $direction) {
                        return $query
                            ->join('lahans', 'detail_kayu_masuks.id_lahan', '=', 'lahans.id')
                            ->orderBy('lahans.kode_lahan', $direction)
                            ->select('detail_kayu_masuks.*');
                    })
                    ->getTitleFromRecordUsing(function ($record, $records = null) {
                        $kode = $record->lahan?->kode_lahan ?? '-';
                        $nama = $record->lahan?->nama_lahan ?? '-';
                        $jenis_kayu = $record->jenisKayu?->nama_kayu ?? '-';
                        $parentId = $record->id_kayu_masuk ?? $record->kayu_masuk_id ?? null;
                        $lahanId = $record->id_lahan;

                        // ✅ DIUBAH: kubikasi diambil dari accessor model ($r->kubikasi),
                        // bukan dihitung ulang manual dari panjang/diameter/jumlah_batang.
                        // Ini menyamakan pola dengan DetailTurusanKayusTable, sehingga
                        // subtotal grup di sini konsisten dengan nilai yang dipakai
                        // di tempat lain (kolom tabel, Nota Kayu, total harga, dst).
                        if ($records instanceof Collection && $records->isNotEmpty()) {
                            $filtered = $records->where('id_kayu_masuk', $parentId)->where('id_lahan', $lahanId);
                            $totalBatang   = $filtered->sum(fn($r) => (int) ($r->jumlah_batang ?? 0));
                            $totalKubikasi = $filtered->sum(fn($r) => $r->kubikasi);       // ← round() dihapus
                        } else {
                            $query = DetailKayuMasuk::where('id_kayu_masuk', $parentId)->where('id_lahan', $lahanId)->get();
                            $totalBatang   = $query->sum('jumlah_batang');
                            $totalKubikasi = $query->sum(fn($r) => $r->kubikasi);          // ← round() dihapus
                        }

                        return "{$kode} {$nama} {$jenis_kayu} - ".number_format($totalBatang).' batang ('.number_format($totalKubikasi, 4, ',', '.').' m³)';
                    }),
            ])
            ->defaultGroup('lahan.kode_lahan')
            ->groupingSettingsHidden()
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->visible($canPerformAction), // Bypass Lock jika Admin

                // ✅ DIUBAH: total kubikasi diambil dari accessor model ($item->kubikasi),
                // bukan hitung ulang manual. Konsisten dengan pola Turusan.
                Action::make('total_kubikasi')
                    ->label(function () use ($ownerRecord) {
                        if (! $ownerRecord) {
                            return 'Total: 0 m³';
                        }
                        $total = DetailKayuMasuk::where('id_kayu_masuk', $ownerRecord->id)
                            ->get()
                            ->sum(fn($item) => $item->kubikasi);
                        return 'Total: ' . number_format($total, 4, ',', '.') . ' m³';
                    })
                    ->disabled()
                    ->icon('heroicon-o-cube')
                    ->color('gray'),

                Action::make('offlineInput')
                    ->label('Input Mode Offline')
                    ->icon('heroicon-m-signal-slash')
                    ->color('warning')
                    ->modalHeading('Input Kayu (Mode Offline)')
                    ->modalWidth('2xl')
                    ->visible($canPerformAction) // Bypass Lock jika Admin
                    ->modalContent(fn () => view('filament.components.offline-detail-kayu-modal', [
                        'parentId' => $ownerRecord?->id,
                        'optionsLahan' => Lahan::all()->mapWithKeys(fn ($l) => [$l->id => "{$l->kode_lahan} - {$l->nama_lahan}"]),
                        'optionsJenis' => JenisKayu::pluck('nama_kayu', 'id'),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),
            ])
            ->recordActions([
                Action::make('kurangiBatang')
                    ->label('')
                    ->icon('heroicon-o-minus')
                    ->color('danger')
                    ->button()
                    ->size('sm')
                    ->visible($canPerformAction) // Bypass Lock jika Admin
                    ->action(fn (DetailKayuMasuk $record) => $record->jumlah_batang > 0 ? $record->decrement('jumlah_batang') : null),

                Action::make('tambahBatang')
                    ->label('')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->visible($canPerformAction) // Bypass Lock jika Admin
                    ->action(fn (DetailKayuMasuk $record) => $record->increment('jumlah_batang')),

                EditAction::make()->visible($canPerformAction), // Bypass Lock jika Admin
                DeleteAction::make()->visible($canPerformAction), // Bypass Lock jika Admin
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('update_lahan')
                        ->label('Update Lahan')
                        ->icon('heroicon-o-map')
                        ->schema([
                            Select::make('id_lahan')->label('Lahan Baru')->options(Lahan::pluck('kode_lahan', 'id')->toArray())->required(),
                        ])
                        ->action(fn (array $data, Collection $records) => $records->each->update(['id_lahan' => $data['id_lahan']]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('update_panjang')
                        ->label('Update Panjang')
                        ->icon('heroicon-o-arrows-up-down')
                        ->schema([
                            Select::make('panjang')->label('Panjang Baru')->options([130 => '130', 260 => '260'])->required(),
                        ])
                        ->action(fn (array $data, Collection $records) => $records->each->update(['panjang' => $data['panjang']]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('update_jenis_kayu')
                        ->label('Update Jenis Kayu')
                        ->icon('heroicon-o-tag')
                        ->schema([
                            Select::make('id_jenis_kayu')
                                ->label('Jenis Kayu Baru')
                                ->options(JenisKayu::pluck('nama_kayu', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            $records->each->update([
                                'id_jenis_kayu' => $data['id_jenis_kayu'],
                            ]);
                        })
                        ->deselectRecordsAfterCompletion(),
                ])->visible($canPerformAction), // Bypass Lock jika Admin untuk semua Bulk Action
            ]);
    }
}
