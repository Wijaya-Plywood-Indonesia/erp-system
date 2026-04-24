<?php

namespace App\Filament\Resources\DetailMasuks\Schemas;

use App\Models\DetailHasilPaletRotary;
use App\Models\DetailMasuk;
use App\Models\DetailMasukStik;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class DetailMasukForm
{
    public static function configure(
        Schema $schema,
        ?int $idProduksi = null,
        string $tipe = 'dryer'
    ): Schema {
        // 1. Tentukan Model dan Foreign Key secara dinamis
        $modelClass = $tipe === 'stik' ? DetailMasukStik::class : DetailMasuk::class;
        $foreignKey = $tipe === 'stik' ? 'id_produksi_stik' : 'id_produksi_dryer';

        return $schema->schema([

            // ✅ Simpan ID Produksi ke kolom yang benar agar tidak "Undefined id_produksi"
            Hidden::make($foreignKey)
                ->default($idProduksi)
                ->required()
                ->dehydrated(true),

            Select::make('no_palet_select')
                ->label('Nomor Palet')
                // ✅ HAPUS afterStateHydrated di sini, pindah ke Hidden no_palet
                ->options(function ($record) {
                    // 🔥 Semua palet dari serah terima
                    $idDiterima = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                        ->whereNotNull('id_detail_hasil_palet_rotary')
                        ->pluck('id_detail_hasil_palet_rotary')
                        ->unique()
                        ->toArray();

                    // 🔥 Ambil palet yang SUDAH DIPAKAI dari kedua tabel
                    $usedPallets = collect()
                        ->merge(
                            DB::table((new DetailMasuk)->getTable())
                                ->whereNotNull('no_palet')
                                ->pluck('no_palet')
                        )
                        ->merge(
                            DB::table((new DetailMasukStik)->getTable())
                                ->whereNotNull('no_palet')
                                ->pluck('no_palet')
                        )
                        ->map(fn($id) => (int) $id)
                        ->toArray();

                    // ✅ Keluarkan palet milik record yang sedang diedit
                    if ($record) {
                        $currentNoPalet = (int) $record->getRawOriginal('no_palet');
                        $usedPallets = array_filter(
                            $usedPallets,
                            fn($id) => $id !== $currentNoPalet
                        );
                    }

                    $palets = DetailHasilPaletRotary::with([
                        'ukuran',
                        'penggunaanLahan.jenisKayu',
                        'produksi.mesin'
                    ])
                        ->whereIn('id', $idDiterima)
                        ->get();

                    $options = [];
                    foreach ($palets as $p) {
                        $isUsed = in_array($p->id, $usedPallets);

                        if ($isUsed) continue;

                        $nomor  = $p->kode_palet;
                        $isi    = $p->total_lembar ?? 0;
                        $ukuran = $p->ukuran?->nama_ukuran ?? 'Ukuran N/A';
                        $kw     = $p->kw ?? '-';
                        $kayu   = $p->penggunaanLahan?->jenisKayu?->nama_kayu ?? 'Kayu Tidak Diketahui';

                        $options[$p->id] = "{$nomor} | {$kayu} | {$ukuran} | KW: {$kw} | Isi: {$isi} lbr";
                    }

                    // ✅ Sembunyikan AF saat edit
                    if ($record) {
                        return $options;
                    }

                    return ['AF' => 'Palet AF'] + $options;
                })
                ->searchable()
                ->required(fn($record) => $record === null) // ✅ Required hanya saat create
                ->live()
                ->disabled(fn($record) => $record !== null)
                ->dehydrated(false)

                // ✅ Validasi backend anti bypass duplicate
                ->rule(function ($record) {
                    return function ($attribute, $value, $fail) use ($record) {
                        if ($value === 'AF') return;

                        // ✅ Saat edit, skip validasi untuk palet yang sedang diedit
                        if ($record && (int) $record->getRawOriginal('no_palet') === (int) $value) {
                            return;
                        }

                        $exists =
                            DB::table((new DetailMasuk)->getTable())
                            ->where('no_palet', $value)
                            ->exists()
                            || DB::table((new DetailMasukStik)->getTable())
                            ->where('no_palet', $value)
                            ->exists();

                        if ($exists) {
                            $fail('Palet sudah digunakan!');
                        }
                    };
                })

                // ✅ SATU afterStateUpdated dengan guard mode edit
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state) use ($modelClass) {

                    // ✅ Guard: jika no_palet sudah ada = mode edit, skip semua
                    $currentNoPalet = $get('no_palet');
                    if ($currentNoPalet !== null && $currentNoPalet !== '' && (int)$currentNoPalet !== 0) {
                        return;
                    }

                    if ($state === 'AF') {
                        $lastAF  = DB::table($modelClass::make()->getTable())
                            ->where('no_palet', '<', 0)
                            ->min('no_palet');
                        $newAFId = $lastAF ? $lastAF - 1 : -1;

                        $set('no_palet', $newAFId);
                        $set('af_generated_id', $newAFId);
                        $set('id_jenis_kayu', null);
                        $set('id_ukuran', null);
                        $set('kw', null);
                        $set('isi', null);
                        return;
                    }

                    if ($state && $state !== 'AF') {
                        $set('no_palet', (int) $state);
                        $palet = DetailHasilPaletRotary::with(['penggunaanLahan', 'ukuran'])->find($state);
                        if ($palet) {
                            $set('kw', $palet->kw);           // ✅ KW dari palet
                            $set('isi', $palet->total_lembar);
                            $set('id_jenis_kayu', $palet->penggunaanLahan?->id_jenis_kayu);
                            $set('id_ukuran', $palet->id_ukuran);
                        }
                    }
                })
                ->columnSpanFull(),

            // ✅ Hidden no_palet: hydrate no_palet DAN no_palet_select dari sini
            Hidden::make('no_palet')
                ->required()
                ->dehydrated(true)
                ->afterStateHydrated(function (Set $set, $state, $record) {
                    if ($record) {
                        $rawNoPalet = $record->getRawOriginal('no_palet');

                        $set('no_palet', $rawNoPalet);

                        if ((int) $rawNoPalet < 0) {
                            $set('no_palet_select', 'AF');
                        } else {
                            $set('no_palet_select', (string) $rawNoPalet);
                        }
                    }
                }),

            // =========================================================
            // PERBAIKAN: Placeholder untuk AF Preview
            // =========================================================
            Placeholder::make('af_preview')
                ->label('Nomor AF yang akan digunakan')
                ->content(function (Get $get) {
                    $noPalet = $get('no_palet');
                    if ($noPalet !== null && (int) $noPalet < 0) {
                        return 'AF-' . abs((int) $noPalet);
                    }
                    return '-';
                })
                ->visible(fn(Get $get) => $get('no_palet_select') === 'AF')
                ->columnSpanFull(),

            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                ->searchable()
                ->disabled(fn(Get $get) => $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null)
                ->dehydrated(true)
                ->required(),

            Select::make('id_ukuran')
                ->label('Ukuran')
                ->options(Ukuran::all()->pluck('nama_ukuran', 'id'))
                ->searchable()
                ->disabled(fn(Get $get) => $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null)
                ->dehydrated(true)
                ->required(),

            TextInput::make('kw')
                ->label('KW (Kualitas)')
                ->required()
                ->readOnly(fn(Get $get) => $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null)
                ->dehydrated(true),

            TextInput::make('isi')
                ->label('Isi')
                ->required()
                ->numeric()
                // ->readOnly(fn(Get $get) => $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null)
                ->dehydrated(true),
        ]);
    }
}