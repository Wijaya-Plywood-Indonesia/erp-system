<?php

namespace App\Filament\Resources\ProduksiKedis\Schemas;

use App\Models\Mesin;
use App\Models\ProduksiKedi;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ProduksiKediForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /**
             * ==========================
             * 📅 TANGGAL PRODUKSI
             * ==========================
             */
            // Catatan: rule uniqueness (tanggal + id_mesin + status) sudah
            // dipindahkan seluruhnya ke field id_mesin di bawah, supaya tidak
            // overlap dan errornya muncul di field yang tepat.
            DatePicker::make('tanggal')
                ->label('Tanggal Produksi')
                ->default(fn () => now())
                ->displayFormat('d F Y')
                ->required()
                ->reactive()
                ->live() // Pastikan live agar mentrigger perubahan dropdown mesin
                ->afterStateUpdated(function ($state, $get, ?ProduksiKedi $record) {
                    self::notifyIfMachineConflict($state, $get('id_mesin'), $get('status') ?? 'masuk', $record);
                }),

            DatePicker::make('tanggal_bongkar')
                ->label('Tanggal Bongkar')
                ->displayFormat('d F Y')
                ->visible(fn (?ProduksiKedi $record) => $record && in_array($record->status, ['bongkar', 'selesai']))
                ->required(fn ($get) => $get('status') === 'bongkar')
                ->default(now()),

            /**
             * ==========================
             * ⚙️ MESIN KEDI
             * ==========================
             */
            Select::make('id_mesin')
                ->label('Mesin Kedi')
                ->options(function ($get, ?ProduksiKedi $record) {
                    $tanggal = $get('tanggal');
                    $status = $get('status');

                    if (! $tanggal) {
                        return Mesin::whereHas('kategoriMesin', fn ($q) => $q->where('nama_kategori_mesin', 'DRYER')
                        )->pluck('nama_mesin', 'id');
                    }

                    // Ambil daftar mesin yang sudah digunakan pada tanggal & status tersebut
                    $usedMachineIds = ProduksiKedi::whereDate('tanggal', $tanggal)
                        ->where('status', $status)
                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                        ->pluck('id_mesin')
                        ->filter()
                        ->toArray();

                    return Mesin::whereHas('kategoriMesin', fn ($q) => $q->where('nama_kategori_mesin', 'DRYER')
                    )
                        ->get()
                        ->mapWithKeys(function ($mesin) use ($usedMachineIds) {
                            $isUsed = in_array($mesin->id, $usedMachineIds);
                            $label = $isUsed ? "{$mesin->nama_mesin} (Sudah digunakan)" : $mesin->nama_mesin;

                            return [$mesin->id => $label];
                        });
                })
                // Pastikan label value yang sedang tersimpan selalu bisa ditemukan,
                // walaupun options() di atas sedang tidak mengembalikannya
                // (mis. karena tanggal/status baru saja berubah lewat live()).
                // Tanpa ini, Filament kadang menampilkan dropdown kosong / "hilang"
                // untuk value yang sebenarnya masih valid.
                ->getOptionLabelUsing(fn ($value) => $value
                    ? Mesin::find($value)?->nama_mesin
                    : null)
                // Catatan: validasi & pesan error "sudah digunakan" ditangani
                // penuh oleh rule closure di bawah (bukan validasi bawaan
                // Filament untuk disableOptionWhen), supaya pesannya custom.
                // disableOptionWhen dikembalikan untuk UX (opsi tampak disabled
                // di dropdown). Validasi & pesan error tetap ditangani oleh
                // rule closure + notifikasi live di bawah, bukan oleh mekanisme
                // validasi bawaan Filament untuk opsi disabled.
                ->disableOptionWhen(function ($value, $get, ?ProduksiKedi $record) {
                    $tanggal = $get('tanggal');
                    $status = $get('status');
                    if (! $tanggal) {
                        return false;
                    }

                    return ProduksiKedi::whereDate('tanggal', $tanggal)
                        ->where('status', $status)
                        ->where('id_mesin', $value)
                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                        ->exists();
                })
                ->rule(function ($get, ?ProduksiKedi $record) {
                    return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                        $tanggal = $get('tanggal');
                        $status = $get('status');

                        if (! $tanggal || ! $value) {
                            return;
                        }

                        $isUsed = ProduksiKedi::whereDate('tanggal', $tanggal)
                            ->where('status', $status)
                            ->where('id_mesin', $value)
                            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                            ->exists();

                        if ($isUsed) {
                            $fail('Mesin ini sudah digunakan pada tanggal dan status yang sama.');
                        }
                    };
                })
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function ($state, $get, ?ProduksiKedi $record) {
                    self::notifyIfMachineConflict($get('tanggal'), $state, $get('status') ?? 'masuk', $record);
                }),

            DatePicker::make('tanggal_actual_bongkar')
                ->label('Tanggal Aktual Bongkar')
                ->displayFormat('d F Y')
                ->default(fn () => now())
                ->visible(fn (?ProduksiKedi $record) => $record && $record->status !== 'masuk')
                ->required(fn (?ProduksiKedi $record) => $record && $record->status !== 'masuk'),

            DatePicker::make('rencana_bongkar')
                ->label('Rencana Bongkar')
                ->displayFormat('d F Y')
                ->default(fn () => now()->addDays(2)) // Default to 2 days after
                ->required(),

            /**
             * ==========================
             * ⚙️ STATUS PRODUKSI
             * ==========================
             */
            Select::make('status')
                ->label('Status Produksi')
                ->options([
                    'masuk' => 'Masuk',
                    'bongkar' => 'Bongkar',
                ])
                ->default('masuk')
                ->required()
                ->dehydrated() // Pastikan nilai dikirim ke server meskipun hidden
                ->hidden(), // Disembunyikan karena manual create selalu 'masuk'
        ]);
    }

    /**
     * Cek apakah kombinasi tanggal + mesin + status sudah dipakai,
     * dan tampilkan notifikasi langsung (bukan menunggu submit) jika iya.
     */
    protected static function notifyIfMachineConflict($tanggal, $idMesin, $status, ?ProduksiKedi $record): void
    {
        if (! $tanggal || ! $idMesin) {
            return;
        }

        $isUsed = ProduksiKedi::whereDate('tanggal', $tanggal)
            ->where('status', $status)
            ->where('id_mesin', $idMesin)
            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
            ->exists();

        if ($isUsed) {
            $mesinNama = Mesin::find($idMesin)?->nama_mesin ?? 'Mesin ini';

            Notification::make()
                ->title('Mesin sudah digunakan')
                ->body("{$mesinNama} sudah digunakan pada tanggal dan status yang sama. Silakan pilih mesin atau tanggal lain.")
                ->danger()
                ->send();
        }
    }
}
