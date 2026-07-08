<?php

namespace App\Filament\Resources\HasilGrajiTripleks\Tables;

use App\Models\HasilGrajiTriplek;
use App\Models\SerahTerimaHp;
use App\Models\SerahTerimaTriplekJadi;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class HasilGrajiTripleksTable
{
    /**
     * Ambil record serah-terima yang aktif untuk satu HasilGrajiTriplek,
     * apapun sumbernya (SerahTerimaHp untuk Sanding, SerahTerimaTriplekJadi
     * untuk Gudang). Return null kalau belum pernah diserahkan sama sekali.
     *
     * @return array{record: SerahTerimaHp|SerahTerimaTriplekJadi, tujuan: string}|null
     */
    protected static function resolveSerahTerima(HasilGrajiTriplek $record): ?array
    {
        if ($record->serahTerimaHp) {
            $tujuan = match ($record->serahTerimaHp->tujuan) {
                'gudang' => 'Gudang',
                'sanding' => 'Sanding',
                default => '-',
            };

            return [
                'record' => $record->serahTerimaHp,
                'tujuan' => $tujuan,
            ];
        }

        if ($record->serahTerimaTriplekJadi) {
            return [
                'record' => $record->serahTerimaTriplekJadi,
                'tujuan' => 'Gudang',
            ];
        }

        return null;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'barangSetengahJadiHp.jenisBarang',
                'barangSetengahJadiHp.grade.kategoriBarang',
                'barangSetengahJadiHp.ukuran',
                'serahTerimaHp',
                'serahTerimaTriplekJadi',
            ]))
            ->columns([

                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->color(function (HasilGrajiTriplek $record) {
                        $info = static::resolveSerahTerima($record);

                        if (! $info) {
                            return 'gray';
                        }

                        return $info['record']->diterima_oleh === '-' ? 'warning' : 'success';
                    })
                    ->description(function (HasilGrajiTriplek $record) {
                        $info = static::resolveSerahTerima($record);

                        if (! $info) {
                            return 'Belum Serah';
                        }

                        $tujuan = $info['tujuan'];

                        return $info['record']->diterima_oleh === '-'
                            ? "Diserahkan ke {$tujuan} — Menunggu Diterima"
                            : "Diserahkan ke {$tujuan} — Sudah Diterima";
                    }),

                TextColumn::make('barangSetengahJadiHp.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grade_display')
                    ->label('Grade')
                    ->getStateUsing(fn ($record) => ($record->barangSetengahJadiHp?->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                        .' | '.
                        ($record->barangSetengahJadiHp?->grade?->nama_grade ?? '-')
                    )
                    ->sortable(),

                TextColumn::make('barangSetengahJadiHp.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->sortable(),

                TextColumn::make('isi')
                    ->label('Jumlah')
                    ->alignCenter(),

                /*
                 * STATUS SERAH — toggleable, default hidden
                 * Mencerminkan status gabungan dari serahTerimaHp (Sanding)
                 * ATAU serahTerimaTriplekJadi (Gudang), mana yang terisi.
                 */
                TextColumn::make('status_serah')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (HasilGrajiTriplek $record) {
                        $info = static::resolveSerahTerima($record);

                        if (! $info) {
                            return 'Belum Diserahkan';
                        }

                        $tujuan = $info['tujuan'];

                        return $info['record']->diterima_oleh === '-'
                            ? "Menunggu Diterima {$tujuan}"
                            : "Sudah Diterima {$tujuan}";
                    })
                    ->color(fn ($state) => match (true) {
                        str_contains($state, 'Sudah Diterima') => 'success',
                        str_contains($state, 'Menunggu Diterima') => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * TUJUAN — toggleable, default hidden
                 */
                TextColumn::make('tujuan_serah')
                    ->label('Diserahkan Ke')
                    ->getStateUsing(fn (HasilGrajiTriplek $record) => static::resolveSerahTerima($record)['tujuan'] ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * DISERAHKAN OLEH — toggleable, default hidden
                 */
                TextColumn::make('diserahkan_oleh_display')
                    ->label('Diserahkan Oleh')
                    ->getStateUsing(fn (HasilGrajiTriplek $record) => static::resolveSerahTerima($record)['record']->diserahkan_oleh ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * DITERIMA OLEH — toggleable, default hidden
                 */
                TextColumn::make('diterima_oleh_display')
                    ->label('Diterima Oleh')
                    ->getStateUsing(fn (HasilGrajiTriplek $record) => static::resolveSerahTerima($record)['record']->diterima_oleh ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * CREATED AT — toggleable, default hidden
                 */
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * UPDATED AT — toggleable, default hidden
                 */
                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->headerActions([
                CreateAction::make()
                    ->hidden(fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->recordActions([
                Action::make('serah')
                    ->label('Serah')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (HasilGrajiTriplek $record) => static::resolveSerahTerima($record) === null)
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Hasil Graji Triplek')
                    ->modalDescription('Pastikan data berikut sudah sesuai sebelum diserahkan.')
                    ->modalContent(function (HasilGrajiTriplek $record) {
                        $jenis = $record->barangSetengahJadiHp?->jenisBarang?->nama_jenis_barang ?? '-';
                        $kategori = $record->barangSetengahJadiHp?->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori';
                        $grade = $record->barangSetengahJadiHp?->grade?->nama_grade ?? '-';
                        $ukuran = $record->barangSetengahJadiHp?->ukuran?->nama_ukuran ?? '-';

                        return new HtmlString(<<<HTML
            <div class="space-y-2 text-sm">
                <div class="grid grid-cols-3 gap-1">
                    <span class="font-medium text-gray-500">No. Palet</span>
                    <span class="col-span-2">: {$record->no_palet}</span>

                    <span class="font-medium text-gray-500">Jenis Barang</span>
                    <span class="col-span-2">: {$jenis}</span>

                    <span class="font-medium text-gray-500">Grade</span>
                    <span class="col-span-2">: {$kategori} | {$grade}</span>

                    <span class="font-medium text-gray-500">Ukuran</span>
                    <span class="col-span-2">: {$ukuran}</span>

                    <span class="font-medium text-gray-500">Jumlah Lembar</span>
                    <span class="col-span-2">: {$record->isi}</span>
                </div>
            </div>
        HTML);
                    })
                    ->schema([
                        Radio::make('tujuan')
                            ->label('Diterima Di Mana')
                            ->options([
                                'sanding' => 'Sanding',
                                'gudang' => 'Gudang',
                            ])
                            ->default('sanding')
                            ->required()
                            ->inline(false)
                            ->extraFieldWrapperAttributes(['class' => 'flex flex-col gap-2']),
                    ])
                    ->action(function (HasilGrajiTriplek $record, array $data) {
                        try {
                            DB::transaction(function () use ($record, $data) {
                                if ($data['tujuan'] === 'gudang') {
                                    // Gudang: hanya catat serah terima, stok BELUM ditambah.
                                    // Stok baru ditambahkan nanti saat proses "Terima" (menyusul).
                                    SerahTerimaTriplekJadi::create([
                                        'id_hasil_graji_triplek' => $record->id,
                                        'diserahkan_oleh' => Auth::user()->name,
                                        'diterima_oleh' => '-',
                                        'status' => 'Serah Graji Triplek ke Gudang',
                                    ]);

                                    return;
                                }

                                // Sanding: tetap seperti semula, pakai SerahTerimaHp.
                                SerahTerimaHp::create([
                                    'id_hasil_graji_triplek' => $record->id,
                                    'tujuan' => 'sanding',
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'status' => 'Serah Graji Triplek',
                                ]);
                            });

                            $record->unsetRelation('serahTerimaHp');
                            $record->unsetRelation('serahTerimaTriplekJadi');
                            $record->refresh();

                            $tujuanLabel = $data['tujuan'] === 'gudang' ? 'Gudang' : 'Sanding';

                            Notification::make()
                                ->title('Penyerahan Berhasil')
                                ->body("Palet telah diserahkan ke {$tujuanLabel}.")
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

                Action::make('batalServah')
                    ->label('Batal Serahkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (HasilGrajiTriplek $record) {
                        $info = static::resolveSerahTerima($record);

                        // Hanya bisa dibatalkan selagi belum diterima.
                        return $info !== null && $info['record']->diterima_oleh === '-';
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan Penyerahan?')
                    ->modalDescription('Palet akan kembali berstatus belum diserahkan.')
                    ->action(function (HasilGrajiTriplek $record) {
                        try {
                            $info = static::resolveSerahTerima($record);

                            if (! $info) {
                                return;
                            }

                            DB::transaction(function () use ($info) {
                                $info['record']->delete();
                            });

                            $record->unsetRelation('serahTerimaHp');
                            $record->unsetRelation('serahTerimaTriplekJadi');
                            $record->refresh();

                            Notification::make()
                                ->title('Penyerahan Dibatalkan')
                                ->body('Palet kembali berstatus belum diserahkan.')
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
                    ->hidden(function ($livewire, HasilGrajiTriplek $record) {
                        $info = static::resolveSerahTerima($record);
                        $sudahDiterima = $info !== null && $info['record']->diterima_oleh !== '-';

                        return $sudahDiterima
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi';
                    }),

                DeleteAction::make()
                    ->hidden(function ($livewire, HasilGrajiTriplek $record) {
                        return static::resolveSerahTerima($record) !== null
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi';
                    }),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
