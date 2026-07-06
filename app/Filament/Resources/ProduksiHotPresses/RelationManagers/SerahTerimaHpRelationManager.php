<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Models\ProduksiGrajitriplek;
use App\Models\ProduksiHp;
use App\Models\SerahTerimaHp;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerahTerimaHpRelationManager extends RelationManager
{
    private const ROLE_ADMIN = ['super_admin', 'Super Admin', 'admin_kayu'];

    protected static string $relationship = 'serahTerimaHp';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return match (get_class($ownerRecord)) {
            ProduksiHp::class => 'Serah Triplek',
            ProduksiGrajitriplek::class => 'Terima Triplek',
            default => 'Serah Terima Triplek',
        };
    }

    protected function getTipe(): string
    {
        return match (get_class($this->getOwnerRecord())) {
            ProduksiHp::class => 'hp',
            ProduksiGrajitriplek::class => 'graji',
            default => 'unknown',
        };
    }

    /**
     * Ambil data ringkas dari record untuk ditampilkan di preview modal terima.
     */
    protected function getPreviewData($record): array
    {
        $hasil = $record->triplekHasilHp;

        return [
            'no_palet' => $hasil?->no_palet ?? '-',
            'jenis_barang' => $hasil?->barangSetengahJadi?->jenisBarang?->nama_jenis_barang ?? '-',
            'grade' => $hasil?->barangSetengahJadi?->grade?->nama_grade ?? '-',
            'ukuran' => $hasil?->barangSetengahJadi?->ukuran?->nama_ukuran ?? '-',
            'isi' => $hasil?->isi ?? '-',
            'dari_mesin' => $hasil?->mesin?->nama_mesin ?? '-',
        ];
    }

    public function table(Table $table): Table
    {
        $tipe = $this->getTipe();
        $ownerId = $this->getOwnerRecord()->id;

        return $table
            ->modifyQueryUsing(function ($query) use ($tipe, $ownerId) {
                if ($tipe === 'hp') {
                    // Hotpress: hanya tampilkan riwayat miliknya sendiri
                    // (hasManyThrough sudah filter otomatis by FK owner)
                    return $query
                        ->with([
                            'triplekHasilHp.mesin',
                            'triplekHasilHp.barangSetengahJadi.jenisBarang',
                            'triplekHasilHp.barangSetengahJadi.grade',
                            'triplekHasilHp.barangSetengahJadi.ukuran',
                        ])
                        ->orderBy('created_at', 'desc');
                }

                // Graji Triplek: reset constraint hasMany, tampilkan semua menunggu + riwayat sendiri
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                return $query
                    ->with([
                        'triplekHasilHp.mesin',
                        'triplekHasilHp.barangSetengahJadi.jenisBarang',
                        'triplekHasilHp.barangSetengahJadi.grade',
                        'triplekHasilHp.barangSetengahJadi.ukuran',
                    ])
                    ->where(function ($q) use ($ownerId) {
                        $q->where('diterima_oleh', '-')
                            ->orWhere('id_produksi_graji_triplek', $ownerId);
                    })
                    ->orderBy('diterima_oleh', 'asc')
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('triplekHasilHp.no_palet')
                    ->label('No. Palet')
                    ->badge()
                    ->color('info'),

                TextColumn::make('triplekHasilHp.mesin.nama_mesin')
                    ->label('Mesin')
                    ->placeholder('-'),

                TextColumn::make('triplekHasilHp.barangSetengahJadi.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->placeholder('-'),

                TextColumn::make('triplekHasilHp.barangSetengahJadi.grade.nama_grade')
                    ->label('Grade')
                    ->placeholder('-'),

                TextColumn::make('triplekHasilHp.barangSetengahJadi.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->placeholder('-'),

                TextColumn::make('triplekHasilHp.isi')
                    ->label('Jumlah Lembar')
                    ->alignCenter(),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('diterima_oleh')
                    ->label('Diterima Oleh')
                    ->badge()
                    ->color(fn ($state) => $state === '-' ? 'gray' : 'success')
                    ->formatStateUsing(fn ($state) => $state === '-' ? 'Menunggu' : $state),

                TextColumn::make('status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn ($state) => match ($state) {
                        'Terima Triplek' => 'success',
                        'Serah Triplek' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->actions([
                Action::make('terima')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Terima Triplek ini?')
                    ->modalDescription('Periksa data triplek berikut sebelum menerima.')
                    ->schema(function ($record) {
                        $preview = $this->getPreviewData($record);

                        return [
                            Grid::make(2)
                                ->schema([
                                    Placeholder::make('preview_no_palet')
                                        ->label('No. Palet')
                                        ->content($preview['no_palet']),

                                    Placeholder::make('preview_jenis_barang')
                                        ->label('Jenis Barang')
                                        ->content($preview['jenis_barang']),

                                    Placeholder::make('preview_grade')
                                        ->label('Grade')
                                        ->content($preview['grade']),

                                    Placeholder::make('preview_ukuran')
                                        ->label('Ukuran')
                                        ->content($preview['ukuran']),

                                    Placeholder::make('preview_isi')
                                        ->label('Jumlah Lembar')
                                        ->content($preview['isi']),

                                    Placeholder::make('preview_dari_mesin')
                                        ->label('Dari Mesin')
                                        ->content($preview['dari_mesin']),
                                ]),
                        ];
                    })
                    // Hanya muncul kalau dibuka dari Graji Triplek DAN belum diterima
                    ->visible(fn ($record) => $tipe === 'graji' && $record->diterima_oleh === '-')
                    ->action(function ($record) use ($ownerId) {
                        try {
                            DB::transaction(function () use ($record, $ownerId) {
                                $fresh = SerahTerimaHp::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Triplek ini sudah diambil produksi lain.');
                                }

                                $fresh->update([
                                    'diterima_oleh' => Auth::user()->name.' - Graji Triplek',
                                    'id_produksi_graji_triplek' => $ownerId,
                                    'status' => 'Terima Triplek',
                                ]);

                                // Stok belum diupdate di sini — menyusul
                            });

                            Notification::make()
                                ->title('Triplek Berhasil Diterima')
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->hasAnyRole(self::ROLE_ADMIN)),
                ]),
            ]);
    }
}
