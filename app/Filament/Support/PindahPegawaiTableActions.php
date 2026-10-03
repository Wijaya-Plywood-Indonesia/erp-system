<?php

namespace App\Filament\Support;

use App\Services\PindahPegawaiService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * Aksi "Pindah Pegawai" (per baris, dan massal) + "Batal Pindah", dipakai ulang
 * di *Table.php lini manapun. Supaya lini baru bisa pindah pegawai, tinggal:
 *
 *   ->recordActions([
 *       ...
 *       PindahPegawaiTableActions::recordAction('joint', fn ($livewire) => $livewire->getOwnerRecord()->tanggal_produksi),
 *       PindahPegawaiTableActions::batalAction('joint'),
 *   ])
 *   ->toolbarActions([
 *       BulkActionGroup::make([
 *           PindahPegawaiTableActions::bulkAction('joint', fn ($livewire) => $livewire->getOwnerRecord()->tanggal_produksi),
 *           ...
 *       ]),
 *   ])
 *
 * 'joint' harus salah satu kunci di PindahPegawaiService::sumber() (otomatis ada
 * untuk semua lini yang terdaftar di tujuan(), lihat komentar di service itu).
 * $tanggalGetter mengambil tanggal produksi yang SEDANG DIBUKA dari owner record
 * relation manager itu — sesuaikan nama kolom tanggalnya per lini.
 */
class PindahPegawaiTableActions
{
    public static function recordAction(string $kodeSumber, \Closure $tanggalGetter): Action
    {
        return Action::make('pindah_pegawai')
            ->label('Pindah')
            ->icon('heroicon-o-arrows-right-left')
            ->color('info')
            ->form(self::skemaPindah($kodeSumber, $tanggalGetter))
            ->modalHeading(fn ($record) => 'Pindah Pegawai - '.($record->pegawai->nama_pegawai ?? '-'))
            ->modalDescription('Jam pulang di produksi ini dikurangi, dan jam tersebut dibuatkan di produksi tujuan (diambil dari akhir shift).')
            ->modalSubmitActionLabel('Pindahkan')
            ->action(fn ($record, array $data, $livewire) => self::jalankan($kodeSumber, collect([$record]), $data, $tanggalGetter($livewire)));
    }

