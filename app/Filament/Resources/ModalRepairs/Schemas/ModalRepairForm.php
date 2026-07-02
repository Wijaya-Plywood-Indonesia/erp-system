<?php

namespace App\Filament\Resources\ModalRepairs\Schemas;

use App\Models\ModalRepair;
use App\Models\SerahTerimaVeneerKering;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ModalRepairForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Hidden::make('id_produksi_repair')
                    ->default(fn ($livewire) => $livewire->getOwnerRecord()?->id),

                Hidden::make('id_ukuran'),
                Hidden::make('id_jenis_kayu'),

                Select::make('id_serah_terima_veneer_kering')
                    ->label('Pilih Palet (Veneer)')
                    ->options(fn (?ModalRepair $record) => self::getPaletOptions($record))
                    ->searchable()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, $set, ?ModalRepair $record) {
                        if (! $state) {
                            $set('id_ukuran', null);
                            $set('id_jenis_kayu', null);
                            $set('kw', null);
                            $set('nomor_palet', null);
                            $set('ukuran_label', null);
                            $set('jenis_kayu_label', null);
                            $set('jenis_terima_label', null);
                            $set('sisa_tersedia', null);
                            $set('jumlah', null);

                            return;
                        }

                        $serahTerima = SerahTerimaVeneerKering::with([
                            'detailHasil.ukuran', 'detailHasil.jenisKayu',
                            'detailBongkarKedi.ukuran', 'detailBongkarKedi.jenisKayu',
                        ])->find($state);

                        $sumber = $serahTerima?->sumber;
                        $ukuran = $sumber?->ukuran;

                        $sisa = $serahTerima?->sisa ?? 0;
                        if ($record && $record->id_serah_terima_veneer_kering === (int) $state) {
                            $sisa += (float) $record->jumlah;
                        }

                        $set('id_ukuran', $sumber?->id_ukuran);
                        $set('id_jenis_kayu', $sumber?->id_jenis_kayu);
                        $set('kw', $sumber?->kw);
                        $set('nomor_palet', $sumber?->no_palet);
                        $set('ukuran_label', $ukuran
                            ? "{$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}"
                            : '-');
                        $set('jenis_kayu_label', $sumber?->jenisKayu?->nama_kayu ?? '-');
                        $set('jenis_terima_label', $serahTerima?->label_jenis_terima ?? '-');
                        $set('sisa_tersedia', $sisa);
                        $set('jumlah', $sisa);
                    }),

                TextInput::make('sisa_tersedia')
                    ->label('Sisa Tersedia (Lembar)')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?ModalRepair $record) {
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
                        fn (Get $get, ?ModalRepair $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $idSerahTerima = $get('id_serah_terima_veneer_kering');

                            if (! $idSerahTerima) {
                                return;
                            }

                            $serahTerima = SerahTerimaVeneerKering::find($idSerahTerima);

                            if (! $serahTerima) {
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
                        if (! $record?->serahTerimaVeneerKering) {
                            return;
                        }

                        $set('jenis_terima_label', $record->serahTerimaVeneerKering->label_jenis_terima);
                    }),

                TextInput::make('ukuran_label')
                    ->label('Ukuran Kayu')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?ModalRepair $record) {
                        if (! $record?->ukuran) {
                            return;
                        }

                        $set('ukuran_label', "{$record->ukuran->panjang} x {$record->ukuran->lebar} x {$record->ukuran->tebal}");
                    }),

                TextInput::make('jenis_kayu_label')
                    ->label('Jenis Kayu')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?ModalRepair $record) {
                        if (! $record?->jenisKayu) {
                            return;
                        }

                        $set('jenis_kayu_label', $record->jenisKayu->nama_kayu);
                    }),

                TextInput::make('kw')
                    ->label('KW')
                    ->disabled()
                    ->dehydrated(),

                TextInput::make('nomor_palet')
                    ->label('Nomor Palet (Sumber)')
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

        return SerahTerimaVeneerKering::query()
            ->where('diterima_oleh', '!=', '-')
            ->with([
                'detailHasil.ukuran', 'detailHasil.jenisKayu',
                'detailBongkarKedi.ukuran', 'detailBongkarKedi.jenisKayu',
            ])
            ->get()
            ->map(function ($item) use ($currentId, $currentJumlah) {
                $sisa = $item->sisa + ($item->id === $currentId ? $currentJumlah : 0);

                return [$item, $sisa];
            })
            ->filter(fn ($pair) => $pair[1] > 0)
            ->mapWithKeys(function ($pair) {
                [$item, $sisa] = $pair;
                $sumber = $item->sumber;
                $jenisLabel = $item->label_jenis_terima;
                // Bagian sumber (Press Dryer/Kedi) tidak ditampilkan lagi
                $label = "Palet {$sumber?->no_palet} - ({$jenisLabel}) - Sisa {$sisa} lembar";

                return [$item->id => $label];
            })
            ->toArray();
    }
}
