<?php

namespace App\Filament\Resources\DetailNotaBarangKeluars\Tables;

use App\Models\DetailNotaBarangKeluar;
use App\Models\DetailNotaBarangMasuk;
use App\Models\Grade;
use App\Models\HppVeneerBasahSummary;
use App\Models\JenisKayu;
use App\Models\NotaBarangKeluar;
use App\Models\PlywoodMutasi;
use App\Models\PlywoodMutasiDetail;
use App\Models\StokPlywoodSiapJual;
use App\Models\StokVeneerJadi;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use App\Services\PlywoodMutasiService;
use App\Services\VeneerMutasiService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class DetailNotaBarangKeluarsTable
{
    /**
     * Key dimensi yang konsisten: tahan beda tipe data (string "122.00" vs
     * float 122) dan tahan beda urutan panjang/lebar antar tabel.
     */
    protected static function dimKey($panjang, $lebar, $tebal): string
    {
        $sisi = [(float) $panjang, (float) $lebar];
        sort($sisi);

        return implode('|', [...$sisi, (float) $tebal]);
    }

    /**
     * Label ukuran langsung dari dimensi stok, tanpa butuh master ukurans.
     */
    protected static function labelUkuran($panjang, $lebar, $tebal): string
    {
        return ((float) $panjang).' cm x '.((float) $lebar).' cm x '.((float) $tebal).' mm';
    }

    /**
     * Semua baris stok plywood yang masih tersedia. Sumber tunggal form KELUAR.
     */
    protected static function stokTersedia()
    {
        return StokPlywoodSiapJual::where('stok_lembar', '>', 0)
            ->orderBy('tebal')
            ->get();
    }

    /**
     * Jumlah lembar untuk kombinasi terpilih. null = pilihan belum lengkap.
     */
    protected static function cariStok($ukuranKey, $idJenisKayu, $kw): ?int
    {
        if (! $ukuranKey || ! $idJenisKayu || ! $kw) {
            return null;
        }

        $stok = static::stokTersedia()->first(
            fn ($s) => static::dimKey($s->panjang, $s->lebar, $s->tebal) === $ukuranKey
                && $s->id_jenis_kayu == $idJenisKayu
                && $s->kw_grade === $kw
        );

        return $stok ? (int) $stok->stok_lembar : 0;
    }

    /**
     * Terjemahkan pilihan dimensi (ukuran_key) ke baris master `ukurans`,
     * karena plywood_mutasi_details butuh id_ukuran. Dicari dua arah;
     * kalau belum ada, dibuatkan mengikuti konvensi master (sisi panjang dulu).
     */
    protected static function resolveUkuran(string $ukuranKey): Ukuran
    {
        [$a, $b, $tebal] = array_map('floatval', explode('|', $ukuranKey));

        $ukuran = Ukuran::where('tebal', $tebal)
            ->where(function ($q) use ($a, $b) {
                $q->where(fn ($s) => $s->where('panjang', $a)->where('lebar', $b))
                    ->orWhere(fn ($s) => $s->where('panjang', $b)->where('lebar', $a));
            })
            ->first();

        if ($ukuran) {
            return $ukuran;
        }

        $sisi = [$a, $b];
        rsort($sisi);

        return Ukuran::create([
            'panjang' => $sisi[0],
            'lebar' => $sisi[1],
            'tebal' => $tebal,
        ]);
    }

    /**
     * Form plywood untuk NOTA KELUAR — seluruh pilihan bersumber dari
     * stok_plywood_siap_jual, jadi hanya barang yang benar-benar ada
     * yang bisa dipilih.
     */
    protected static function plywoodFormSchema(): array
    {
        return [
            Select::make('ukuran_key')
                ->label('Ukuran')
                ->options(fn () => static::stokTersedia()
                    ->mapWithKeys(fn ($s) => [
                        static::dimKey($s->panjang, $s->lebar, $s->tebal)
                            => static::labelUkuran($s->panjang, $s->lebar, $s->tebal),
                    ])
                    ->all()
                )
                ->placeholder('Pilih ukuran yang ada stoknya')
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (callable $set) {
                    $set('id_jenis_kayu', null);
                    $set('kw_grade', null);
                }),

            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(function (callable $get) {
                    $key = $get('ukuran_key');
                    if (! $key) {
                        return [];
                    }

                    $ids = static::stokTersedia()
                        ->filter(fn ($s) => static::dimKey($s->panjang, $s->lebar, $s->tebal) === $key)
                        ->pluck('id_jenis_kayu')
                        ->unique();

                    return JenisKayu::whereIn('id', $ids)->pluck('nama_kayu', 'id');
                })
                ->placeholder('Pilih ukuran dulu')
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('kw_grade', null)),

            Select::make('kw_grade')
                ->label('KW / Grade')
                ->options(function (callable $get) {
                    $key = $get('ukuran_key');
                    $idJenisKayu = $get('id_jenis_kayu');

                    if (! $key || ! $idJenisKayu) {
                        return [];
                    }

                    return static::stokTersedia()
                        ->filter(fn ($s) => static::dimKey($s->panjang, $s->lebar, $s->tebal) === $key
                            && $s->id_jenis_kayu == $idJenisKayu)
                        ->mapWithKeys(fn ($s) => [
                            $s->kw_grade => $s->kw_grade
                                .' ('.number_format((int) $s->stok_lembar).' lbr)',
                        ])
                        ->all();
                })
                ->placeholder('Pilih jenis kayu dulu')
                ->searchable()
                ->required()
                ->live(),

            Placeholder::make('stok_saat_ini')
                ->label('Stok Saat Ini')
                ->content(function (callable $get) {
                    $lembar = static::cariStok($get('ukuran_key'), $get('id_jenis_kayu'), $get('kw_grade'));

                    if ($lembar === null) {
                        return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
                    }

                    if ($lembar <= 0) {
                        return new HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">0 Lembar (Stok Habis)</strong>');
                    }

                    return new HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">'.number_format($lembar).' Lembar</strong>');
                }),

            TextInput::make('jumlah')
                ->label('Jumlah (Lembar)')
                ->numeric()
                ->minValue(1)
                ->maxValue(fn (callable $get) => static::cariStok(
                    $get('ukuran_key'), $get('id_jenis_kayu'), $get('kw_grade')
                ) ?: null)
                ->helperText('Tidak boleh melebihi stok yang tersedia.')
                ->required(),

            Textarea::make('keterangan')
                ->label('Keterangan')
                ->rows(3)
                ->required(),
        ];
    }

    /**
     * Cari baris plywood_mutasi_details yang cocok dengan baris detail nota.
     */
    protected static function findPlywoodDetail($record): ?PlywoodMutasiDetail
    {
        $nota = $record->nota;

        if (! $nota || ! $nota->plywoodMutasi) {
            return null;
        }

        $details = $nota->plywoodMutasi->details()->with(['ukuran', 'jenisKayu'])->get();

        foreach ($details as $detail) {
            $ukuran = $detail->ukuran;
            $jenisKayu = $detail->jenisKayu;

            if (! $ukuran || ! $jenisKayu) {
                continue;
            }

            $expectedName = 'Plywood - '.$ukuran->nama_ukuran
                .' - '.$jenisKayu->nama_kayu
                .' - KW '.$detail->kw_grade;

            if ($expectedName === $record->nama_barang && (int) $detail->qty === (int) $record->jumlah) {
                return $detail;
            }
        }

        return null;
    }

    /**
     * Cari baris veneer_mutasi_details yang cocok dengan baris detail nota.
     */
    protected static function findVeneerDetail($record): ?VeneerMutasiDetail
    {
        $nota = $record->nota;

        if (! $nota || ! $nota->mutasi) {
            return null;
        }

        $details = $nota->mutasi->details()->with(['ukuran', 'jenisKayu'])->get();

        foreach ($details as $detail) {
            $ukuran = $detail->ukuran;
            $jenisKayu = $detail->jenisKayu;

            if (! $ukuran || ! $jenisKayu) {
                continue;
            }

            $expectedName = 'Veneer '.ucfirst($detail->tipe_veneer)
                .' - '.$ukuran->nama_ukuran
                .' - '.$jenisKayu->nama_kayu
                .' - KW '.$detail->kw;

            if ($expectedName === $record->nama_barang && (int) $detail->qty === (int) $record->jumlah) {
                return $detail;
            }
        }

        return null;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nota.no_nota')
                    ->label('No Nota')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->sortable()
                    ->numeric(),

                TextColumn::make('satuan')
                    ->label('Satuan')
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->keterangan),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('tambah_plywood')
                    ->label('Keluar Plywood')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('info')
                    ->form(static::plywoodFormSchema())
                    ->action(function (RelationManager $livewire, array $data) {
                        $nota = $livewire->getOwnerRecord();
                        if (! $nota) {
                            return;
                        }

                        $isKeluar = $nota instanceof NotaBarangKeluar;

                        $mutasi = $nota->plywoodMutasi ?? PlywoodMutasi::create([
                            'tanggal' => $nota->tanggal,
                            'tipe_transaksi' => $isKeluar ? 'keluar' : 'masuk',
                            'no_nota' => $nota->no_nota,
                            'tujuan_nota' => $nota->tujuan_nota ?? '-',
                            'status' => 'draft',
                            'id_nota_bk' => $isKeluar ? $nota->id : null,
                            'id_nota_bm' => $isKeluar ? null : $nota->id,
                            'dibuat_oleh' => auth()->id(),
                        ]);

                        $ukuran = static::resolveUkuran($data['ukuran_key']);
                        $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                        $qty = (int) $data['jumlah'];

                        PlywoodMutasiDetail::create([
                            'id_plywood_mutasi' => $mutasi->id,
                            'id_ukuran' => $ukuran->id,
                            'id_jenis_kayu' => $data['id_jenis_kayu'],
                            'kw_grade' => $data['kw_grade'],
                            'qty' => $qty,
                            'm3' => PlywoodMutasiDetail::hitungM3($ukuran, $qty),
                        ]);

                        $namaBarang = 'Plywood - '.$ukuran->nama_ukuran
                            .' - '.$jenisKayu->nama_kayu
                            .' - KW '.$data['kw_grade'];

                        $payload = [
                            'nama_barang' => $namaBarang,
                            'jumlah' => $qty,
                            'satuan' => 'Lembar',
                            'keterangan' => $data['keterangan'] ?? 'Otomatis dari Mutasi Plywood',
                        ];

                        if ($isKeluar) {
                            DetailNotaBarangKeluar::create($payload + ['id_nota_bk' => $nota->id]);
                        } else {
                            DetailNotaBarangMasuk::create($payload + ['id_nota_bm' => $nota->id]);
                        }

                        $livewire->dispatch('$refresh');
                    })
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        return $nota && empty($nota->divalidasi_oleh);
                    }),

                Action::make('tambah_veneer')
                    ->label('Tambah Veneer')
                    ->icon('heroicon-o-plus-circle')
                    ->color('warning')
                    ->form([
                        Select::make('tipe_veneer')
                            ->label('Tipe Veneer')
                            ->options([
                                'basah' => 'Veneer Basah',
                                'kering' => 'Veneer Kering',
                                'jadi' => 'Veneer Jadi',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('id_ukuran', null);
                                $set('id_jenis_kayu', null);
                                $set('kw', null);
                            }),

                        Select::make('id_ukuran')
                            ->label('Ukuran')
                            ->options(function (callable $get) {
                                $tipe = $get('tipe_veneer');
                                if (! $tipe) {
                                    return [];
                                }

                                if ($tipe === 'basah') {
                                    $availableUkuranIds = HppVeneerBasahSummary::where('stok_lembar', '>', 0)
                                        ->get()
                                        ->map(function ($summary) {
                                            return Ukuran::where([
                                                'panjang' => $summary->panjang,
                                                'lebar' => $summary->lebar,
                                                'tebal' => $summary->tebal,
                                            ])->first()?->id;
                                        })
                                        ->filter()
                                        ->unique();

                                    return Ukuran::whereIn('id', $availableUkuranIds)
                                        ->get()
                                        ->pluck('nama_ukuran', 'id');
                                } else {
                                    // TEMPORARY: show all sizes for dry veneer
                                    return Ukuran::all()->pluck('nama_ukuran', 'id');
                                }
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('id_jenis_kayu', null);
                                $set('kw', null);
                            }),

                        Select::make('id_jenis_kayu')
                            ->label('Jenis Kayu')
                            ->options(function (callable $get) {
                                $tipe = $get('tipe_veneer');
                                $idUkuran = $get('id_ukuran');
                                if (! $tipe || ! $idUkuran) {
                                    return [];
                                }

                                if ($tipe === 'basah') {
                                    $ukuran = Ukuran::find($idUkuran);
                                    if (! $ukuran) {
                                        return [];
                                    }

                                    $availableJenisKayuIds = HppVeneerBasahSummary::where([
                                        'panjang' => $ukuran->panjang,
                                        'lebar' => $ukuran->lebar,
                                        'tebal' => $ukuran->tebal,
                                    ])
                                        ->where('stok_lembar', '>', 0)
                                        ->pluck('id_jenis_kayu')
                                        ->unique();

                                    return JenisKayu::whereIn('id', $availableJenisKayuIds)
                                        ->pluck('nama_kayu', 'id');
                                } else {
                                    // TEMPORARY: show all jenis kayu for dry veneer
                                    return JenisKayu::pluck('nama_kayu', 'id');
                                }
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('kw', null);
                            }),

                        Select::make('kw')
                            ->label('KW')
                            ->options(function (callable $get) {
                                $tipe = $get('tipe_veneer');
                                $idUkuran = $get('id_ukuran');
                                $idJenisKayu = $get('id_jenis_kayu');
                                if (! $tipe || ! $idUkuran || ! $idJenisKayu) {
                                    return [];
                                }

                                if ($tipe === 'basah') {
                                    $ukuran = Ukuran::find($idUkuran);
                                    if (! $ukuran) {
                                        return [];
                                    }

                                    $availableKws = HppVeneerBasahSummary::where([
                                        'id_jenis_kayu' => $idJenisKayu,
                                        'panjang' => $ukuran->panjang,
                                        'lebar' => $ukuran->lebar,
                                        'tebal' => $ukuran->tebal,
                                    ])
                                        ->where('stok_lembar', '>', 0)
                                        ->pluck('kw')
                                        ->unique();
                                    $options = [];
                                    foreach ($availableKws as $kw) {
                                        $options[$kw] = 'KW '.$kw;
                                    }

                                    return $options;
                                } else {
                                    // Ambil daftar KW dari master Grade
                                    return Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade');
                                }
                            })
                            ->searchable()
                            ->required()
                            ->live(),

                        Placeholder::make('stok_saat_ini')
                            ->label('Stok Saat Ini')
                            ->content(function (callable $get) {
                                $tipe = $get('tipe_veneer');
                                $idUkuran = $get('id_ukuran');
                                $idJenisKayu = $get('id_jenis_kayu');
                                $kw = $get('kw');
                                $ukuran = $idUkuran ? Ukuran::find($idUkuran) : null;

                                if (! $tipe || ! $idUkuran || ! $idJenisKayu || ! $kw) {
                                    return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
                                }

                                if ($tipe === 'basah') {
                                    if (! $ukuran) {
                                        return new HtmlString('<strong class="text-danger-600 dark:text-danger-400">0 Lembar</strong>');
                                    }

                                    $summary = HppVeneerBasahSummary::where([
                                        'id_jenis_kayu' => $idJenisKayu,
                                        'panjang' => $ukuran->panjang,
                                        'lebar' => $ukuran->lebar,
                                        'tebal' => $ukuran->tebal,
                                        'kw' => $kw,
                                    ])->first();

                                    $stok = $summary ? (int) $summary->stok_lembar : 0;
                                } elseif ($tipe === 'jadi') {
                                    $summaryJadi = StokVeneerJadi::where([
                                        'id_jenis_kayu' => $idJenisKayu,
                                        'panjang' => $ukuran->panjang,
                                        'lebar' => $ukuran->lebar,
                                        'tebal' => $ukuran->tebal,
                                        'kw_grade' => $kw,
                                    ])->first();

                                    $stok = $summaryJadi ? (int) $summaryJadi->stok_lembar : 0;
                                } else {
                                    $latest = StokVeneerKering::where([
                                        'id_ukuran' => $idUkuran,
                                        'id_jenis_kayu' => $idJenisKayu,
                                        'kw' => $kw,
                                    ])
                                        ->orderBy('tanggal_transaksi', 'desc')
                                        ->orderBy('id', 'desc')
                                        ->first();

                                    $stok = $latest ? (int) $latest->stok_lembar_sesudah : 0;
                                }

                                if ($stok <= 0) {
                                    return new HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">0 Lembar (Stok Habis)</strong>');
                                }

                                return new HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">'.number_format($stok).' Lembar</strong>');
                            }),

                        TextInput::make('jumlah')
                            ->label('Jumlah (Lembar)')
                            ->numeric()
                            ->required(),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (RelationManager $livewire, array $data) {
                        $nota = $livewire->getOwnerRecord();
                        if (! $nota) {
                            return;
                        }

                        $mutasi = $nota->mutasi;
                        $isKeluar = $nota instanceof NotaBarangKeluar;

                        if (! $mutasi) {
                            $mutasi = VeneerMutasi::create([
                                'tanggal' => $nota->tanggal,
                                'tipe_transaksi' => $isKeluar ? 'keluar' : 'masuk',
                                'no_nota' => $nota->no_nota,
                                'tujuan_nota' => $nota->tujuan_nota ?? '-',
                                'status' => 'draft',
                                'id_nota_bk' => $isKeluar ? $nota->id : null,
                                'id_nota_bm' => $isKeluar ? null : $nota->id,
                                'dibuat_oleh' => auth()->id(),
                            ]);
                        }

                        $ukuran = Ukuran::findOrFail($data['id_ukuran']);
                        $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);

                        $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * (int) $data['jumlah']) / 10000000;

                        VeneerMutasiDetail::create([
                            'id_veneer_mutasi' => $mutasi->id,
                            'tipe_veneer' => $data['tipe_veneer'],
                            'id_ukuran' => $data['id_ukuran'],
                            'id_jenis_kayu' => $data['id_jenis_kayu'],
                            'kw' => $data['kw'],
                            'qty' => (int) $data['jumlah'],
                            'm3' => $m3,
                        ]);

                        $namaBarang = 'Veneer '.ucfirst($data['tipe_veneer'])
                            .' - '.$ukuran->nama_ukuran
                            .' - '.$jenisKayu->nama_kayu
                            .' - KW '.$data['kw'];

                        if ($isKeluar) {
                            DetailNotaBarangKeluar::create([
                                'id_nota_bk' => $nota->id,
                                'nama_barang' => $namaBarang,
                                'jumlah' => (int) $data['jumlah'],
                                'satuan' => 'Lembar',
                                'keterangan' => $data['keterangan'] ?? 'Otomatis dari Mutasi Veneer Keluar',
                            ]);
                        } else {
                            DetailNotaBarangMasuk::create([
                                'id_nota_bm' => $nota->id,
                                'nama_barang' => $namaBarang,
                                'jumlah' => (int) $data['jumlah'],
                                'satuan' => 'Lembar',
                                'keterangan' => $data['keterangan'] ?? 'Otomatis dari Mutasi Veneer Masuk',
                            ]);
                        }

                        $livewire->dispatch('$refresh');
                    })
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        // Hanya muncul jika belum divalidasi
                        return $nota && empty($nota->divalidasi_oleh);
                    }),

                CreateAction::make()
                    ->label('Tambah Barang')
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        // Muncul jika belum divalidasi
                        return $nota && empty($nota->divalidasi_oleh);
                    }),

                Action::make('validasi_nota')
                    ->label('Validasi Nota')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->ownerRecord;
                        if (! $nota) {
                            return false;
                        }
                        // Tombol hanya muncul jika BELUM divalidasi
                        if (! empty($nota->divalidasi_oleh)) {
                            return false;
                        }
                        // Jika Super Admin, boleh lihat (bisa validasi)
                        $user = auth()->user();
                        if ($user && $user->hasAnyRole(['super_admin', 'Super Admin'])) {
                            return true;
                        }

                        // Pembuat TIDAK boleh validasi (hilangkan tombol)
                        return $nota->dibuat_oleh != auth()->id();
                    })
                    ->action(function (RelationManager $livewire) {
                        $nota = $livewire->ownerRecord;

                        try {
                            $hasVeneer = VeneerMutasi::where('id_nota_bk', $nota->id)->exists();
                            $hasPlywood = PlywoodMutasi::where('id_nota_bk', $nota->id)->exists();

                            DB::transaction(function () use ($nota) {
                                app(VeneerMutasiService::class)->processStockFromNota($nota);

                                // Pastikan divalidasi_oleh terbaca service plywood
                                $nota->refresh();

                                app(PlywoodMutasiService::class)->processStockFromNota($nota);
                            });

                            $pesan = match (true) {
                                $hasVeneer && $hasPlywood => 'Stok veneer & plywood telah dikurangi sesuai isi nota BK.',
                                $hasVeneer => 'Stok veneer telah dikurangi sesuai isi nota BK.',
                                $hasPlywood => 'Stok plywood telah dikurangi sesuai isi nota BK.',
                                default => 'Status nota telah diperbarui.',
                            };

                            Notification::make()
                                ->title('Nota berhasil divalidasi!')
                                ->body($pesan)
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Validasi Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->after(function ($livewire) {
                        // Refresh komponen supaya status berubah
                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->form(function ($record) {
                        if (str_starts_with($record->nama_barang, 'Plywood ')) {
                            return static::plywoodFormSchema();
                        }

                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            return [
                                Select::make('tipe_veneer')
                                    ->label('Tipe Veneer')
                                    ->options([
                                        'basah' => 'Veneer Basah',
                                        'kering' => 'Veneer Kering',
                                        'jadi' => 'Veneer Jadi',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('id_ukuran', null);
                                        $set('id_jenis_kayu', null);
                                        $set('kw', null);
                                    }),

                                Select::make('id_ukuran')
                                    ->label('Ukuran')
                                    ->options(function (callable $get) {
                                        $tipe = $get('tipe_veneer');
                                        if (! $tipe) {
                                            return [];
                                        }

                                        if ($tipe === 'basah') {
                                            $availableUkuranIds = HppVeneerBasahSummary::where('stok_lembar', '>', 0)
                                                ->get()
                                                ->map(function ($summary) {
                                                    return Ukuran::where([
                                                        'panjang' => $summary->panjang,
                                                        'lebar' => $summary->lebar,
                                                        'tebal' => $summary->tebal,
                                                    ])->first()?->id;
                                                })
                                                ->filter()
                                                ->unique();

                                            return Ukuran::whereIn('id', $availableUkuranIds)
                                                ->get()
                                                ->pluck('nama_ukuran', 'id');
                                        } else {
                                            // TEMPORARY: show all sizes for dry veneer
                                            return Ukuran::all()->pluck('nama_ukuran', 'id');
                                        }
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('id_jenis_kayu', null);
                                        $set('kw', null);
                                    }),

                                Select::make('id_jenis_kayu')
                                    ->label('Jenis Kayu')
                                    ->options(function (callable $get) {
                                        $tipe = $get('tipe_veneer');
                                        $idUkuran = $get('id_ukuran');
                                        if (! $tipe || ! $idUkuran) {
                                            return [];
                                        }

                                        if ($tipe === 'basah') {
                                            $ukuran = Ukuran::find($idUkuran);
                                            if (! $ukuran) {
                                                return [];
                                            }

                                            $availableJenisKayuIds = HppVeneerBasahSummary::where([
                                                'panjang' => $ukuran->panjang,
                                                'lebar' => $ukuran->lebar,
                                                'tebal' => $ukuran->tebal,
                                            ])
                                                ->where('stok_lembar', '>', 0)
                                                ->pluck('id_jenis_kayu')
                                                ->unique();

                                            return JenisKayu::whereIn('id', $availableJenisKayuIds)
                                                ->pluck('nama_kayu', 'id');
                                        } else {
                                            // TEMPORARY: show all jenis kayu for dry veneer
                                            return JenisKayu::pluck('nama_kayu', 'id');
                                        }
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('kw', null);
                                    }),

                                Select::make('kw')
                                    ->label('KW')
                                    ->options(function (callable $get) {
                                        $tipe = $get('tipe_veneer');
                                        $idUkuran = $get('id_ukuran');
                                        $idJenisKayu = $get('id_jenis_kayu');
                                        if (! $tipe || ! $idUkuran || ! $idJenisKayu) {
                                            return [];
                                        }

                                        if ($tipe === 'basah') {
                                            $ukuran = Ukuran::find($idUkuran);
                                            if (! $ukuran) {
                                                return [];
                                            }

                                            $availableKws = HppVeneerBasahSummary::where([
                                                'id_jenis_kayu' => $idJenisKayu,
                                                'panjang' => $ukuran->panjang,
                                                'lebar' => $ukuran->lebar,
                                                'tebal' => $ukuran->tebal,
                                            ])
                                                ->where('stok_lembar', '>', 0)
                                                ->pluck('kw')
                                                ->unique();
                                            $options = [];
                                            foreach ($availableKws as $kw) {
                                                $options[$kw] = 'KW '.$kw;
                                            }

                                            return $options;
                                        } else {
                                            // Ambil daftar KW dari master Grade
                                            return Grade::orderBy('nama_grade')->pluck('nama_grade', 'nama_grade');
                                        }
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                Placeholder::make('stok_saat_ini')
                                    ->label('Stok Saat Ini')
                                    ->content(function (callable $get) {
                                        $tipe = $get('tipe_veneer');
                                        $idUkuran = $get('id_ukuran');
                                        $idJenisKayu = $get('id_jenis_kayu');
                                        $kw = $get('kw');
                                        $ukuran = $idUkuran ? Ukuran::find($idUkuran) : null;

                                        if (! $tipe || ! $idUkuran || ! $idJenisKayu || ! $kw) {
                                            return new HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
                                        }

                                        if ($tipe === 'basah') {
                                            if (! $ukuran) {
                                                return new HtmlString('<strong class="text-danger-600 dark:text-danger-400">0 Lembar</strong>');
                                            }

                                            $summary = HppVeneerBasahSummary::where([
                                                'id_jenis_kayu' => $idJenisKayu,
                                                'panjang' => $ukuran->panjang,
                                                'lebar' => $ukuran->lebar,
                                                'tebal' => $ukuran->tebal,
                                                'kw' => $kw,
                                            ])->first();

                                            $stok = $summary ? (int) $summary->stok_lembar : 0;
                                        } elseif ($tipe === 'jadi') {
                                            // Mengambil stok dari model StokVeneerJadi dengan mencocokkan dimensi & kw_grade
                                            $summaryJadi = StokVeneerJadi::where([
                                                'id_jenis_kayu' => $idJenisKayu,
                                                'panjang' => $ukuran->panjang,
                                                'lebar' => $ukuran->lebar,
                                                'tebal' => $ukuran->tebal,
                                                'kw_grade' => $kw, // Menggunakan kolom kw_grade sesuai properti model
                                            ])->first();

                                            $stok = $summaryJadi ? (int) $summaryJadi->stok_lembar : 0;
                                        } else {
                                            $latest = StokVeneerKering::where([
                                                'id_ukuran' => $idUkuran,
                                                'id_jenis_kayu' => $idJenisKayu,
                                                'kw' => $kw,
                                            ])
                                                ->orderBy('tanggal_transaksi', 'desc')
                                                ->orderBy('id', 'desc')
                                                ->first();

                                            $stok = $latest ? (int) $latest->stok_lembar_sesudah : 0;
                                        }

                                        if ($stok <= 0) {
                                            return new HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">0 Lembar (Stok Habis)</strong>');
                                        }

                                        return new HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">'.number_format($stok).' Lembar</strong>');
                                    }),

                                TextInput::make('jumlah')
                                    ->label('Jumlah (Lembar)')
                                    ->numeric()
                                    ->required(),

                                Textarea::make('keterangan')
                                    ->label('Keterangan')
                                    ->rows(3)
                                    ->required(),
                            ];
                        }

                        return [
                            TextInput::make('nama_barang')
                                ->label('Nama Barang')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('jumlah')
                                ->label('Jumlah')
                                ->numeric()
                                ->required(),

                            TextInput::make('satuan')
                                ->label('Satuan')
                                ->required()
                                ->maxLength(50),

                            Textarea::make('keterangan')
                                ->label('Keterangan')
                                ->rows(3)
                                ->required(),
                        ];
                    })
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        $data['jumlah'] = (int) $record->jumlah;
                        $data['keterangan'] = $record->keterangan;

                        if (str_starts_with($record->nama_barang, 'Plywood ')) {
                            $detail = static::findPlywoodDetail($record);

                            if ($detail && $detail->ukuran) {
                                $u = $detail->ukuran;
                                $data['ukuran_key'] = static::dimKey($u->panjang, $u->lebar, $u->tebal);
                                $data['id_jenis_kayu'] = $detail->id_jenis_kayu;
                                $data['kw_grade'] = $detail->kw_grade;
                            }

                            return $data;
                        }

                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            $detail = static::findVeneerDetail($record);

                            if ($detail) {
                                $data['tipe_veneer'] = $detail->tipe_veneer;
                                $data['id_ukuran'] = $detail->id_ukuran;
                                $data['id_jenis_kayu'] = $detail->id_jenis_kayu;
                                $data['kw'] = $detail->kw;
                            }
                        }

                        return $data;
                    })
                    ->using(function ($record, array $data) {
                        if (str_starts_with($record->nama_barang, 'Plywood ')) {
                            $matchingDetail = static::findPlywoodDetail($record);

                            $ukuran = static::resolveUkuran($data['ukuran_key']);
                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $qty = (int) $data['jumlah'];

                            if ($matchingDetail) {
                                $matchingDetail->update([
                                    'id_ukuran' => $ukuran->id,
                                    'id_jenis_kayu' => $data['id_jenis_kayu'],
                                    'kw_grade' => $data['kw_grade'],
                                    'qty' => $qty,
                                    'm3' => PlywoodMutasiDetail::hitungM3($ukuran, $qty),
                                ]);
                            }

                            $record->update([
                                'nama_barang' => 'Plywood - '.$ukuran->nama_ukuran
                                    .' - '.$jenisKayu->nama_kayu
                                    .' - KW '.$data['kw_grade'],
                                'jumlah' => $qty,
                                'keterangan' => $data['keterangan'] ?? $record->keterangan,
                            ]);

                            return $record;
                        }

                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            $matchingDetail = static::findVeneerDetail($record);

                            if ($matchingDetail) {
                                $matchingDetail->update([
                                    'tipe_veneer' => $data['tipe_veneer'],
                                    'id_ukuran' => $data['id_ukuran'],
                                    'id_jenis_kayu' => $data['id_jenis_kayu'],
                                    'kw' => $data['kw'],
                                    'qty' => (int) $data['jumlah'],
                                ]);

                                // Recalculate m3
                                $ukuranObj = Ukuran::findOrFail($data['id_ukuran']);
                                $matchingDetail->m3 = ($ukuranObj->panjang * $ukuranObj->lebar * $ukuranObj->tebal * $matchingDetail->qty) / 10000000;
                                $matchingDetail->save();
                            }

                            // Generate new nama_barang
                            $ukuran = Ukuran::findOrFail($data['id_ukuran']);
                            $jenisKayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $newNamaBarang = 'Veneer '.ucfirst($data['tipe_veneer'])
                                .' - '.$ukuran->nama_ukuran
                                .' - '.$jenisKayu->nama_kayu
                                .' - KW '.$data['kw'];

                            $record->update([
                                'nama_barang' => $newNamaBarang,
                                'jumlah' => (int) $data['jumlah'],
                                'keterangan' => $data['keterangan'] ?? $record->keterangan,
                            ]);
                        } else {
                            $record->update([
                                'nama_barang' => $data['nama_barang'],
                                'jumlah' => (int) $data['jumlah'],
                                'satuan' => $data['satuan'],
                                'keterangan' => $data['keterangan'] ?? null,
                            ]);
                        }

                        return $record;
                    })
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        return $nota && empty($nota->divalidasi_oleh);
                    }),

                DeleteAction::make()
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        // Hanya bisa delete jika belum divalidasi
                        return $nota && empty($nota->divalidasi_oleh);
                    })
                    ->before(function ($record) {
                        if (str_starts_with($record->nama_barang, 'Plywood ')) {
                            static::findPlywoodDetail($record)?->delete();

                            return;
                        }

                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            static::findVeneerDetail($record)?->delete();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }
}