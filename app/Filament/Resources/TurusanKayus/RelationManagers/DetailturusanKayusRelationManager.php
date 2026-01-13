<?php

namespace App\Filament\Resources\TurusanKayus\RelationManagers;

use App\Models\DetailTurusanKayu;
use App\Models\JenisKayu;
use App\Models\Lahan;
use App\Models\DetailTurunKayu;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Actions\Action; // Tambahan Import Action Custom
use Illuminate\Contracts\View\View; // Tambahan Import View
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class DetailturusanKayusRelationManager extends RelationManager
{
    protected static string $relationship = 'DetailturusanKayus';

    // Listener agar tabel refresh otomatis setelah sync dari offline mode
    protected $listeners = ['refreshDatatable' => '$refresh'];

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        // Ambil status dari detail_turun_kayus berdasarkan kayu masuk
        $detailTurun = DetailTurunKayu::where('id_kayu_masuk', $ownerRecord->id)->first();

        // Jika belum ada record → anggap belum selesai → tidak boleh isi
        if (!$detailTurun) {
            return false;
        }

        // Jika status "menunggu" → boleh isi data
        if ($detailTurun->status === 'menunggu') {
            return true;
        }

        // Jika status "selesai" → tidak boleh isi lagi
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | NOMOR URUT (SUDAH BENAR)
                |--------------------------------------------------------------------------
                */
                TextInput::make('nomer_urut')
                    ->label('Nomor')
                    ->numeric()
                    ->required()
                    ->default(function (callable $get, $livewire) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return 1;

                        $last = DetailTurusanKayu::where('id_kayu_masuk', $parent->id)
                            ->max('nomer_urut');

                        return $last ? $last + 1 : 1;
                    })
                    ->rules(function ($get, $livewire, $record) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return [];

                        return [
                            Rule::unique('detail_turusan_kayus', 'nomer_urut')
                                ->where('id_kayu_masuk', $parent->id)
                                ->where('lahan_id', $get('lahan_id'))
                                ->ignore($record?->id),
                        ];
                    })
                    ->validationMessages([
                        'unique' => 'Nomor ini sudah digunakan pada kayu masuk dan lahan yang sama.',
                    ]),

                /*
                |--------------------------------------------------------------------------
                | LAHAN
                |--------------------------------------------------------------------------
                */
                Select::make('lahan_id')
                    ->label('Lahan')
                    ->options(
                        Lahan::get()
                            ->mapWithKeys(fn($lahan) => [
                                $lahan->id => "{$lahan->kode_lahan} - {$lahan->nama_lahan}",
                            ])
                    )
                    ->default(function ($livewire) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return 1;

                        return DetailTurusanKayu::where('id_kayu_masuk', $parent->id)
                            ->latest('id')
                            ->value('lahan_id') ?? 1;
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (!$state)
                            return $set('panjang', 0);

                        $lahan = Lahan::find($state);

                        if (!$lahan)
                            return $set('panjang', 0);

                        $nama = strtolower($lahan->nama_lahan ?? '');

                        if (str_contains($nama, '130'))
                            return $set('panjang', 130);
                        if (str_contains($nama, '260'))
                            return $set('panjang', 260);

                        $last = DetailTurusanKayu::where('lahan_id', $state)
                            ->latest('id')
                            ->value('panjang');

                        return $set('panjang', $last ?? 0);
                    }),

                /*
                |--------------------------------------------------------------------------
                | PANJANG
                |--------------------------------------------------------------------------
                */
                Select::make('panjang')
                    ->label('Panjang')
                    ->options([
                        130 => '130 cm',
                        260 => '260 cm',
                        0 => 'Tidak Diketahui',
                    ])
                    ->required()
                    ->default(function ($livewire) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return 0;

                        return DetailTurusanKayu::where('id_kayu_masuk', $parent->id)
                            ->latest('id')
                            ->value('panjang') ?? 0;
                    })
                    ->searchable()
                    ->native(false),

                /*
                |--------------------------------------------------------------------------
                | JENIS KAYU
                |--------------------------------------------------------------------------
                */
                Select::make('jenis_kayu_id')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::get()
                            ->mapWithKeys(fn($x) => [
                                $x->id => "{$x->kode_kayu} - {$x->nama_kayu}",
                            ])
                    )
                    ->default(function ($livewire) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return 1;

                        return DetailTurusanKayu::where('id_kayu_masuk', $parent->id)
                            ->latest('id')
                            ->value('jenis_kayu_id') ?? 1;
                    })
                    ->searchable()
                    ->required(),

                /*
                |--------------------------------------------------------------------------
                | GRADE
                |--------------------------------------------------------------------------
                */
                Select::make('grade')
                    ->label('Grade')
                    ->options([
                        1 => 'Grade A',
                        2 => 'Grade B',
                    ])
                    ->required()
                    ->default(function ($livewire) {
                        $parent = $livewire->ownerRecord;

                        if (!$parent)
                            return 1;

                        return DetailTurusanKayu::where('id_kayu_masuk', $parent->id)
                            ->latest('id')
                            ->value('grade') ?? 1;
                    })
                    ->native(false)
                    ->searchable()
                    ->reactive()
                    ->afterStateHydrated(function ($state, $set) {
                        $saved =
                            request()->cookie('filament_local_storage_detail_kayu_masuk.grade')
                            ?? optional(json_decode(request()->header('X-Filament-Local-Storage'), true))['detail_kayu_masuk.grade']
                            ?? null;

                        if ($saved && in_array($saved, [1, 2])) {
                            $set('grade', (int) $saved);
                        }
                    })
                    ->afterStateUpdated(
                        fn($state) =>
                        cookie()->queue('filament_local_storage_detail_kayu_masuk.grade', $state, 60 * 24 * 30)
                    ),

                /*
                |--------------------------------------------------------------------------
                | DIAMETER
                |--------------------------------------------------------------------------
                */
                TextInput::make('diameter')
                    ->label('Diameter (cm)')
                    ->placeholder('Masukkan diameter kayu')
                    ->required()
                    ->numeric()
            ]);
    }

    public function table(Table $table): Table
    {
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

                        // Normalisasi nilai agar aman di server
                        $rawGrade = $record->grade;

                        // buang spasi + uppercase
                        $clean = trim(strtoupper((string) $rawGrade));

                        // konversi ke integer kalau isi angka
                        $gradeInt = is_numeric($clean) ? intval($clean) : $clean;

                        // match fleksibel: terima 1, "1", "A", "a"
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

                        $kubikasi =
                            $diameter * $diameter * $kuantitas * 0.785 / 1_000_000;

                        return number_format($kubikasi, 6, ',', '.');
                    })
                    ->suffix(' m³')
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true)

                    // OPTIONAL: kalau mau bisa di-sort
                    ->sortable(
                        query: fn($q, $direction) =>
                        $q->orderByRaw(
                            '(diameter * diameter * kuantitas * 0.785 / 1000000) ' . $direction
                        )
                    ),
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
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->defaultSort('nomer_urut', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Kayu')
                    ->createAnother(true) // tetap sembunyikan tombol built-in jika mau
                    ->successNotification(null)
                    ->after(function ($record, $action) {
                        $diameter = $record->diameter ?? '-';
                        $nomerUrut = $record->nomer_urut ?? '-';

                        Notification::make()
                            ->title("Batang D : {$diameter} cm | No {$nomerUrut} ditambahkan")
                            ->success()
                            ->send();
                    }),

                // ==========================================
                // TOMBOL OFFLINE MODE (TURUSAN)
                // ==========================================
                Action::make('offlineInput')
                    ->label('Mode Offline')
                    ->icon('heroicon-m-signal-slash')
                    ->color('warning')
                    ->modalHeading('Input Turusan (Tanpa Sinyal)')
                    ->modalWidth('2xl')
                    ->modalContent(function ($livewire): View {
                        $owner = $livewire->getOwnerRecord();

                        // Persiapan data untuk dropdown modal offline
                        // Kita gabungkan Kode - Nama agar user mudah memilih
                        $lahanOptions = Lahan::get()->mapWithKeys(fn($l) => [
                            $l->id => "{$l->kode_lahan} - {$l->nama_lahan}"
                        ]);

                        $jenisOptions = JenisKayu::get()->mapWithKeys(fn($j) => [
                            $j->id => "{$j->kode_kayu} - {$j->nama_kayu}"
                        ]);

                        // Panggil View khusus turusan (offline-turusan-modal.blade.php)
                        return view('filament.components.offline-turusan-modal', [
                            'parentId'     => $owner->id,
                            'optionsLahan' => $lahanOptions,
                            'optionsJenis' => $jenisOptions,
                        ]);
                    })
                    ->modalSubmitAction(false) // Matikan tombol default
                    ->modalCancelAction(false) // Matikan tombol default
                    ->extraAttributes(['id' => 'modal-offline-turusan']),

            ])
            ->groups([
                Group::make('lahan.kode_lahan')
                    ->label('Lahan')
                    ->collapsible()
                    ->getTitleFromRecordUsing(function ($record, $records = null) {
                        $kode = $record->lahan?->kode_lahan ?? '-';
                        $nama = $record->lahan?->nama_lahan ?? '-';
                        $jenis_kayu = $record->jenisKayu?->nama_kayu ?? '-';

                        // Jika $records tersedia gunakan itu (lebih cepat & pakai accessor kubikasi)
                        if ($records instanceof Collection && $records->isNotEmpty()) {
                            $totalBatang = $records->count();
                            $totalKubikasi = $records->sum(fn($r) => (float) $r->kubikasi);
                        } else {
                            // Fallback: hitung via query berdasarkan lahan_id dan parent (id_kayu_masuk)
                            $parentId = $record->id_kayu_masuk ?? $record->kayu_masuk_id ?? null;

                            $query = DetailTurusanKayu::query()
                                ->when($parentId, fn($q) => $q->where('id_kayu_masuk', $parentId))
                                ->where('lahan_id', $record->lahan_id)
                                ->get();

                            $totalBatang = $query->count();
                            $totalKubikasi = $query->sum(fn($r) => (float) $r->kubikasi);
                        }

                        $kubikasiFormatted = number_format($totalKubikasi, 4, ',', '.');

                        return "{$kode} {$nama} {$jenis_kayu} - {$totalBatang} batang ({$kubikasiFormatted} m³)";
                    }),
            ])
            ->defaultGroup('lahan.kode_lahan')
            ->groupingSettingsHidden()
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('update_lahan')
                        ->label('Update Lahan')
                        ->icon('heroicon-o-map')
                        ->schema([
                            Select::make('lahan_id')
                                ->label('Lahan Baru')
                                ->options(Lahan::pluck('kode_lahan', 'id'))
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'lahan_id' => $data['lahan_id'],
                                ]);
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle(fn($count) => "{$count} data berhasil diupdate"),

                    BulkAction::make('update_panjang')
                        ->label('Update Panjang')
                        ->icon('heroicon-o-arrows-up-down')
                        ->schema([
                            Select::make('panjang')
                                ->label('Panjang Baru')
                                ->options([
                                    130 => '130',
                                    260 => '260',
                                ])
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'panjang' => $data['panjang'],
                                ]);
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle(fn($count) => "{$count} data berhasil diupdate"),
                ]),
            ]);
    }
}
