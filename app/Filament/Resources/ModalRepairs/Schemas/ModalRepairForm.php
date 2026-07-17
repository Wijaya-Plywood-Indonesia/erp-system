<?php

namespace App\Filament\Resources\ModalRepairs\Schemas;

use App\Models\JenisKayu;
use App\Models\ModalRepair;
use App\Models\SerahTerimaVeneerKering;
use App\Models\Ukuran;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class ModalRepairForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Hidden::make('id_produksi_repair')
                    ->default(fn($livewire) => $livewire->getOwnerRecord()?->id),

                // ✅ Field UI saja, tidak disimpan langsung.
                // Menentukan apakah user pilih palet asli atau "Afalan".
                Select::make('palet_select')
                    ->label('Pilih Palet (Veneer)')
                    ->options(fn(?ModalRepair $record) => self::getPaletOptions($record))
                    ->searchable()
                    ->live()
                    ->required(fn($record) => $record === null) // required hanya saat create
                    ->disabled(fn($record) => $record !== null) // sembunyikan edit ulang palet
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Set $set, ?ModalRepair $record) {
                        if (!$record) {
                            return;
                        }

                        if ($record->id_serah_terima_veneer_kering === null) {
                            $set('palet_select', 'AF');
                        } else {
                            $set('palet_select', (string) $record->id_serah_terima_veneer_kering);
                        }
                    })
                    ->afterStateUpdated(function ($state, Set $set, ?ModalRepair $record) {
                        if (!$state) {
                            $set('id_serah_terima_veneer_kering', null);
                            $set('id_ukuran', null);
                            $set('id_jenis_kayu', null);
                            $set('id_ukuran_select', null);
                            $set('id_jenis_kayu_select', null);
                            $set('kw', null);
                            $set('nomor_palet', null);
                            $set('ukuran_label', null);
                            $set('jenis_kayu_label', null);
                            $set('jenis_terima_label', null);
                            $set('sisa_tersedia', null);
                            $set('jumlah', null);

                            return;
                        }

                        if ($state === 'AF') {
                            // Nomor afalan murni angka (kolom nomor_palet masih int),
                            // diambil dari total baris modal_repairs + 1.
                            // id_serah_terima_veneer_kering diisi NULL karena FK mengharuskan nilai
                            // yang valid (ada di tabel serah_terima_veneer_kering) atau NULL.
                            $newAfNumber = DB::table((new ModalRepair)->getTable())->count() + 1;

                            $set('id_serah_terima_veneer_kering', null);
                            $set('af_generated_id', $newAfNumber);

                            // Kosongkan agar diisi manual oleh user lewat id_ukuran_select / id_jenis_kayu_select.
                            $set('id_ukuran', null);
                            $set('id_jenis_kayu', null);
                            $set('id_ukuran_select', null);
                            $set('id_jenis_kayu_select', null);
                            $set('kw', 'AF');
                            $set('nomor_palet', $newAfNumber);
                            $set('ukuran_label', null);
                            $set('jenis_kayu_label', null);
                            $set('jenis_terima_label', 'Afalan');
                            $set('sisa_tersedia', null);
                            $set('jumlah', null);

                            return;
                        }

                        // Palet asli dipilih.
                        $serahTerima = SerahTerimaVeneerKering::with([
                            'detailHasil.ukuran', 'detailHasil.jenisKayu',
                            'detailBongkarKedi.ukuran', 'detailBongkarKedi.jenisKayu',
                            'mutasiKeluarPalet.mutasiKeluar.ukuran',
                            'mutasiKeluarPalet.mutasiKeluar.jenisKayu',
                            'mutasiKeluarPaletJadi.mutasiKeluar.jenisKayu',
                        ])->find($state);

                        $sumber = $serahTerima?->sumber;
                        $tampilan = $serahTerima?->tampilan ?? ['no_palet' => '-', 'dimensi' => '-', 'jenis_kayu' => '-', 'kw' => '-'];

                        $sisa = $serahTerima?->sisa ?? 0;
                        if ($record && $record->id_serah_terima_veneer_kering === (int) $state) {
                            $sisa += (float) $record->jumlah;
                        }

                        // ✅ Nomor palet SELALU nomor baru (turunan urutan modal_repairs),
                        // sama persis seperti jalur AF — bukan lagi diambil dari no_palet sumber.
                        $newPaletNumber = DB::table((new ModalRepair)->getTable())->count() + 1;
                        if ($record && $record->nomor_palet) {
                            // Saat edit, pertahankan nomor palet yang sudah ada, jangan generate baru lagi.
                            $newPaletNumber = $record->nomor_palet;
                        }

                        // id_ukuran & id_jenis_kayu: gudang & gudang_jadi resolve id_ukuran
                        // menggunakan relasi mutasiKeluar atau helper cariUkuran.
                        $idUkuran = match ($serahTerima?->tipe_sumber) {
                            'gudang' => $serahTerima->mutasiKeluarPalet?->mutasiKeluar?->id_ukuran,
                            'gudang_jadi' => $serahTerima->mutasiKeluarPaletJadi?->mutasiKeluar
                                ? SerahTerimaVeneerKering::cariUkuran(
                                    $serahTerima->mutasiKeluarPaletJadi->mutasiKeluar->panjang,
                                    $serahTerima->mutasiKeluarPaletJadi->mutasiKeluar->lebar,
                                    $serahTerima->mutasiKeluarPaletJadi->mutasiKeluar->tebal
                                )
                                : null,
                            default => $sumber?->id_ukuran,
                        };
                        $idJenisKayu = match ($serahTerima?->tipe_sumber) {
                            'gudang' => $serahTerima->mutasiKeluarPalet?->mutasiKeluar?->id_jenis_kayu,
                            'gudang_jadi' => $serahTerima->mutasiKeluarPaletJadi?->mutasiKeluar?->id_jenis_kayu,
                            default => $sumber?->id_jenis_kayu,
                        };

                        $set('id_serah_terima_veneer_kering', (int) $state);
                        // ✅ Field Hidden ini yang benar-benar disimpan ke DB — sumber kebenaran tunggal,
                        // sama seperti jalur AF (yang menyimpan lewat sync dari _select).
                        $set('id_ukuran', $idUkuran);
                        $set('id_jenis_kayu', $idJenisKayu);
                        $set('kw', $tampilan['kw']);
                        $set('nomor_palet', $newPaletNumber);
                        $set('ukuran_label', $tampilan['dimensi']);
                        $set('jenis_kayu_label', $tampilan['jenis_kayu']);
                        $set('jenis_terima_label', $serahTerima?->label_jenis_terima ?? '-');
                        $set('sisa_tersedia', $sisa);
                        $set('jumlah', $sisa);
                    }),

                // ✅ Field asli yang disimpan ke DB. NULL untuk Afalan (sudah nullable di DB).
                Hidden::make('id_serah_terima_veneer_kering')
                    ->dehydrated(true),

                // ==========================================================
                // ✅ FIELD YANG BENAR-BENAR DISIMPAN KE DB.
                // Selalu ada di form state (tidak pernah hilang dari DOM),
                // sehingga dehydrate konsisten baik untuk AF maupun Serah Terima.
                // Diisi dari dua jalur:
                //   1. AF      -> disinkron dari Select id_ukuran_select / id_jenis_kayu_select (manual).
                //   2. Serah   -> diisi otomatis di afterStateUpdated milik palet_select.
                // ==========================================================
                Hidden::make('id_ukuran')
                    ->dehydrated(true),

                Hidden::make('id_jenis_kayu')
                    ->dehydrated(true),

                // ✅ Select ini HANYA tampil & dipakai saat AF, murni untuk input manual user.
                // Tidak pernah disimpan langsung (dehydrated false) — nilainya disalin ke Hidden di atas.
                Select::make('id_jenis_kayu_select')
                    ->label('Jenis Kayu')
                    ->options(JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                    ->searchable()
                    ->live()
                    ->visible(fn(Get $get) => $get('palet_select') === 'AF')
                    ->required(fn(Get $get) => $get('palet_select') === 'AF')
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Set $set, ?ModalRepair $record) {
                        // Saat edit record AF, isi ulang select dari nilai yang sudah tersimpan.
                        if ($record && $record->id_serah_terima_veneer_kering === null) {
                            $set('id_jenis_kayu_select', $record->id_jenis_kayu);
                        }
                    })
                    ->afterStateUpdated(fn($state, Set $set) => $set('id_jenis_kayu', $state)),

                Select::make('id_ukuran_select')
                    ->label('Ukuran')
                    ->options(Ukuran::all()->pluck('nama_ukuran', 'id'))
                    ->searchable()
                    ->live()
                    ->visible(fn(Get $get) => $get('palet_select') === 'AF')
                    ->required(fn(Get $get) => $get('palet_select') === 'AF')
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Set $set, ?ModalRepair $record) {
                        if ($record && $record->id_serah_terima_veneer_kering === null) {
                            $set('id_ukuran_select', $record->id_ukuran);
                        }
                    })
                    ->afterStateUpdated(fn($state, Set $set) => $set('id_ukuran', $state)),

                TextInput::make('sisa_tersedia')
                    ->label('Sisa Tersedia (Lembar)')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?ModalRepair $record) {
                        if (!$record?->serahTerimaVeneerKering) {
                            return;
                        }

                        $set('sisa_tersedia', $record->serahTerimaVeneerKering->sisa + (float) $record->jumlah);
                    }),

                TextInput::make('jumlah')
                    ->label('Jumlah Digunakan (Lembar)')
                    ->numeric()
                    ->required()
                    ->rules([
                        fn(Get $get, ?ModalRepair $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $idSerahTerima = $get('id_serah_terima_veneer_kering');

                            // Afalan (id null, tanpa record asli) tidak divalidasi terhadap sisa.
                            if (!$idSerahTerima) {
                                return;
                            }

                            $serahTerima = SerahTerimaVeneerKering::find($idSerahTerima);

                            if (!$serahTerima) {
                                return;
                            }

                            $sisa = $serahTerima->sisa;

                            if ($record && $record->id_serah_terima_veneer_kering === (int) $idSerahTerima) {
                                $sisa += (float) $record->jumlah;
                            }

                            if ($value > $sisa) {
                                $fail("Jumlah melebihi sisa yang tersedia ({$sisa} lembar).");
                            }
                        },
                    ]),

                TextInput::make('jenis_terima_label')
                    ->label('Jenis Veneer')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?ModalRepair $record) {
                        if (!$record?->serahTerimaVeneerKering) {
                            return;
                        }

                        $set('jenis_terima_label', $record->serahTerimaVeneerKering->label_jenis_terima);
                    }),

                TextInput::make('ukuran_label')
                    ->label('Ukuran Kayu')
                    ->disabled()
                    ->visible(fn(Get $get) => $get('palet_select') !== 'AF')
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?ModalRepair $record) {
                        if (!$record?->ukuran) {
                            return;
                        }

                        $set('ukuran_label', "{$record->ukuran->panjang} x {$record->ukuran->lebar} x {$record->ukuran->tebal}");
                    }),

                TextInput::make('jenis_kayu_label')
                    ->label('Jenis Kayu')
                    ->disabled()
                    ->visible(fn(Get $get) => $get('palet_select') !== 'AF')
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?ModalRepair $record) {
                        if (!$record?->jenisKayu) {
                            return;
                        }

                        $set('jenis_kayu_label', $record->jenisKayu->nama_kayu);
                    }),

                TextInput::make('kw')
                    ->label('KW')
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('nomor_palet')
                    ->label('Nomor Palet')
                    ->disabled()
                    ->dehydrated(),

                Textarea::make('keterangan')
                    ->label('Kehilangan/Kelebihan')
                    ->columnSpanFull(),
            ]);
    }

    protected static function getPaletOptions(?ModalRepair $record): array
    {
        $currentId = $record?->id_serah_terima_veneer_kering;
        $currentJumlah = (float) ($record?->jumlah ?? 0);

        $options = SerahTerimaVeneerKering::query()
            ->where('diterima_oleh', '!=', '-')
            ->with([
                'detailHasil.ukuran', 'detailHasil.jenisKayu',
                'detailBongkarKedi.ukuran', 'detailBongkarKedi.jenisKayu',
                'mutasiKeluarPalet.mutasiKeluar.ukuran',
                'mutasiKeluarPalet.mutasiKeluar.jenisKayu',
                'mutasiKeluarPaletJadi.mutasiKeluar.jenisKayu',
            ])
            ->get()
            ->map(function ($item) use ($currentId, $currentJumlah) {
                $sisa = $item->sisa + ($item->id === $currentId ? $currentJumlah : 0);

                return [$item, $sisa];
            })
            ->filter(fn($pair) => $pair[1] > 0)
            ->mapWithKeys(function ($pair) {
                [$item, $sisa] = $pair;
                $tampilan = $item->tampilan;

                $label = "Palet {$tampilan['no_palet']} · {$tampilan['dimensi']} {$tampilan['jenis_kayu']} KW{$tampilan['kw']}"
                    . " · ({$item->label_jenis_terima}) · Sisa {$sisa} lbr";

                return [$item->id => $label];
            })
            ->toArray();

        if ($record) {
            return $options;
        }

        return ['AF' => 'Afalan'] + $options;
    }
}
