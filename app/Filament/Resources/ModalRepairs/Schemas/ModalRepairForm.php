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

                Select::make('palet_select')
                    ->label('Pilih Palet (Veneer)')
                    ->options(fn(?ModalRepair $record) => self::getPaletOptions($record))
                    ->searchable()
                    ->live()
                    ->required(fn ($record) => $record === null)
                    ->disabled(fn ($record) => $record !== null)
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
                            $newAfNumber = DB::table((new ModalRepair)->getTable())->count() + 1;

                            $set('id_serah_terima_veneer_kering', null);
                            $set('af_generated_id', $newAfNumber);
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

                        $serahTerima = SerahTerimaVeneerKering::with([
                            'detailHasil.ukuran',
                            'detailHasil.jenisKayu',
                            'detailBongkarKedi.ukuran',
                            'detailBongkarKedi.jenisKayu',
                        ])->find($state);

                        $sumber = $serahTerima?->sumber;
                        $sisa = $serahTerima?->sisa ?? 0;
                        
                        if ($record && (int) $record->id_serah_terima_veneer_kering === (int) $state) {
                            $sisa += (float) $record->jumlah;
                        }

                        $newPaletNumber = DB::table((new ModalRepair)->getTable())->count() + 1;
                        if ($record && $record->nomor_palet) {
                            $newPaletNumber = $record->nomor_palet;
                        }

                        // Definisikan relasi ukuran dari sumber untuk label
                        $ukuran = $sumber?->ukuran;

                        $set('id_serah_terima_veneer_kering', (int) $state);
                        $set('id_ukuran', $sumber?->id_ukuran);
                        $set('id_jenis_kayu', $sumber?->id_jenis_kayu);
                        $set('kw', $sumber?->kw);
                        $set('nomor_palet', $newPaletNumber);
                        $set('ukuran_label', $ukuran 
                            ? "{$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}" 
                            : '-');
                        $set('jenis_kayu_label', $sumber?->jenisKayu?->nama_kayu ?? '-');
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
                    ->visible(fn(Get $get) => $get('palet_select') === 'AF')
                    ->required(fn(Get $get) => $get('palet_select') === 'AF')
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Set $set, ?ModalRepair $record) {
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

                            if (!$idSerahTerima) {
                                return;
                            }

                            $serahTerima = SerahTerimaVeneerKering::find($idSerahTerima);
                            if (!$serahTerima) {
                                return;
                            }

                            $sisa = (float) $serahTerima->sisa;

                            // ✅ FIX: Perbaikan struktur penutupan logika penambahan sisa toleransi
                            if ($record && (int) $record->id_serah_terima_veneer_kering === (int) $idSerahTerima) {
                                $sisa += (float) $record->jumlah;
                            }

                            if ((float)$value > $sisa) {
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
        $currentId = $record ? (int) $record->id_serah_terima_veneer_kering : null;
        $currentJumlah = (float) ($record?->jumlah ?? 0);

        $options = SerahTerimaVeneerKering::query()
            ->where('diterima_oleh', '!=', '-')
            ->with([
                'detailHasil.ukuran',
                'detailHasil.jenisKayu',
                'detailBongkarKedi.ukuran',
                'detailBongkarKedi.jenisKayu',
            ])
            ->get()
            ->map(function ($item) use ($currentId, $currentJumlah) {
                $sisa = $item->sisa + ((int) $item->id === $currentId ? $currentJumlah : 0);
                return [$item, $sisa];
            })
            ->filter(fn ($pair) => $pair[1] > 0 || (int) $pair[0]->id === $currentId)
            ->mapWithKeys(function ($pair) {
                [$item, $sisa] = $pair;
                $sumber = $item->sumber;

                $ukuran = $sumber?->ukuran;
                $dimensi = $ukuran
                    ? collect([$ukuran->panjang, $ukuran->lebar, $ukuran->tebal])
                        ->map(fn($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'))
                        ->implode('x')
                    : '-';

                $kayu = $sumber?->jenisKayu?->nama_kayu ?? '-';
                $kw = $sumber?->kw ?? '-';

                $label = "Palet {$sumber?->no_palet} · {$dimensi} {$kayu} KW{$kw} · ({$item->label_jenis_terima}) · Sisa {$sisa} lbr";

                return [$item->id => $label];
            })
            ->toArray();

        if ($record) {
            return $options;
        }

        return ['AF' => 'Afalan'] + $options;
    }
}
