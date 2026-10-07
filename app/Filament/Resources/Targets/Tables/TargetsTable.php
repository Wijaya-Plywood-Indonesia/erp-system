<?php

namespace App\Filament\Resources\Targets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TargetsTable
{
    /**
     * Kata pemisah/pelengkap yang sering diketik user saat mencari ukuran
     * atau KW (misal "122 x 244 x 9 mm" atau "kw 1"). Kata-kata ini tidak
     * punya arti sendiri, jadi tidak boleh membuat hasil pencarian kosong.
     */
    private const KATA_UKURAN = ['x', '×', '*', '-', 'mm', 'cm'];
    private const KATA_KW = ['kw', 'grade'];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Search')
            ->searchDebounce('500ms')
            ->columns([
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->searchable()
                    ->color(fn(string $state): string => match ($state) {
                        'diajukan' => 'danger',
                        'disetujui' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'diajukan' => 'heroicon-o-x-circle',
                        'disetujui' => 'heroicon-o-check-circle',
                        default => 'heroicon-o-minus-circle',
                    }),

                // Cari di nama mesin (abaikan spasi: "hotpress1" = "HOTPRESS 1")
                // dan di kode target (misal HOTPRESS1222449sPlatform).
                TextColumn::make('mesin.nama_mesin')
                    ->label('Mesin')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $like = self::like($search);
                        $tanpaSpasi = self::like(str_replace(' ', '', $search));

                        return $query
                            ->whereHas('mesin', function (Builder $q) use ($like, $tanpaSpasi) {
                                $q->where('nama_mesin', 'like', $like)
                                    ->orWhereRaw("REPLACE(nama_mesin, ' ', '') LIKE ?", [$tanpaSpasi]);
                            })
                            ->orWhere('kode_ukuran', 'like', $like);
                    }),

                // Cari ukuran dengan format bebas: "122x244x9", "122 x 244 x 9",
                // "244 122", atau cukup "9". Urutan angka tidak berpengaruh.
                TextColumn::make('ukuranModel.dimensi')
                    ->label('Ukuran')
                    ->numeric()
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        if (self::kataPelengkap($search, self::KATA_UKURAN)) {
                            return $query->whereRaw('1 = 1');
                        }

                        $angka = self::ambilAngka($search);

                        if ($angka === []) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query->whereHas('ukuranModel', function (Builder $q) use ($angka) {
                            foreach ($angka as $nilai) {
                                $q->where(function (Builder $w) use ($nilai) {
                                    // 1-2 digit (tebal): harus sama persis.
                                    // 3 digit ke atas (panjang/lebar): boleh diketik sebagian.
                                    $boleh_awalan = strlen(preg_replace('/\D/', '', $nilai)) >= 3;

                                    foreach (['panjang', 'lebar', 'tebal'] as $kolom) {
                                        $w->orWhere($kolom, $nilai);

                                        if ($boleh_awalan) {
                                            $w->orWhere($kolom, 'like', $nilai . '%');
                                        }
                                    }
                                });
                            }
                        });
                    }),

                TextColumn::make('shift')
                    ->label('Shift')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('grade')
                    ->label('KW')
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        if (self::kataPelengkap($search, self::KATA_KW)) {
                            return $query->whereRaw('1 = 1');
                        }

                        return $query->where('grade', 'like', self::like($search));
                    })
                    ->sortable(),

                // Cari nama kayu ("sengon") atau kode kayu persis ("s", "m").
                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('jenisKayu', function (Builder $q) use ($search) {
                            $q->where('nama_kayu', 'like', self::like($search))
                                ->orWhere('kode_kayu', $search);
                        });
                    }),

                TextColumn::make('kategoriBarang.nama_kategori')
                    ->label('Jenis Barang')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('target')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('orang')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('jam')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('targetperjam')
                    ->label('Tgt/Jam')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('targetperorang')
                    ->label('Tgt/Org')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('gaji')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('potongan')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('id_mesin')
                    ->label('Mesin')
                    ->relationship('mesin', 'nama_mesin')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->relationship('jenisKayu', 'nama_kayu')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('id_kategori_barang')
                    ->label('Jenis Barang')
                    ->relationship('kategoriBarang', 'nama_kategori')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'diajukan' => 'Diajukan',
                        'disetujui' => 'Disetujui',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Escape karakter khusus LIKE (% dan _) supaya diketik apa adanya,
     * lalu bungkus dengan wildcard di kiri-kanan.
     */
    private static function like(string $search): string
    {
        return '%' . addcslashes(trim($search), '\\%_') . '%';
    }

    /**
     * True jika kata pencarian hanya kata pelengkap (mis. "x", "mm", "kw").
     */
    private static function kataPelengkap(string $search, array $daftar): bool
    {
        return in_array(mb_strtolower(trim($search)), $daftar, true);
    }

    /**
     * Ambil angka dari kata pencarian ukuran. Hanya diproses jika kata
     * terdiri dari angka dan pemisah ukuran (122x244x9, 9mm, 9,5),
     * sehingga kata biasa seperti "hotpress1" tidak ikut terbaca sebagai ukuran.
     *
     * @return string[] angka dengan titik desimal, mis. ['122', '244', '9.5']
     */
    private static function ambilAngka(string $search): array
    {
        $kata = mb_strtolower(trim($search));

        if (! preg_match('/^[\d.,x×*\-]+(mm|cm)?$/u', $kata)) {
            return [];
        }

        preg_match_all('/\d+(?:[.,]\d+)?/', $kata, $cocok);

        return array_map(fn($n) => str_replace(',', '.', $n), $cocok[0]);
    }
}