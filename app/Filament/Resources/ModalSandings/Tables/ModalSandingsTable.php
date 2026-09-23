<?php

namespace App\Filament\Resources\ModalSandings\Tables;

use App\Models\ModalSanding;
use App\Models\SerahTerimaHp;
use App\Services\SerahTerimaHpService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModalSandingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'barangSetengahJadi.grade.kategoriBarang',
                'barangSetengahJadi.ukuran',
                'barangSetengahJadi.jenisBarang',
                'serahTerimaHp.triplekMutasiKeluar.jenisKayu',
                'serahTerimaHp.platformMthMutasiKeluar.jenisKayu',
                'serahTerimaHp.triplekMthMutasiKeluar.jenisKayu',
            ]))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No Palet')
                    ->alignCenter()
                    ->numeric()
                    ->sortable(),

                TextColumn::make('barangSetengahJadiInfo')
                    ->label('Barang Setengah Jadi')
                    ->getStateUsing(function ($record) {
                        $serah = $record->serahTerimaHp;

                        // Barang dari Gudang Triplek Jadi tidak punya barangSetengahJadi —
                        // rakit label dari mutasi keluar (jenis kayu / ukuran / grade).
                        if ($serah?->id_triplek_mutasi_keluar !== null) {
                            $m = $serah->triplekMutasiKeluar;
                            if (! $m) {
                                return 'Triplek Jadi — -';
                            }
                            $ukuran = ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0);

                            // Barang di Gudang Triplek Jadi selalu berkategori Plywood
                            // (tidak menyimpan field kategori sendiri), jadi di-hardcode
                            // agar formatnya identik dengan baris hotpress/graji.
                            return "Plywood — {$ukuran} - "
                                .($m->jenisKayu?->nama_kayu ?? '-').' - '
                                .($m->kw_grade ?? '-');
                        }

                        // Barang dari Gudang Platform Mentah — sama seperti Triplek Jadi,
                        // tidak punya barangSetengahJadi, label diambil dari mutasi keluar.
                        if ($serah?->id_platform_mth_mutasi_keluar !== null) {
                            $m = $serah->platformMthMutasiKeluar;
                            if (! $m) {
                                return 'Platform Mentah — -';
                            }
                            $ukuran = ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0);

                            // Barang di Gudang Platform Mentah selalu berkategori Platform
                            // (tidak menyimpan field kategori sendiri), jadi di-hardcode.
                            return "Platform — {$ukuran} - "
                                .($m->jenisKayu?->nama_kayu ?? '-').' - '
                                .($m->kw_grade ?? '-');
                        }

                        // Barang dari Gudang Triplek Mentah — sama pola dengan Platform
                        // Mentah / Triplek Jadi, tidak punya barangSetengahJadi, label
                        // diambil dari mutasi keluar. Kategorinya selalu Plywood.
                        if ($serah?->id_triplek_mth_mutasi_keluar !== null) {
                            $m = $serah->triplekMthMutasiKeluar;
                            if (! $m) {
                                return 'Triplek Mentah — -';
                            }
                            $ukuran = ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0);

                            return "Plywood — {$ukuran} - "
                                .($m->jenisKayu?->nama_kayu ?? '-').' - '
                                .($m->kw_grade ?? '-');
                        }

                        // Sumber lama (hotpress / graji): format asli, tidak diubah.
                        $kategori = $record->barangSetengahJadi?->grade?->kategoriBarang?->nama_kategori ?? '-';
                        $ukuran = $record->barangSetengahJadi?->ukuran?->dimensi ?? '-';
                        $grade = $record->barangSetengahJadi?->grade?->nama_grade ?? '-';
                        $jenis = $record->barangSetengahJadi?->jenisBarang?->nama_jenis_barang ?? '-';

                        return "{$kategori} — {$ukuran} - {$jenis} - {$grade}";
                    }),
                // --- INI BUAT FILTER AJA
                TextColumn::make('barangSetengahJadi.grade.kategoriBarang.nama_kategori')
                    ->label('Kategori')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('barangSetengahJadi.ukuran.dimensi')
                    ->label('Ukuran')
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('barangSetengahJadi.ukuran', function ($q) use ($search) {
                            $q->whereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('barangSetengahJadi.grade.nama_grade')
                    ->label('Grade')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('barangSetengahJadi.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('kuantitas')
                    ->label('Kuantitas')
                    ->suffix(' Lbr')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('jumlah_sanding_face')
                    ->label('Sanding Face (Pass)')
                    ->suffix(' x')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('jumlah_sanding_back')
                    ->label('Sanding Back (Pass)')
                    ->suffix(' x')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                
                // 🌟 Tombol pengembalian sisa bahan Sanding ke gudang. Alurnya
                // sama dengan tombol "Kembalikan ke Gudang" di tab Bahan Hot
                // Press: pilih bahan yang sudah dipakai sebagai modal pada
                // produksi ini, isi jumlahnya, lalu stok gudang bertambah.
                // Asal bahan (Gudang Triplek Jadi, Gudang Platform Mentah,
                // hasil Hotpress, hasil Graji) tidak dibatasi.
                Action::make('kembalikanKeGudang')
                    ->label('Kembalikan ke Gudang')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->modalHeading('Kembalikan Bahan ke Gudang')
                    ->modalDescription(
                        'Sisa bahan yang tidak terpakai pada produksi sanding ini akan dikembalikan sebagai stok di gudang.'
                    )
                    ->modalSubmitActionLabel('Kembalikan')
                    ->hidden(
                        fn ($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    )
                    ->schema(function ($livewire) {

                        $ownerId = $livewire->ownerRecord?->id;

                        return [

                            // =====================================================
                            // FIELD PALET
                            // =====================================================
                            Select::make('id_serah_terima_hp')
                                ->label('Palet Bahan')
                                ->helperText(
                                    'Pilih palet bahan yang akan dikembalikan.'
                                )
                                ->required()
                                ->live()
                                ->searchable()
                                ->options(
                                    fn () => self::opsiPengembalian($ownerId)
                                )
                                ->afterStateUpdated(function ($state, callable $set) {

                                    if (! $state) {

                                        $set('no_palet', null);
                                        $set('maks_pengembalian', null);

                                        return;
                                    }

                                    $serahTerima = SerahTerimaHp::find($state);

                                    // Nomor palet otomatis diambil dari bahan
                                    $set(
                                        'no_palet',
                                        $serahTerima?->no_palet
                                    );

                                    // Sisa bahan yang masih bisa dikembalikan
                                    $set(
                                        'maks_pengembalian',
                                        $serahTerima?->sisa ?? 0
                                    );
                                }),

                            // =====================================================
                            // NOMOR PALET
                            // Otomatis mengikuti palet yang dipilih
                            // =====================================================
                            TextInput::make('no_palet')
                                ->label('Nomor Palet')
                                ->disabled()
                                ->dehydrated(false),

                            // =====================================================
                            // JUMLAH PENGEMBALIAN
                            // =====================================================
                            TextInput::make('jumlah_kembali')
                                ->label('Jumlah Dikembalikan (Lembar)')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->helperText(
                                    fn (Get $get) =>
                                    $get('maks_pengembalian')
                                        ? 'Maks. bisa dikembalikan: '
                                            . $get('maks_pengembalian')
                                            . ' lembar.'
                                        : 'Pilih palet terlebih dahulu.'
                                )
                                ->rules([
                                    fn (Get $get) =>
                                        function (
                                            string $attribute,
                                            $value,
                                            \Closure $fail
                                        ) use ($get) {

                                            $maks = (float) (
                                                $get('maks_pengembalian') ?? 0
                                            );

                                            if ($value > $maks) {
                                                $fail(
                                                    "Jumlah melebihi sisa yang tersedia ({$maks} lembar)."
                                                );
                                            }
                                        },
                                ]),

                            // Menyimpan batas maksimum pengembalian
                            Hidden::make('maks_pengembalian'),
                        ];
                    })
                    ->action(function (array $data, $livewire) {

                        try {

                            $serahTerima = SerahTerimaHp::findOrFail(
                                (int) $data['id_serah_terima_hp']
                            );

                            $jumlah = (float) $data['jumlah_kembali'];

                            app(SerahTerimaHpService::class)->kembaliKeGudang(
                                $serahTerima,
                                $jumlah,
                                $livewire->ownerRecord,
                            );

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
                    ->label('+ Tambah Modal')
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

            ])
            ->recordActions([
                EditAction::make()
                    ->label('')
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
                DeleteAction::make()
                    ->label('')
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }

    /**
     * Opsi dropdown untuk Select 'id_serah_terima_hp' di tombol "Kembalikan
     * ke Gudang" — bahan yang sudah dipakai sebagai modal pada produksi
     * sanding ini (sama seperti Hotpress yang membaca bahan produksinya),
     * asalnya apa saja, dan masih punya sisa > 0.
     */
    protected static function opsiPengembalian(?int $idProduksiSanding): array
    {
        if (! $idProduksiSanding) {
            return [];
        }

        return ModalSanding::query()
            ->where('id_produksi_sanding', $idProduksiSanding)
            ->whereNotNull('id_serah_terima_hp')
            ->with([
                'serahTerimaHp.triplekMutasiKeluar.jenisKayu',
                'serahTerimaHp.platformMthMutasiKeluar.jenisKayu',
            ])
            ->get()
            ->pluck('serahTerimaHp')
            ->filter()
            ->unique('id')
            ->filter(fn ($item) => SerahTerimaHpService::sumberSanding($item) !== null && $item->sisa > 0)
            ->mapWithKeys(fn ($item) => [$item->id => self::labelPengembalian($item)])
            ->toArray();
    }

    /**
     * Label yang ditampilkan di dropdown: asal · ukuran/jenis/grade · sisa.
     */
    protected static function labelPengembalian(SerahTerimaHp $item): string
    {
        $sisa = rtrim(rtrim(number_format($item->sisa, 2, '.', ''), '0'), '.');

        $mutasi = $item->triplekMutasiKeluar ?? $item->platformMthMutasiKeluar;

        if ($mutasi) {
            $detail = ($mutasi->panjang + 0).' x '.($mutasi->lebar + 0).' x '.($mutasi->tebal + 0)
                .' '.($mutasi->jenisKayu?->nama_kayu ?? '?').' '.($mutasi->kw_grade ?? '?');
        } else {
            $detail = $item->barangSetengahJadi?->label ?? '-';
        }

        return "{$item->asal_label} · {$detail} · Sisa {$sisa} Lbr";
    }
}