    public static function batalAction(string $kodeSumber): Action
    {
        return Action::make('batal_pindah')
            ->label('Batal Pindah')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn ($record) => PindahPegawaiService::logAktif($kodeSumber, $record->getKey()) !== null)
            ->requiresConfirmation()
            ->modalHeading('Batalkan pindah pegawai?')
            ->modalDescription('Jam pulang dikembalikan seperti sebelum dipindah. Kalau pindahan tadi digabung ke baris yang sudah ada, baris gabungan itu akan ikut terhapus — cek dulu datanya.')
            ->action(function ($record) use ($kodeSumber) {
                try {
                    $log = PindahPegawaiService::logAktif($kodeSumber, $record->getKey());
                    PindahPegawaiService::batalkan($log);
                    Notification::make()->success()->title('Pindah pegawai dibatalkan')->send();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title('Gagal membatalkan')->body($e->getMessage())->send();
                }
            });
    }

    public static function bulkAction(string $kodeSumber, \Closure $tanggalGetter): BulkAction
    {
        return BulkAction::make('pindah_pegawai_massal')
            ->label('Pindah Pegawai')
            ->icon('heroicon-o-arrows-right-left')
            ->color('info')
            ->form(self::skemaPindah($kodeSumber, $tanggalGetter))
            ->modalHeading('Pindah Pegawai Terpilih')
            ->modalDescription('Semua pegawai terpilih dipindah dengan durasi dan tujuan yang sama. Kalau satu gagal, semuanya dibatalkan.')
            ->modalSubmitActionLabel('Pindahkan')
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records, array $data, $livewire) => self::jalankan($kodeSumber, $records, $data, $tanggalGetter($livewire)));
    }

    /**
     * Nama pegawai (di antara yang sedang diproses) yang sudah punya baris di produksi tujuan
     * yang sedang dipilih di form. Dipakai reaktif — dihitung ulang tiap 'tujuan'/'shift'/
     * 'id_produksi' berubah. $record ada untuk aksi per baris, null untuk aksi massal
     * (yang dicek untuk aksi massal adalah SEMUA baris yang sedang dicentang).
     */
    private static function duplikatNama(string $kodeSumber, \Closure $tanggalGetter, Get $get, $livewire, $record = null): array
    {
        $kodeTujuan = $get('tujuan');
        if (blank($kodeTujuan)) {
            return [];
        }

        $sumberCfg = PindahPegawaiService::sumber()[$kodeSumber] ?? null;
        if (! $sumberCfg) {
            return [];
        }

        if ($record) {
            $idPegawaiList = [$record->id_pegawai];
        } else {
            $keys = [];
            if (method_exists($livewire, 'getSelectedTableRecords')) {
                try {
                    $keys = collect($livewire->getSelectedTableRecords())->all();
                } catch (\Throwable $e) {
                    $keys = [];
                }
            } elseif (property_exists($livewire, 'selectedTableRecords')) {
                $keys = collect($livewire->selectedTableRecords ?? [])->all();
            }

            if (empty($keys)) {
                return [];
            }

            $idPegawaiList = $sumberCfg['model']::query()
                ->whereIn((new $sumberCfg['model'])->getKeyName(), $keys)
                ->pluck('id_pegawai')
                ->all();
        }

        return PindahPegawaiService::pegawaiSudahAda(
            $kodeTujuan,
            $idPegawaiList,
            $tanggalGetter($livewire),
            $get('shift'),
            $get('id_produksi'),
        );
    }

    /** Form pindah pegawai (dipakai tombol per baris dan bulk action). */
    private static function skemaPindah(string $kodeSumber, \Closure $tanggalGetter): array
    {
        // Lini yang produksinya per shift (Press Dryer, Hotpress, Graji Triplek, Dempul) selalu memilih Shift.
        $perluShift = fn (Get $get) => PindahPegawaiService::config($get('tujuan'))['shift'] ?? false;

        // Lini per mesin (Rotary, Sanding, Kedi) selalu memilih mesinnya.
        // Lini lain hanya menampilkan pilihan produksi kalau ada LEBIH DARI SATU di tanggal itu;
        // kalau hanya satu, otomatis dipakai oleh service.
        $perluPilihProduksi = function (Get $get, $livewire) use ($tanggalGetter) {
            $cfg = PindahPegawaiService::config($get('tujuan'));
            if (! $cfg || $cfg['auto_create'] || $cfg['shift']) {
                return false;
            }
            if ($cfg['pilih']) {
                return true;
            }

            return count(PindahPegawaiService::opsiProduksi($get('tujuan'), $tanggalGetter($livewire))) > 1;
        };

        // Tugas / meja hanya ditanyakan untuk lini yang mewajibkannya dan tidak punya default
        $perluTugas = function (Get $get) {
            $cfg = PindahPegawaiService::config($get('tujuan'));

            return $cfg && ($cfg['tugas'] ?? null) === 'required' && blank($cfg['tugas_default'] ?? null);
        };

        // Field peringatan + pilihan mode HANYA muncul kalau memang ada duplikat terdeteksi.
        $adaDuplikat = fn (Get $get, $livewire, $record = null) => ! empty(self::duplikatNama($kodeSumber, $tanggalGetter, $get, $livewire, $record));

        return [
            Select::make('tujuan')
                ->label('Pindah ke Produksi')
                ->options(fn () => PindahPegawaiService::opsiTujuan($kodeSumber))
                ->native(false)
                ->searchable()
                ->required()
                ->live(),

            Select::make('shift')
                ->label('Shift Tujuan')
                ->options(['PAGI' => 'Pagi', 'MALAM' => 'Malam'])
                ->native(false)
                ->visible($perluShift)
                ->required($perluShift)
                ->live(),

            Select::make('id_produksi')
                ->label(fn (Get $get) => PindahPegawaiService::config($get('tujuan'))['pilih'] ?? 'Produksi Tujuan')
                ->options(fn (Get $get, $livewire) => PindahPegawaiService::opsiProduksi($get('tujuan'), $tanggalGetter($livewire)))
                ->native(false)
                ->visible($perluPilihProduksi)
                ->required($perluPilihProduksi)
                ->live(),

            Select::make('id_mesin')
                ->label('Mesin Tujuan')
                ->options(fn () => PindahPegawaiService::opsiMesinHotpress())
                ->native(false)
                ->visible(fn (Get $get) => PindahPegawaiService::config($get('tujuan'))['mesin'] ?? false)
                ->required(fn (Get $get) => PindahPegawaiService::config($get('tujuan'))['mesin'] ?? false),

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
                ->default(1)
                ->required()
                ->helperText('Contoh: 1 jam. Jam pulang di produksi ini berkurang 1 jam, dan 1 jam itu dicatat di produksi tujuan.'),

            // Muncul HANYA kalau pegawainya memang sudah ada di produksi tujuan.
            Placeholder::make('info_duplikat')
                ->label('')
                ->content(function (Get $get, $livewire, $record = null) use ($kodeSumber, $tanggalGetter) {
                    $nama = self::duplikatNama($kodeSumber, $tanggalGetter, $get, $livewire, $record);

                    return new HtmlString(
                        '<div class="text-sm text-amber-600 dark:text-amber-400 font-medium">⚠ '
                        .implode(', ', array_map('e', $nama))
                        .' sudah ada di tujuan.</div>'
                    );
                })
                ->visible($adaDuplikat),

            Select::make('mode_duplikat')
                ->label('Datanya mau diapakan?')
                ->options([
                    'update' => 'Gabung jam (tambah ke pindahan sebelumnya)',
                    'timpa' => 'Timpa (buang riwayat pindahan sebelumnya, ganti total)',
                ])
                ->helperText(fn (Get $get) => ($get('mode_duplikat') ?? 'update') === 'timpa'
                    ? 'Semua pindahan sebelumnya ke tujuan ini DIBATALKAN dulu (jam sumber dikembalikan seperti semula), baru diterapkan durasi yang baru ini sendirian.'
                    : 'Durasi ini DITAMBAHKAN ke pindahan sebelumnya yang masih aktif ke tujuan ini.')
                ->default('update')
                ->native(false)
                ->live()
                ->visible($adaDuplikat),

            Textarea::make('keterangan')
                ->label('Keterangan (opsional)')
                ->rows(2)
                ->placeholder('Contoh: bantu dryer'),
        ];
    }

    private static function jalankan(string $kodeSumber, Collection $rows, array $data, $tanggalSumber): void
    {
        try {
            $hasil = PindahPegawaiService::pindahkan(
                $kodeSumber,
                $rows,
                $data['tujuan'],
                (float) $data['durasi'],
                $tanggalSumber,
                $data['id_produksi'] ?? null,
                $data['shift'] ?? null,
                $data['tugas'] ?? null,
                $data['id_mesin'] ?? null,
                $data['keterangan'] ?? null,
                $data['mode_duplikat'] ?? 'update',
            );

            $tujuan = PindahPegawaiService::opsiTujuan()[$data['tujuan']] ?? $data['tujuan'];
            $total = $hasil['baru'] + $hasil['digabung'] + $hasil['ditimpa'];

            $rincian = [];
            if ($hasil['digabung'] > 0) {
                $rincian[] = "{$hasil['digabung']} digabung ke data yang sudah ada";
            }
            if ($hasil['ditimpa'] > 0) {
                $rincian[] = "{$hasil['ditimpa']} menimpa data yang sudah ada";
            }

            Notification::make()
                ->success()
                ->title("{$total} pegawai dipindahkan ke {$tujuan}")
                ->body(
                    "Durasi {$data['durasi']} jam diambil dari akhir shift."
                    .($rincian ? ' ⚠ '.implode(', ', $rincian).' — sudah pernah dipindah ke sini sebelumnya.' : '')
                )
                ->send();
        } catch (RuntimeException $e) {
            Notification::make()
                ->danger()
                ->title('Gagal memindahkan pegawai')
                ->body($e->getMessage())
                ->send();
        }
    }
}