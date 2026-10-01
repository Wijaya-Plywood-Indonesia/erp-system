<?php

namespace App\Filament\Resources\BahanHotPresses\Tables;

use App\Models\BahanHotpress;
use App\Models\PlatformJadiMutasiKeluarPalet;
use App\Models\TriplekJadiMutasiKeluarPalet;
use App\Models\VeneerJadiMutasiKeluarPalet;
use App\Services\StokPlatformJadiService;
use App\Services\StokTriplekJadiService;
use App\Services\StokVeneerJadiService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Utilities\Get;

class BahanHotPressesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with([
                'mutasiKeluarPalet.mutasiKeluar.jenisKayu',
                'mutasiKeluarPlatform.mutasiKeluar.jenisBarang',
                'mutasiKeluarTriplek.mutasiKeluar.jenisKayu',
            ]))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('jenis')
                    ->label('Jenis Barang')
                    ->state(function ($record) {
                        $sumber = $record->sumber
                            ?? ($record->id_mutasi_keluar_palet ? 'veneer' : ($record->id_mutasi_keluar_platform ? 'platform' : null));

                        if ($sumber === 'veneer' && $record->mutasiKeluarPalet?->mutasiKeluar) {
                            return 'Veneer';
                        }

                        if ($sumber === 'platform' && $record->mutasiKeluarPlatform?->mutasiKeluar) {
                            return 'Platform';
                        }

                        if ($sumber === 'triplek') {
                            return 'Triplek';
                        }

                        return '-';
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Veneer'   => 'success',
                        'Platform' => 'info',
                        'Triplek'  => 'primary',
                        default    => 'gray',
                    }),

                TextColumn::make('jenis_kayu')
                    ->label('Jenis Barang')
                    ->state(function ($record) {
                        $sumber = $record->sumber
                            ?? ($record->id_mutasi_keluar_palet ? 'veneer' : ($record->id_mutasi_keluar_platform ? 'platform' : null));

                        if ($sumber === 'veneer') {
                            return $record->mutasiKeluarPalet?->mutasiKeluar?->jenisKayu?->nama_kayu ?? '-';
                        }

                        if ($sumber === 'platform') {
                            return $record->mutasiKeluarPlatform?->mutasiKeluar?->jenisBarang?->nama_jenis_barang ?? '-';
                        }

                        if ($sumber === 'triplek') {
                            return $record->mutasiKeluarTriplek?->mutasiKeluar?->jenisKayu?->nama_kayu ?? '-';
                        }

                        return '-';
                    }),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(function ($record) {
                        $sumber = $record->sumber
                            ?? ($record->id_mutasi_keluar_palet ? 'veneer' : ($record->id_mutasi_keluar_platform ? 'platform' : null));

                        if ($sumber === 'veneer') {
                            return $record->mutasiKeluarPalet?->mutasiKeluar?->kw_grade ?? '-';
                        }

                        if ($sumber === 'platform') {
                            return $record->mutasiKeluarPlatform?->mutasiKeluar?->kw_grade ?? '-';
                        }

                        if ($sumber === 'triplek') {
                            return $record->mutasiKeluarTriplek?->mutasiKeluar?->kw_grade ?? '-';
                        }

                        return '-';
                    }),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(function ($record) {
                        $sumber = $record->sumber
                            ?? ($record->id_mutasi_keluar_palet ? 'veneer' : ($record->id_mutasi_keluar_platform ? 'platform' : null));

                        $mk = match ($sumber) {
                            'veneer'   => $record->mutasiKeluarPalet?->mutasiKeluar,
                            'platform' => $record->mutasiKeluarPlatform?->mutasiKeluar,
                            'triplek'  => $record->mutasiKeluarTriplek?->mutasiKeluar,
                            default    => null,
                        };

                        if (! $mk) {
                            return '-';
                        }

                        $panjang = (float) $mk->panjang + 0;
                        $lebar   = (float) $mk->lebar + 0;
                        $tebal   = (float) $mk->tebal + 0;

                        return "{$panjang} x {$lebar} x {$tebal}";
                    }),

                TextColumn::make('isi')
                    ->label('Jumlah Lembar'),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->wrap()
                    ->limit(50)
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([

                // 🌟 Tombol pengembalian sisa bahan hotpress (veneer, platform,
                // atau triplek) ke gudang. Formnya sengaja disederhanakan dari
                // form "Create Bahan Hot Press": tidak ada filter grade/jenis
                // barang, dan nomor palet otomatis terisi (tidak diketik
                // manual) karena palet sudah ditentukan lewat pilihan di atas.
                //
                // Pilihan palet memakai key gabungan "sumber:id" (sama seperti
                // di BahanHotPressForm) supaya satu Select bisa menampung
                // ketiga sumber sekaligus tanpa saling bentrok ID-nya.
                Action::make('kembalikanKeGudang')
                    ->label('Kembalikan ke Gudang')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->modalHeading('Kembalikan Bahan ke Gudang')
                    ->modalDescription('Sisa bahan (veneer, platform, atau triplek) yang tidak terpakai pada produksi ini akan dikembalikan sebagai stok di gudang.')
                    ->modalSubmitActionLabel('Kembalikan')
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    )
                    ->schema(function ($livewire) {
                        $ownerId = $livewire->ownerRecord?->id;

                        return [
                            Select::make('sumber_palet_selector')
                                ->label('Palet Bahan')
                                ->helperText('Hanya menampilkan palet (veneer/platform/triplek) yang sudah diambil sebagai bahan pada produksi ini dan masih menyisakan jumlah yang belum dikembalikan.')
                                ->required()
                                ->live()
                                ->options(function () use ($ownerId) {
                                    if (! $ownerId) {
                                        return [];
                                    }

                                    return BahanHotpress::query()
                                        ->where('id_produksi_hp', $ownerId)
                                        ->whereNotNull('sumber')
                                        ->with([
                                            'mutasiKeluarPalet.mutasiKeluar.jenisKayu',
                                            'mutasiKeluarPlatform.mutasiKeluar.jenisBarang',
                                            'mutasiKeluarTriplek.mutasiKeluar.jenisKayu',
                                        ])
                                        ->get()
                                        ->unique(fn($b) => $b->sumber . ':' . self::idPaletDariBahan($b))
                                        ->mapWithKeys(function ($bahan) {
                                            $sumber = $bahan->sumber;
                                            $palet = self::paletDariBahan($bahan);

                                            if (! $palet || $palet->sisa <= 0) {
                                                return [];
                                            }

                                            $label = self::labelPalet($bahan, $sumber, $palet->sisa);

                                            return ["{$sumber}:{$palet->id}" => $label];
                                        })
                                        ->toArray();
                                })
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if (! $state || ! str_contains($state, ':')) {
                                        $set('no_palet', null);
                                        $set('sumber', null);
                                        $set('maks_pengembalian', null);

                                        return;
                                    }

                                    [$sumber, $paletId] = explode(':', $state, 2);
                                    $palet = self::muatPalet($sumber, (int) $paletId);

                                    $set('no_palet', $palet?->nomor_palet);
                                    $set('sumber', $sumber);
                                    $set('maks_pengembalian', $palet?->sisa ?? 0);
                                }),

                            // Nomor palet & sumber disamakan otomatis dari hasil
                            // pilihan di atas — tidak perlu diketik ulang manual.
                            Hidden::make('sumber'),

                            TextInput::make('no_palet')
                                ->label('Nomor Palet')
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('jumlah_kembali')
                                ->label('Jumlah Dikembalikan (Lembar)')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->helperText(
                                    fn(Get $get) =>
                                    $get('maks_pengembalian')
                                        ? 'Maks. bisa dikembalikan: '.$get('maks_pengembalian').' lembar.'
                                        : 'Pilih palet terlebih dahulu.'
                                )
                                ->rules([
                                    fn(Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $maks = (float) ($get('maks_pengembalian') ?? 0);

                                        if ($value > $maks) {
                                            $fail("Jumlah melebihi sisa yang tersedia di palet ini ({$maks} lembar).");
                                        }
                                    },
                                ]),

                            Hidden::make('maks_pengembalian'),
                        ];
                    })
                    ->action(function (array $data, $livewire) {
                        $sumber = $data['sumber'] ?? null;
                        $rawSelector = $data['sumber_palet_selector'] ?? '';
                        $paletId = str_contains($rawSelector, ':')
                            ? (int) explode(':', $rawSelector, 2)[1]
                            : 0;

                        if (! $sumber || ! $paletId) {
                            Notification::make()
                                ->title('Gagal Mengembalikan')
                                ->body('Palet belum dipilih.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $jumlah = (float) $data['jumlah_kembali'];
                            $produksiHp = $livewire->ownerRecord;

                            match ($sumber) {
                                'veneer' => app(StokVeneerJadiService::class)->kembaliDariHotpress(
                                    VeneerJadiMutasiKeluarPalet::findOrFail($paletId),
                                    $jumlah,
                                    $produksiHp,
                                ),
                                'platform' => app(StokPlatformJadiService::class)->kembaliDariHotpress(
                                    PlatformJadiMutasiKeluarPalet::findOrFail($paletId),
                                    $jumlah,
                                    $produksiHp,
                                ),
                                'triplek' => app(StokTriplekJadiService::class)->kembaliDariHotpress(
                                    TriplekJadiMutasiKeluarPalet::findOrFail($paletId),
                                    $jumlah,
                                    $produksiHp,
                                ),
                                default => throw new \RuntimeException("Sumber tidak dikenal: {$sumber}"),
                            };

                            Notification::make()
                                ->title('Bahan berhasil dikembalikan ke gudang')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal Mengembalikan')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                
            ])
            ->recordActions([
                Action::make('ket')
                    ->label('Keterangan')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->form([
                        Textarea::make('ket')
                            ->label('Keterangan')
                            ->rows(3)
                            ->default(fn($record) => $record->keterangan),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'ket' => $data['ket'],
                        ]);

                        Notification::make()
                            ->title('Keterangan berhasil disimpan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn($record) => "Keterangan ")
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg'),
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }

    /**
     * Ambil id palet dari sebuah baris BahanHotpress, sesuai sumbernya.
     */
    protected static function idPaletDariBahan(BahanHotpress $bahan): ?int
    {
        return match ($bahan->sumber) {
            'veneer'   => $bahan->id_mutasi_keluar_palet,
            'platform' => $bahan->id_mutasi_keluar_platform,
            'triplek'  => $bahan->id_mutasi_keluar_triplek,
            default    => null,
        };
    }

    /**
     * Ambil MODEL PALET (bukan cuma id) dari sebuah baris BahanHotpress,
     * sesuai sumbernya. Dipakai supaya bisa langsung baca `->sisa`
     * (VeneerJadiMutasiKeluarPalet / PlatformJadiMutasiKeluarPalet /
     * TriplekJadiMutasiKeluarPalet — ketiganya punya accessor `sisa` yang
     * sudah memperhitungkan jumlah_dikembalikan).
     */
    protected static function paletDariBahan(BahanHotpress $bahan): VeneerJadiMutasiKeluarPalet|PlatformJadiMutasiKeluarPalet|TriplekJadiMutasiKeluarPalet|null
    {
        return match ($bahan->sumber) {
            'veneer'   => $bahan->mutasiKeluarPalet,
            'platform' => $bahan->mutasiKeluarPlatform,
            'triplek'  => $bahan->mutasiKeluarTriplek,
            default    => null,
        };
    }

    /**
     * Muat ulang model palet dari sumber + id (dipakai di afterStateUpdated,
     * setelah Select cuma mengirim "sumber:id" sebagai string).
     */
    protected static function muatPalet(string $sumber, int $paletId): VeneerJadiMutasiKeluarPalet|PlatformJadiMutasiKeluarPalet|TriplekJadiMutasiKeluarPalet|null
    {
        return match ($sumber) {
            'veneer'   => VeneerJadiMutasiKeluarPalet::find($paletId),
            'platform' => PlatformJadiMutasiKeluarPalet::find($paletId),
            'triplek'  => TriplekJadiMutasiKeluarPalet::find($paletId),
            default    => null,
        };
    }

    /**
     * Label yang ditampilkan di dropdown pilihan palet, sesuai sumbernya.
     */
    protected static function labelPalet(BahanHotpress $bahan, string $sumber, float $sisa): string
    {
        if ($sumber === 'veneer') {
            $palet = $bahan->mutasiKeluarPalet;
            $mk = $palet?->mutasiKeluar;
            $kayu = $mk?->jenisKayu?->nama_kayu ?? '?';
            $kw = $mk?->kw_grade ?? '?';
            $noPalet = $palet?->nomor_palet ?? '?';

            return "Veneer | Palet {$noPalet} | {$kayu} | {$kw} | Sisa Bisa Dikembalikan {$sisa} Lbr";
        }

        if ($sumber === 'platform') {
            $palet = $bahan->mutasiKeluarPlatform;
            $mk = $palet?->mutasiKeluar;
            $jenisBarang = $mk?->jenisBarang?->nama_jenis_barang ?? '?';
            $kw = $mk?->kw_grade ?? '?';
            $noPalet = $palet?->nomor_palet ?? '?';

            return "Platform | Palet {$noPalet} | {$jenisBarang} | {$kw} | Sisa Bisa Dikembalikan {$sisa} Lbr";
        }

        // triplek
        $palet = $bahan->mutasiKeluarTriplek;
        $mk = $palet?->mutasiKeluar;
        $kayu = $mk?->jenisKayu?->nama_kayu ?? '?';
        $kw = $mk?->kw_grade ?? '?';
        $noPalet = $palet?->nomor_palet ?? '?';

        return "Triplek | Palet {$noPalet} | {$kayu} | {$kw} | Sisa Bisa Dikembalikan {$sisa} Lbr";
    }
}
