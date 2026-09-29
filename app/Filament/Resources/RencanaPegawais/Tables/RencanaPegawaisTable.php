<?php

namespace App\Filament\Resources\RencanaPegawais\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Textarea;
use Filament\Actions\CreateAction;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use App\Services\PindahPegawaiService;

class RencanaPegawaisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Pekerja')
                    ->formatStateUsing(
                        fn($record) => $record->pegawai
                            ? $record->pegawai->kode_pegawai . ' - ' . $record->pegawai->nama_pegawai
                            : '—'
                    )
                    ->badge()
                    ->searchable(
                        query: fn($query, $search) => $query->whereHas(
                            'pegawai',
                            fn($q) => $q
                                ->where('nama_pegawai', 'like', "%{$search}%")
                                ->orWhere('kode_pegawai', 'like', "%{$search}%")
                        )
                    ),
                TextColumn::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->time()
                    ->sortable(),
                TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->time()
                    ->sortable(),
                TextColumn::make('ijin')
                    ->default('-')
                    ->searchable(),
                TextColumn::make('keterangan')
                    ->default('-')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('ijin_keterangan')
                    ->label('Ijin & Keterangan')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->form([
                        Select::make('ijin')
                            ->label('Jenis Ijin')
                            ->options([
                                '' => 'Tidak Ada Ijin',
                                'sakit' => 'Sakit',
                                'izin' => 'Izin Pribadi',
                                'cuti' => 'Cuti',
                                'alpha' => 'Tanpa Keterangan',
                            ])
                            ->native(false)
                            ->default(fn($record) => $record->ijin)
                            ->reactive(),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
                            ->placeholder('Alasan ijin / keterangan tambahan...')
                            ->default(fn($record) => $record->keterangan),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'ijin' => $data['ijin'] === '' ? null : $data['ijin'],
                            'keterangan' => $data['keterangan'],
                        ]);

                        Notification::make()
                            ->title('Ijin & keterangan berhasil disimpan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn($record) => "Ijin & Keterangan - {$record->pegawai->nama_pegawai}")
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg'),
                Action::make('pindah_pegawai')
                    ->label('Pindah')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->form(self::skemaPindah())
                    ->modalHeading(fn($record) => "Pindah Pegawai - {$record->pegawai->nama_pegawai}")
                    ->modalDescription('Jam pulang di produksi ini dikurangi, dan jam tersebut dibuatkan di produksi tujuan (diambil dari akhir shift).')
                    ->modalSubmitActionLabel('Pindahkan')
                    ->action(fn($record, array $data) => self::jalankanPindah(collect([$record]), $data)),
                Action::make('batal_pindah')
                    ->label('Batal Pindah')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn($record) => PindahPegawaiService::logAktif('repair', $record->getKey()) !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan pindah pegawai?')
                    ->modalDescription('Jam pulang dikembalikan seperti sebelum dipindah, dan baris pegawai di produksi tujuan dihapus.')
                    ->action(function ($record) {
                        try {
                            $log = PindahPegawaiService::logAktif('repair', $record->getKey());
                            PindahPegawaiService::batalkan($log);
                            Notification::make()->success()->title('Pindah pegawai dibatalkan')->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->danger()->title('Gagal membatalkan')->body($e->getMessage())->send();
                        }
                    }),
                EditAction::make(),
                Action::make('delete_rencana')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        if ($record->rencanaRepairs()->exists()) {
                            Notification::make()
                                ->title('Tidak bisa dihapus!')
                                ->body('Rencana Pegawai ini masih memiliki data repair yang terkait. Hapus data repair terlebih dahulu.')
                                ->warning()
                                ->send();
                            return; // Hentikan delete
                        }

                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Data berhasil dihapus')
                            ->send();
                    })
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('pindah_pegawai_massal')
                        ->label('Pindah Pegawai')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('info')
                        ->form(self::skemaPindah())
                        ->modalHeading('Pindah Pegawai Terpilih')
                        ->modalDescription('Semua pegawai terpilih dipindah dengan durasi dan tujuan yang sama. Kalau satu gagal, semuanya dibatalkan.')
                        ->modalSubmitActionLabel('Pindahkan')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn(Collection $records, array $data) => self::jalankanPindah($records, $data)),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Form pindah pegawai (dipakai tombol per baris dan bulk action). */
    private static function skemaPindah(): array
    {
        // Tanggal produksi Repair yang sedang dibuka (semua pegawai di halaman ini satu tanggal)
        $tanggalRepair = fn($livewire) => $livewire->getOwnerRecord()->tanggal;

        // Lini yang produksinya per shift (Press Dryer, Hotpress, Graji Triplek, Dempul) selalu memilih Shift.
        $perluShift = fn(Get $get) => PindahPegawaiService::config($get('tujuan'))['shift'] ?? false;

        // Lini per mesin (Rotary, Sanding, Kedi) selalu memilih mesinnya.
        // Lini lain hanya menampilkan pilihan produksi kalau ada LEBIH DARI SATU di tanggal itu;
        // kalau hanya satu, otomatis dipakai oleh service.
        $perluPilihProduksi = function (Get $get, $livewire) use ($tanggalRepair) {
            $cfg = PindahPegawaiService::config($get('tujuan'));
            if (! $cfg || $cfg['auto_create'] || $cfg['shift']) {
                return false;
            }
            if ($cfg['pilih']) {
                return true;
            }

            return count(PindahPegawaiService::opsiProduksi($get('tujuan'), $tanggalRepair($livewire))) > 1;
        };

        // Tugas / meja hanya ditanyakan untuk lini yang mewajibkannya dan tidak punya default
        $perluTugas = function (Get $get) {
            $cfg = PindahPegawaiService::config($get('tujuan'));

            return $cfg && ($cfg['tugas'] ?? null) === 'required' && blank($cfg['tugas_default'] ?? null);
        };

        return [
            Select::make('tujuan')
                ->label('Pindah ke Produksi')
                ->options(PindahPegawaiService::opsiTujuan())
                ->native(false)
                ->searchable()
                ->required()
                ->live(),

            Select::make('shift')
                ->label('Shift Tujuan')
                ->options(['PAGI' => 'Pagi', 'MALAM' => 'Malam'])
                ->native(false)
                ->visible($perluShift)
                ->required($perluShift),

            Select::make('id_produksi')
                ->label(fn(Get $get) => PindahPegawaiService::config($get('tujuan'))['pilih'] ?? 'Produksi Tujuan')
                ->options(fn(Get $get, $livewire) => PindahPegawaiService::opsiProduksi($get('tujuan'), $tanggalRepair($livewire)))
                ->native(false)
                ->visible($perluPilihProduksi)
                ->required($perluPilihProduksi),

            Select::make('id_mesin')
                ->label('Mesin Tujuan')
                ->options(fn() => PindahPegawaiService::opsiMesinHotpress())
                ->native(false)
                ->visible(fn(Get $get) => PindahPegawaiService::config($get('tujuan'))['mesin'] ?? false)
                ->required(fn(Get $get) => PindahPegawaiService::config($get('tujuan'))['mesin'] ?? false),

            TextInput::make('tugas')
                ->label('Nomor Meja / Tugas')
                ->visible($perluTugas)
                ->required($perluTugas),

            TextInput::make('durasi')
                ->label('Durasi Pindah')
                ->numeric()
                ->minValue(0.25)
                ->step(0.25)
                ->suffix('jam')
                ->required()
                ->helperText('Contoh: 1 jam. Jam pulang di Repair berkurang 1 jam, dan 1 jam itu dicatat di produksi tujuan.'),

            Textarea::make('keterangan')
                ->label('Keterangan (opsional)')
                ->rows(2)
                ->placeholder('Contoh: bantu dryer'),
        ];
    }

    private static function jalankanPindah(Collection $rows, array $data): void
    {
        try {
            $jumlah = PindahPegawaiService::pindahkan(
                'repair',
                $rows,
                $data['tujuan'],
                (float) $data['durasi'],
                $data['id_produksi'] ?? null,
                $data['shift'] ?? null,
                $data['tugas'] ?? null,
                $data['id_mesin'] ?? null,
                $data['keterangan'] ?? null,
            );

            $tujuan = PindahPegawaiService::opsiTujuan()[$data['tujuan']] ?? $data['tujuan'];
            Notification::make()
                ->success()
                ->title("{$jumlah} pegawai dipindahkan ke {$tujuan}")
                ->body("Durasi {$data['durasi']} jam diambil dari akhir shift.")
                ->send();
        } catch (\RuntimeException $e) {
            Notification::make()
                ->danger()
                ->title('Gagal memindahkan pegawai')
                ->body($e->getMessage())
                ->send();
        }
    }
}