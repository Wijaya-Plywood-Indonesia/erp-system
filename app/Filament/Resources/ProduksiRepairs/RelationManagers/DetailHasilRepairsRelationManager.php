<?php

namespace App\Filament\Resources\ProduksiRepairs\RelationManagers;

use App\Models\JenisKayu;
use App\Models\ModalRepair;
use App\Models\Ukuran;
use App\Services\DetailHasilRepairService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DetailHasilRepairsRelationManager extends RelationManager
{
    protected static string $relationship = 'detailHasilRepairs';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Hidden::make('id_modal_repair')
                    ->dehydrated(),

                Hidden::make('id_ukuran')
                    ->required()
                    ->dehydrated(),

                TextInput::make('is_ukuran_manual')
                    ->hidden()
                    ->default(false)
                    ->dehydrated(false),

                Hidden::make('id_jenis_kayu')
                    ->dehydrated(),


                Select::make('rencanaPegawais')
                    ->label('Pegawai Repair')
                    ->relationship(
                        name: 'rencanaPegawais',
                        titleAttribute: 'id', // Gunakan 'id' sebagai atribut default agar tidak error order clause
                        modifyQueryUsing: function ($query) {
                            $produksiId = $this->getOwnerRecord()?->id;

                            if ($produksiId) {
                                // Pastikan eager load relasi pegawai jika ada
                                $query->with('pegawai')
                                    ->where('id_produksi_repair', $produksiId);
                            }

                            return $query;
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->pegawai?->nama_pegawai ?? $record->nama_pegawai ?? "Pegawai #{$record->id}")
                    ->multiple()
                    ->required()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),

                // ===================================================
                // 2. SATU-SATUNYA DROPDOWN SELECT DENGAN ACTION BUTTON
                // ===================================================
                Select::make('sumber_pilihan')
                    ->label(
                        fn($get) => $get('is_ukuran_manual')
                            ? 'Ukuran Hasil (Manual)'
                            : 'Ukuran Hasil (Dari Modal)'
                    )
                    ->placeholder(
                        fn($get) => $get('is_ukuran_manual')
                            ? 'Pilih Ukuran Manual...'
                            : 'Pilih Modal Repair...'
                    )
                    ->searchable()
                    ->required()
                    ->live()
                    ->dehydrated(false)

                    // ===================================================
                    // 1. OPTIONS DEFAULT (Hanya Modal yang Stoknya > 0)
                    // ===================================================
                    ->options(function ($get, $record) {
                        if ($get('is_ukuran_manual')) {
                            return Ukuran::all()->pluck('dimensi', 'id');
                        }

                        return ModalRepair::with(['ukuran', 'jenisKayu'])
                            ->withSum('detailHasilRepairs as total_terpakai', 'jumlah')
                            ->get()
                            ->filter(function ($modal) use ($record) {
                                $totalTerpakai = $modal->total_terpakai ?? 0;

                                // Jika sedang EDIT record ini, kembalikan jumlah dari record yang sedang diedit ke sisa stok
                                if ($record && $record->id_modal_repair == $modal->id) {
                                    $totalTerpakai -= $record->jumlah;
                                }

                                $sisaStok = $modal->jumlah - $totalTerpakai;

                                // HANYA TAMPILKAN JIKA SISA STOK > 0
                                return $sisaStok > 0;
                            })
                            ->mapWithKeys(function ($modal) use ($record) {
                                $jenisKayu = $modal->jenisKayu?->nama_kayu ?? '-'; // Menggunakan nama_kayu
                                $ukuran = $modal->ukuran?->dimensi ?? '-';
                                $kw = $modal->kw ?? '-';

                                $totalTerpakai = $modal->total_terpakai ?? 0;
                                if ($record && $record->id_modal_repair == $modal->id) {
                                    $totalTerpakai -= $record->jumlah;
                                }

                                $sisaStok = number_format($modal->jumlah - $totalTerpakai);

                                return [$modal->id => "{$jenisKayu} | {$ukuran} | KW {$kw} — Jumlah : {$sisaStok} Lbr"];
                            });
                    })

                    // ===================================================
                    // 2. SEARCH RESULTS (Pencarian Fleksibel & Tepat Kolom)
                    // ===================================================
                    ->getSearchResultsUsing(function (string $search, $get, $record) {
                        $cleanSearch = str_replace([',', '.'], '', trim($search));

                        if ($get('is_ukuran_manual')) {
                            return Ukuran::query()
                                ->where(function ($query) use ($search) {
                                    $query->whereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"])
                                        ->orWhere('panjang', 'LIKE', "%{$search}%")
                                        ->orWhere('lebar', 'LIKE', "%{$search}%")
                                        ->orWhere('tebal', 'LIKE', "%{$search}%");
                                })
                                ->limit(50)
                                ->get()
                                ->pluck('dimensi', 'id');
                        }

                        return ModalRepair::with(['ukuran', 'jenisKayu'])
                            ->withSum('detailHasilRepairs as total_terpakai', 'jumlah')
                            ->where(function ($query) use ($search, $cleanSearch) {
                                $query->where('kw', 'LIKE', "%{$search}%")
                                    ->orWhere('jumlah', 'LIKE', "%{$cleanSearch}%")
                                    ->orWhereHas('jenisKayu', function ($q) use ($search) {
                                        $q->where('nama_kayu', 'LIKE', "%{$search}%"); // Menggunakan nama_kayu
                                    })
                                    ->orWhereHas('ukuran', function ($q) use ($search) {
                                        $q->whereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                                    });
                            })
                            ->get()
                            ->filter(function ($modal) use ($record) {
                                $totalTerpakai = $modal->total_terpakai ?? 0;
                                if ($record && $record->id_modal_repair == $modal->id) {
                                    $totalTerpakai -= $record->jumlah;
                                }

                                $sisaStok = $modal->jumlah - $totalTerpakai;

                                return $sisaStok > 0;
                            })
                            ->mapWithKeys(function ($modal) use ($record) {
                                $jenisKayu = $modal->jenisKayu?->nama_kayu ?? '-'; // Menggunakan nama_kayu
                                $ukuran = $modal->ukuran?->dimensi ?? '-';
                                $kw = $modal->kw ?? '-';

                                $totalTerpakai = $modal->total_terpakai ?? 0;
                                if ($record && $record->id_modal_repair == $modal->id) {
                                    $totalTerpakai -= $record->jumlah;
                                }

                                $sisaStok = number_format($modal->jumlah - $totalTerpakai);

                                return [$modal->id => "{$jenisKayu} | {$ukuran} | KW {$kw} — Jumlah : {$sisaStok} Lbr"];
                            });
                    })

                    // ===================================================
                    // 3. LABEL SAAT OPSI TERPILIH / HYDRATED
                    // ===================================================
                    ->getOptionLabelUsing(function ($value, $get) {
                        if (! $value) return null;

                        if ($get('is_ukuran_manual')) {
                            return Ukuran::find($value)?->dimensi;
                        }

                        $modal = ModalRepair::with(['ukuran', 'jenisKayu'])
                            ->withSum('detailHasilRepairs as total_terpakai', 'jumlah')
                            ->find($value);

                        if (! $modal) return null;

                        $jenisKayu = $modal->jenisKayu?->nama_kayu ?? '-'; // Menggunakan nama_kayu
                        $ukuran = $modal->ukuran?->dimensi ?? '-';
                        $kw = $modal->kw ?? '-';
                        $sisaStok = number_format($modal->jumlah - ($modal->total_terpakai ?? 0));

                        return "{$jenisKayu} | {$ukuran} | KW {$kw} — [Sisa Stok: {$sisaStok} Lbr]";
                    })
                    ->afterStateUpdated(function ($state, $set, $get) {
                        if (! $state) {
                            $set('id_modal_repair', null);
                            $set('id_ukuran', null);
                            return;
                        }

                        if ($get('is_ukuran_manual')) {
                            $set('id_modal_repair', null);
                            $set('id_ukuran', $state);
                        } else {
                            $modal = ModalRepair::find($state);
                            if ($modal) {
                                $set('id_modal_repair', $modal->id);
                                $set('id_ukuran', $modal->id_ukuran);
                                $set('id_jenis_kayu', $modal->id_jenis_kayu);
                                $set('kw', $modal->kw ?? null);
                            }
                        }
                    })
                    ->suffixAction(
                        Action::make('toggleUkuranManual')
                            ->icon(fn($get) => $get('is_ukuran_manual') ? 'heroicon-m-arrow-path' : 'heroicon-m-scissors')
                            ->color(fn($get) => $get('is_ukuran_manual') ? 'warning' : 'danger')
                            ->tooltip(
                                fn($get) => $get('is_ukuran_manual')
                                    ? 'Kembali ke Pilihan Modal Repair'
                                    : 'Ukuran Beda dari Modal? Klik untuk Pilih Ukuran Manual'
                            )
                            ->action(function ($set, $get) {
                                $statusSaatIni = (bool) $get('is_ukuran_manual');
                                $statusBaru = ! $statusSaatIni;

                                $set('is_ukuran_manual', $statusBaru);
                                $set('sumber_pilihan', null);
                                $set('id_modal_repair', null);
                                $set('id_ukuran', null);

                                if ($statusBaru) {
                                    $set('kw', null);
                                }

                                if (! $statusBaru && $get('id_modal_repair')) {
                                    $modal = ModalRepair::find($get('id_modal_repair'));
                                    if ($modal) {
                                        $set('sumber_pilihan', $modal->id);
                                        $set('id_modal_repair', $modal->id);
                                        $set('id_ukuran', $modal->id_ukuran);
                                        $set('kw', $modal->kw ?? null);
                                    }
                                }
                            })
                    )
                    ->afterStateHydrated(function ($component, $record, $set) {
                        if (! $record) return;

                        $isBedaUkuran = $record->id_modal_repair
                            && $record->modalRepair
                            && $record->id_ukuran !== $record->modalRepair->id_ukuran;

                        if ($isBedaUkuran || (blank($record->id_modal_repair) && $record->id_ukuran)) {
                            $set('is_ukuran_manual', true);
                            $component->state($record->id_ukuran);
                        } else {
                            $set('is_ukuran_manual', false);
                            $component->state($record->id_modal_repair);
                        }
                    }),
                // =========================================
                // 2. DETAIL PRODUKSI & KW
                // =========================================
                TextInput::make('kw')
                    ->label('KW')
                    ->required()
                    ->live(onBlur: true)
                    ->disabled(fn($get) => ! $get('is_ukuran_manual') && filled($get('id_modal_repair')))
                    ->dehydrated(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(fn() => JenisKayu::pluck('nama_kayu', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->columnSpanFull()
                    ->visible(fn($get) => (bool) $get('is_ukuran_manual'))
                    ->required(fn($get) => (bool) $get('is_ukuran_manual'))
                    ->dehydrated(),

                TextInput::make('nomor_meja')
                    ->label('Nomor Meja')
                    ->numeric()
                    ->nullable()
                    ->required(),

                TextInput::make('jumlah')
                    ->label('Hasil Repair')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->rule(function ($get, $record) {
                        return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $idModal = $get('id_modal_repair');
                            $isManual = (bool) $get('is_ukuran_manual');

                            if (! $isManual && $idModal) {
                                $modal = ModalRepair::withSum('detailHasilRepairs as total_terpakai', 'jumlah')->find($idModal);

                                if ($modal) {
                                    $totalTerpakai = $modal->total_terpakai ?? 0;

                                    // JIKA EDIT: Kurangi total terpakai dengan nilai jumlah yang lama dari record ini
                                    if ($record && $record->id_modal_repair == $idModal) {
                                        $totalTerpakai -= $record->jumlah;
                                    }

                                    $sisaStok = $modal->jumlah - $totalTerpakai;

                                    if ((int) $value > $sisaStok) {
                                        $fail("Jumlah tidak boleh melebihi sisa stok modal ({$sisaStok} lembar).");
                                    }
                                }
                            }
                        };
                    }),

                // =========================================
                // 4. KETERANGAN & AUDIT TRAIL
                // =========================================
                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('kw')
            ->modifyQueryUsing(
                fn(Builder $query) => $query->with([
                    'rencanaPegawais.pegawai',
                    'modalRepair.jenisKayu',
                    'jenisKayu',
                    'ukuran',
                    'diserahkanBy',
                ])
            )
            ->groups([
                Group::make('id')
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(function ($record) {
                        if ($record->rencanaPegawais->isEmpty()) {
                            return 'Tanpa Pegawai';
                        }
                        $namaPegawais = $record->rencanaPegawais
                            ->map(function ($rencana) {
                                return $rencana->pegawai?->nama_pegawai
                                    ?? $rencana->pegawai?->nama
                                    ?? $rencana->nama_pegawai
                                    ?? 'Pegawai #' . $rencana->id;
                            })
                            ->filter()
                            ->implode(' & ');
                        return $namaPegawais ?: '-';
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('id')
            ->columns([

                TextColumn::make('jenis_kayu')
                    ->label('Jenis Kayu')
                    ->state(function ($record) {
                        // 1. Ambil dari Modal Repair jika ada
                        if ($record->modalRepair?->jenisKayu?->nama_kayu) {
                            return $record->modalRepair->jenisKayu->nama_kayu;
                        }

                        if ($record->jenisKayu?->nama_kayu) {
                            return $record->jenisKayu->nama_kayu;
                        }

                        return '-';
                    })
                    ->sortable(false)
                    ->searchable(),
                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->sortable()
                    ->searchable()
                    ->description(function ($record) {
                        if ($record->id_modal_repair && $record->id_ukuran !== $record->modalRepair?->id_ukuran) {
                            return 'Ukuran disesuaikan (Beda dari modal)';
                        }

                        return null;
                    }),

                TextColumn::make('kw')
                    ->label('KW')
                    ->badge()
                    ->sortable(),

                TextColumn::make('nomor_meja')
                    ->label('No. Meja')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('diserahkanBy.name')
                    ->label('Diserahkan Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('diserahkan_at')
                    ->label('Status Serah')
                    ->badge()
                    ->state(fn($record) => $record->diserahkan_at ? 'Diserahkan' : 'Belum Diserahkan')
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->diserahkan_at) {
                            $waktu = Carbon::parse($record->diserahkan_at)->format('d/m/Y H:i');
                            return "Diserahkan - {$waktu}";
                        }
                        return 'Belum Diserahkan';
                    })
                    ->color(fn($record) => $record->diserahkan_at ? 'success' : 'gray'),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->state(fn($record) => $record->keterangan ?: null)
                    ->tooltip(fn($state) => $state)
                    ->limit(30)
                    ->sortable()
                    ->searchable()
                    ->wrap()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('serah_ke_gudang')
                    ->label('Serah')
                    ->icon('heroicon-s-arrow-right-end-on-rectangle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Serah Terima Barang')
                    ->modalDescription('Apakah Anda yakin ingin menyerahkan hasil repair ini ke Gudang Veneer Jadi?')
                    ->modalSubmitActionLabel('Serahkan Sekarang')
                    ->hidden(fn($record) => ! empty($record->diserahkan_at))
                    ->action(function ($record) {
                        try {
                            // Panggil Service Class via Dependency Injection / app()
                            app(DetailHasilRepairService::class)->serahKeGudang($record);

                            Notification::make()
                                ->success()
                                ->title('Berhasil Diserahkan!')
                                ->body('Data hasil repair telah berhasil diserahkan ke Gudang Veneer Jadi.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Proses Gagal')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
