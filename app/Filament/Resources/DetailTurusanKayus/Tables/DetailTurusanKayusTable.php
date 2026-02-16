<?php

namespace App\Filament\Resources\DetailTurusanKayus\Tables;

use App\Models\DetailTurusanKayu;
use App\Models\JenisKayu;
use App\Models\Lahan;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;

class DetailTurusanKayusTable
{
    public static function configure(Table $table, $livewire = null): Table
    {
        // 1. LOGIKA LOCK: Cek status Nota melalui Owner Record (KayuMasuk)
        $isLocked = false;
        $ownerRecord = null;
        if ($livewire && method_exists($livewire, 'getOwnerRecord')) {
            $ownerRecord = $livewire->getOwnerRecord();
            $isLocked = $ownerRecord &&
                $ownerRecord->notaKayu &&
                $ownerRecord->notaKayu->status !== 'Belum Diperiksa';
        }

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
                    ->sortable(['jenisKayu.nama_kayu', 'panjang', 'grade'])
                    ->searchable(['jenisKayu.nama_kayu', 'panjang'])
                    ->color(fn($record) => match (trim((string) $record->grade)) {
                        '1', 'A' => 'success',
                        '2', 'B' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('diameter')
                    ->label('D')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('kubikasi')
                    ->label('Kubikasi')
                    ->getStateUsing(function ($record) {
                        $diameter = (int) $record->diameter;
                        $kuantitas = $record->kuantitas ?? 1;
                        $kubikasi = $diameter * $diameter * $kuantitas * 0.785 / 1_000_000;
                        return number_format($kubikasi, 6, ',', '.');
                    })
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

                        if ($records instanceof Collection && $records->isNotEmpty()) {
                            $totalBatang = $records->count();

                            $totalKubikasi = $records->sum(
                                fn($r) =>
                                (($r->panjang ?? 0) * ($r->diameter ?? 0) * ($r->diameter ?? 0) * ($r->kuantitas ?? 1) * 0.785) / 1000000
                            );
                        } else {
                            $parentId = $record->id_kayu_masuk ?? $record->kayu_masuk_id;

                            $query = DetailTurusanKayu::where('id_kayu_masuk', $parentId)
                                ->where('lahan_id', $record->lahan_id)
                                ->get();

                            $totalBatang = $query->count();

                            $totalKubikasi = $query->sum(
                                fn($r) =>
                                (($r->panjang ?? 0) * ($r->diameter ?? 0) * ($r->diameter ?? 0) * ($r->kuantitas ?? 1) * 0.785) / 1000000
                            );
                        }

                        return "{$kode} {$nama} {$jenis_kayu} - {$totalBatang} batang (" .
                            number_format($totalKubikasi, 4, ',', '.') . " m³)";
                    }),
            ])
            ->defaultGroup('lahan.kode_lahan')
            ->headerActions([
                // CREATE ONLINE (LOCKED)
                CreateAction::make()
                    ->label('Tambah Kayu')
                    ->createAnother(true)
                    ->visible(!$isLocked)
                    ->after(function ($record) {
                        Notification::make()
                            ->title("Batang D: {$record->diameter} cm | No {$record->nomer_urut} ditambahkan")
                            ->success()->send();
                    }),

                // OFFLINE MODE (LOCKED)
                Action::make('offlineInput')
                    ->label('Mode Offline')
                    ->icon('heroicon-m-signal-slash')
                    ->color('warning')
                    ->visible(!$isLocked)
                    ->modalHeading('Input Turusan (Tanpa Sinyal)')
                    ->modalWidth('2xl')
                    ->modalContent(fn() => view('filament.components.offline-turusan-modal', [
                        'parentId' => $ownerRecord?->id,
                        'optionsLahan' => Lahan::get()->mapWithKeys(fn($l) => [$l->id => "{$l->kode_lahan} - {$l->nama_lahan}"]),
                        'optionsJenis' => JenisKayu::get()->mapWithKeys(fn($j) => [$j->id => "{$j->kode_kayu} - {$j->nama_kayu}"]),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false),
            ])
            ->recordActions([
                EditAction::make()->visible(!$isLocked),
                DeleteAction::make()->visible(!$isLocked),
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
                        ->action(fn(array $data, Collection $records) => $records->each->update(['lahan_id' => $data['lahan_id']])),
                ])->visible(!$isLocked),
            ]);
    }
}
