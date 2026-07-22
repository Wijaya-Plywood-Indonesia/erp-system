<?php

namespace App\Filament\Resources\ModalJoints\Schemas;

use App\Models\JenisKayu;
use App\Models\ModalJoint;
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

class ModalJointForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Hidden::make('id_produksi_joint')
                    ->default(fn ($livewire) => $livewire->getOwnerRecord()?->id),

                Select::make('palet_select')
                    ->label('Pilih Palet (Veneer)')
                    ->options(fn (?ModalJoint $record) => self::getPaletOptions($record))
                    ->searchable()
                    ->live()
                    ->required(fn ($record) => $record === null)
                    ->disabled(fn ($record) => $record !== null)
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Set $set, ?ModalJoint $record) {
                        if (! $record) {
                            return;
                        }

                        if ($record->id_serah_terima_veneer_kering === null) {
                            $set('palet_select', 'AF');
                        } else {
                            $set('palet_select', (string) $record->id_serah_terima_veneer_kering);
                        }
                    })
                    ->afterStateUpdated(function ($state, Set $set, ?ModalJoint $record) {
                        if (! $state) {
                            $set('id_serah_terima_veneer_kering', null);
                            $set('id_ukuran', null);
                            $set('id_jenis_kayu', null);
                            $set('id_ukuran_select', null);
                            $set('id_jenis_kayu_select', null);
                            $set('kw', null);
                            $set('no_palet', null);
                            $set('ukuran_label', null);
                            $set('jenis_kayu_label', null);
                            $set('jenis_terima_label', null);
                            $set('sisa_tersedia', null);
                            $set('jumlah', null);

                            return;
                        }

                        if ($state === 'AF') {
                            $newAfNumber = DB::table((new ModalJoint)->getTable())->count() + 1;

                            $set('id_serah_terima_veneer_kering', null);
                            $set('af_generated_id', $newAfNumber);

                            $set('id_ukuran', null);
                            $set('id_jenis_kayu', null);
                            $set('id_ukuran_select', null);
                            $set('id_jenis_kayu_select', null);
                            $set('kw', 'AF');
                            $set('no_palet', $newAfNumber);
                            $set('ukuran_label', null);
                            $set('jenis_kayu_label', null);
                            $set('jenis_terima_label', 'Afalan');
                            $set('sisa_tersedia', null);
                            $set('jumlah', null);

                            return;
                        }

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
                        if ($record && (int) $record->id_serah_terima_veneer_kering === (int) $state) {
                            $sisa += (float) $record->jumlah;
                        }

                        $newPaletNumber = DB::table((new ModalJoint)->getTable())->count() + 1;
                        if ($record && $record->no_palet) {
                            $newPaletNumber = $record->no_palet;
                        }

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
                        $set('id_ukuran', $idUkuran);
                        $set('id_jenis_kayu', $idJenisKayu);
                        $set('kw', $tampilan['kw']);
                        $set('no_palet', $newPaletNumber);
                        $set('ukuran_label', $tampilan['dimensi']);
                        $set('jenis_kayu_label', $tampilan['jenis_kayu']);
                        $set('jenis_terima_label', $serahTerima?->label_jenis_terima ?? '-');
                        $set('sisa_tersedia', $sisa);
                        $set('jumlah', $sisa);
                    }),

                Hidden::make('id_serah_terima_veneer_kering')->dehydrated(true),
                Hidden::make('id_ukuran')->dehydrated(true),
                Hidden::make('id_jenis_kayu')->dehydrated(true),

                Select::make('id_jenis_kayu_select')
                    ->label('Jenis Kayu')
                    ->options(JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                    ->searchable()
                    ->live()
                    ->visible(fn (Get $get) => $get('palet_select') === 'AF')
                    ->required(fn (Get $get) => $get('palet_select') === 'AF')
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Set $set, ?ModalJoint $record) {
                        if ($record && $record->id_serah_terima_veneer_kering === null) {
                            $set('id_jenis_kayu_select', $record->id_jenis_kayu);
                        }
                    })
                    ->afterStateUpdated(fn ($state, Set $set) => $set('id_jenis_kayu', $state)),

                Select::make('id_ukuran_select')
                    ->label('Ukuran')
                    ->options(Ukuran::all()->pluck('nama_ukuran', 'id'))
                    ->searchable()
                    ->live()
                    ->visible(fn (Get $get) => $get('palet_select') === 'AF')
                    ->required(fn (Get $get) => $get('palet_select') === 'AF')
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Set $set, ?ModalJoint $record) {
                        if ($record && $record->id_serah_terima_veneer_kering === null) {
                            $set('id_ukuran_select', $record->id_ukuran);
                        }
                    })
                    ->afterStateUpdated(fn ($state, Set $set) => $set('id_ukuran', $state)),

                TextInput::make('sisa_tersedia')
                    ->label('Sisa Tersedia (Lembar)')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?ModalJoint $record) {
                        if (! $record?->serahTerimaVeneerKering) {
                            return;
                        }

                        $set('sisa_tersedia', $record->serahTerimaVeneerKering->sisa + (float) $record->jumlah);
                    }),

                TextInput::make('jumlah')
                    ->label('Jumlah Digunakan (Lembar)')
                    ->numeric()
                    ->required()
                    ->rules([
                        fn (Get $get, ?ModalJoint $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $idSerahTerima = $get('id_serah_terima_veneer_kering');

                            if (! $idSerahTerima) {
                                return;
                            }

                            $serahTerima = SerahTerimaVeneerKering::find($idSerahTerima);

                            if (! $serahTerima) {
                                return;
                            }

                            $sisa = $serahTerima->sisa;

                            if ($record && (int) $record->id_serah_terima_veneer_kering === (int) $idSerahTerima) {
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
                    ->afterStateHydrated(function ($set, ?ModalJoint $record) {
                        if (! $record?->serahTerimaVeneerKering) {
                            return;
                        }

                        $set('jenis_terima_label', $record->serahTerimaVeneerKering->label_jenis_terima);
                    }),

                TextInput::make('ukuran_label')
                    ->label('Ukuran Kayu')
                    ->disabled()
                    ->visible(fn (Get $get) => $get('palet_select') !== 'AF')
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?ModalJoint $record) {
                        if (! $record?->ukuran) {
                            return;
                        }

                        $set('ukuran_label', "{$record->ukuran->panjang} x {$record->ukuran->lebar} x {$record->ukuran->tebal}");
                    }),

                TextInput::make('jenis_kayu_label')
                    ->label('Jenis Kayu')
                    ->disabled()
                    ->visible(fn (Get $get) => $get('palet_select') !== 'AF')
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?ModalJoint $record) {
                        if (! $record?->jenisKayu) {
                            return;
                        }

                        $set('jenis_kayu_label', $record->jenisKayu->nama_kayu);
                    }),

                TextInput::make('kw')
                    ->label('KW')
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->disabled()
                    ->dehydrated(),

                Textarea::make('keterangan')
                    ->label('Kehilangan/Kelebihan')
                    ->columnSpanFull(),
            ]);
    }

    protected static function getPaletOptions(?ModalJoint $record): array
    {
        $currentId = $record ? (int) $record->id_serah_terima_veneer_kering : null;
        $currentJumlah = (float) ($record?->jumlah ?? 0);

        $options = SerahTerimaVeneerKering::query()
            ->where('diterima_oleh', '!=', '-')
            ->whereIn('tujuan', ['joint']) // ✅ sesuaikan value tujuan untuk joint
            ->with([
                'detailHasil.ukuran', 'detailHasil.jenisKayu',
                'detailBongkarKedi.ukuran', 'detailBongkarKedi.jenisKayu',
                'mutasiKeluarPalet.mutasiKeluar.ukuran',
                'mutasiKeluarPalet.mutasiKeluar.jenisKayu',
                'mutasiKeluarPaletJadi.mutasiKeluar.jenisKayu',
            ])
            ->get()
            ->map(function ($item) use ($currentId, $currentJumlah) {
                $sisa = $item->sisa + ((int) $item->id === $currentId ? $currentJumlah : 0);

                return [$item, $sisa];
            })
            ->filter(fn ($pair) => $pair[1] > 0 || (int) $pair[0]->id === $currentId)
            ->mapWithKeys(function ($pair) {
                [$item, $sisa] = $pair;
                $tampilan = $item->tampilan;

                $label = "Palet {$tampilan['no_palet']} · {$tampilan['dimensi']} {$tampilan['jenis_kayu']} KW{$tampilan['kw']}"
                    ." · ({$item->label_jenis_terima}) · Sisa {$sisa} lbr";

                return [$item->id => $label];
            })
            ->toArray();

        if ($record) {
            return $options;
        }

        return ['AF' => 'Afalan'] + $options;
    }
}
