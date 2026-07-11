<?php

namespace App\Filament\Resources\ProduksiTembelTripleks\RelationManagers;

use App\Models\HasilTembeltriplek;
use App\Models\PegawaiTembeltriplek;
use App\Models\SerahTerimaTriplekCacat;
use App\Models\SerahTerimaTriplekJadi;
// Custom Schema & Table
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
// Form Components
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
// Table Columns & Custom Actions
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class HasilTembeltriplekRelationManager extends RelationManager
{
    protected static string $relationship = 'hasilTembeltriplek';

    protected static ?string $title = 'Hasil Tembel Triplek';

    public function form(Schema $form): Schema
    {
        $ownerId = $this->getOwnerRecord()->id;

        return $form
            ->schema([
                Select::make('id_serah_terima_triplek_cacat')
                    ->label('Barang Cacat (Serah Terima)')
                    ->required()
                    ->searchable()
                    ->live()
                    ->options(function () use ($ownerId) {
                        return SerahTerimaTriplekCacat::query()
                            ->where('id_produksi_tembel_triplek', $ownerId)
                            ->where('diterima_oleh', '!=', '-')
                            ->with(
                                'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                                'hasilPilihPlywood.barangSetengahJadiHp.grade',
                                'hasilPilihPlywood.barangSetengahJadiHp.ukuran'
                            )
                            ->get()
                            ->filter(fn ($s) => $s->sisa > 0)
                            ->mapWithKeys(function ($s) {
                                $bsj = $s->barangSetengahJadi;

                                $label = ($bsj?->jenisBarang?->nama_jenis_barang ?? '-').' | '.
                                    ($bsj?->ukuran?->nama_ukuran ?? '-').' | '.
                                    ($bsj?->grade?->nama_grade ?? '-').
                                    ' — Sisa: '.$s->sisa;

                                return [$s->id => $label];
                            });
                    })
                    ->columnSpanFull(),

                Placeholder::make('info_sisa')
                    ->label('Sisa Tersedia')
                    ->content(function (callable $get) {
                        $id = $get('id_serah_terima_triplek_cacat');

                        if (! $id) {
                            return '-';
                        }

                        $serah = SerahTerimaTriplekCacat::find($id);

                        return $serah ? $serah->sisa.' lembar' : '-';
                    })
                    ->live()
                    ->columnSpanFull(),

                TextInput::make('modal')
                    ->label('Modal (Jumlah Dipakai)')
                    ->numeric()
                    ->required(),

                TextInput::make('hasil')
                    ->numeric()
                    ->required(),

                TextInput::make('nomor_palet')
                    ->numeric(),

                Select::make('pegawais')
                    ->label('Dikerjakan Oleh (Pegawai)')
                    ->relationship(
                        name: 'pegawais',
                        titleAttribute: 'nama_pegawai',
                        modifyQueryUsing: function (Builder $query, $livewire) {
                            $produksiId = $livewire->ownerRecord?->id ?? null;

                            if ($produksiId) {
                                $pegawaiIds = PegawaiTembeltriplek::query()
                                    ->where('id_produksi_tembel_triplek', $produksiId)
                                    ->pluck('id_pegawai')
                                    ->toArray();

                                return $query->whereIn('pegawais.id', $pegawaiIds);
                            }

                            return $query;
                        }
                    )
                    ->multiple()
                    ->required()
                    ->preload()
                    ->searchable(),
            ]);
    }

    /**
     * Validasi + isi id_barang_setengah_jadi_hp, dijalankan di dalam DB transaction
     * dengan lockForUpdate supaya perhitungan `sisa` benar-benar fresh dan atomik —
     * tidak bisa dilewati walau ada beberapa submit beruntun/bersamaan.
     *
     * $excludeHasilId dipakai saat EDIT, supaya modal record ini sendiri
     * tidak ikut dihitung sebagai "sudah terpakai" (mencegah sisa salah hitung).
     */
    protected function validateDanIsiData(array $data, ?int $excludeHasilId = null): array
    {
        return DB::transaction(function () use ($data, $excludeHasilId) {
            $serah = SerahTerimaTriplekCacat::with('hasilPilihPlywood')
                ->lockForUpdate()
                ->find($data['id_serah_terima_triplek_cacat'] ?? null);

            if (! $serah || ! $serah->hasilPilihPlywood) {
                Notification::make()
                    ->title('Gagal')
                    ->body('Data serah terima tidak valid atau barang setengah jadi tidak ditemukan.')
                    ->danger()
                    ->send();

                throw new Halt;
            }

            $terpakai = HasilTembeltriplek::where('id_serah_terima_triplek_cacat', $serah->id)
                ->when($excludeHasilId, fn ($q) => $q->where('id', '!=', $excludeHasilId))
                ->sum('modal');

            $sisaReal = $serah->qtyAsli - (float) $terpakai;

            if (($data['modal'] ?? 0) > $sisaReal) {
                Notification::make()
                    ->title('Gagal')
                    ->body("Modal ({$data['modal']}) melebihi sisa yang tersedia ({$sisaReal}).")
                    ->danger()
                    ->send();

                throw new Halt;
            }

            $data['id_barang_setengah_jadi_hp'] = $serah->hasilPilihPlywood->id_barang_setengah_jadi_hp;

            return $data;
        });
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query) => $query->with([
                    'pegawais',
                    'serahTerimaTriplekCacat.hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                    'serahTerimaTriplekCacat.hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                    'serahTerimaTriplekCacat.hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang',
                ])
            )
            ->columns([
                TextColumn::make('pegawais.nama_pegawai')
                    ->label('Dikerjakan Oleh')
                    ->badge()
                    ->wrap(),

                TextColumn::make('barang')
                    ->label('Barang')
                    ->getStateUsing(function ($record) {
                        $b = $record->serahTerimaTriplekCacat?->barangSetengahJadi;
                        if (! $b) {
                            return '-';
                        }

                        return ($b->grade?->kategoriBarang?->nama_kategori ?? '-').' | '.
                            ($b->ukuran?->nama_ukuran ?? '-').' | '.
                            ($b->grade?->nama_grade ?? '-').' | '.
                            ($b->jenisBarang?->nama_jenis_barang ?? '-');
                    })
                    ->wrap(),

                TextColumn::make('modal')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('hasil')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($record) => $record->hasil < $record->modal ? 'danger' : 'success'),

                TextColumn::make('sisa_serah_terima')
                    ->label('Sisa (Serah Terima)')
                    ->getStateUsing(fn ($record) => $record->serahTerimaTriplekCacat?->sisa ?? '-')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nomor_palet')
                    ->label('No. Palet')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status_serah')
                    ->label('Status Serah')
                    ->getStateUsing(fn ($record) => $record->diserahkan_at ? 'Sudah Diserahkan' : 'Belum Diserahkan')
                    ->badge()
                    ->color(fn ($record) => $record->diserahkan_at ? 'success' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $this->validateDanIsiData($data)),
            ])
            ->recordActions([
                Action::make('serahkan')
                    ->label('Serahkan')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->diserahkan_at === null && $record->hasil > 0)
                    ->schema(function ($record) {
                        $b = $record->serahTerimaTriplekCacat?->barangSetengahJadi;

                        return [
                            Grid::make(2)->schema([
                                Placeholder::make('info_barang')
                                    ->label('Barang')
                                    ->content($b?->jenisBarang?->nama_jenis_barang ?? '-'),
                                Placeholder::make('info_grade')
                                    ->label('Grade')
                                    ->content($b?->grade?->nama_grade ?? '-'),
                                Placeholder::make('info_ukuran')
                                    ->label('Ukuran')
                                    ->content($b?->ukuran?->nama_ukuran ?? '-'),
                                Placeholder::make('info_palet')
                                    ->label('No. Palet')
                                    ->content((string) ($record->nomor_palet ?? '-')),
                                Placeholder::make('info_hasil')
                                    ->label('Hasil (Jadi Bagus)')
                                    ->content(new HtmlString('<strong>'.number_format((float) $record->hasil).' lembar</strong>')),
                            ]),
                        ];
                    })
                    ->modalHeading(fn ($record) => 'Serahkan Hasil Tembel Triplek — Palet '.$record->nomor_palet)
                    ->modalDescription(fn ($record) => 'Barang hasil perbaikan Tembel Triplek ('.number_format((float) $record->hasil).' lembar) akan diserahkan ke Pilih Plywood sebagai barang bagus.')
                    ->modalSubmitActionLabel('Serahkan')
                    ->modalWidth('md')
                    ->requiresConfirmation(false)
                    ->action(function ($record) {
                        try {
                            DB::transaction(function () use ($record) {
                                // Ambil ulang + lock, hindari race condition (double serah/klik ganda)
                                $fresh = $record->newQuery()->lockForUpdate()->find($record->id);

                                if ($fresh->diserahkan_at !== null) {
                                    throw new \RuntimeException('Palet ini sudah diserahkan sebelumnya. Muat ulang halaman.');
                                }

                                if ((float) $fresh->hasil <= 0) {
                                    throw new \RuntimeException('Hasil (jumlah jadi bagus) tidak valid, tidak bisa diserahkan.');
                                }

                                if ($fresh->serahTerimaTriplekJadi()->exists()) {
                                    throw new \RuntimeException('Data serah terima untuk palet ini sudah ada.');
                                }

                                $fresh->update([
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diserahkan_at' => now(),
                                ]);

                                SerahTerimaTriplekJadi::create([
                                    'id_hasil_tembel_triplek' => $fresh->id,
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'status' => 'Menunggu Diterima',
                                ]);
                            });

                            Notification::make()
                                ->title('Berhasil diserahkan')
                                ->body('Palet '.$record->nomor_palet.' ('.number_format((float) $record->hasil).' lembar) diserahkan ke Pilih Plywood.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->hidden(fn ($record) => (bool) $record->diserahkan_at)
                    ->mutateFormDataUsing(fn (array $data, $record): array => $this->validateDanIsiData($data, $record->id)),

                DeleteAction::make()
                    ->hidden(fn ($record) => (bool) $record->diserahkan_at),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
