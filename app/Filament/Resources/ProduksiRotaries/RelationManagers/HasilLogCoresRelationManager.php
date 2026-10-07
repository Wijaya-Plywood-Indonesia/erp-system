<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use App\Models\HasilLogCore;
use App\Models\JenisKayu;
use App\Models\LogLogCore;
use App\Models\StokLogCore;
use App\Services\LogCoreStokService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class HasilLogCoresRelationManager extends RelationManager
{
    protected static string $relationship = 'hasilLogCores';

    protected function isSuperAdmin(): bool
    {
        return (bool) \Filament\Facades\Filament::auth()->user()?->hasRole('super_admin');
    }

    public function isReadOnly(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user();

        // Hanya role ini yang terdampak lock
        $rolesAffectedByLock = [
            'pengawas_rotary_1',
            'pengawas_rotary_2',
            'kepala_produksi_wijaya',
        ];

        // Jika user bukan salah satu dari role di atas, tidak terkunci
        if (!$user?->hasAnyRole($rolesAffectedByLock)) {
            return false;
        }

        $ownerRecord = $this->getOwnerRecord();

        $validated = \App\Models\ValidasiHasilRotary::where('id_produksi', $ownerRecord->id)
            ->where('status', 'disetujui')
            ->pluck('role')
            ->toArray();

        $kepalaSudah = collect($validated)->contains(
            fn($role) => str_contains(strtolower($role), 'kepala_produksi')
        );

        $pengawasSudah = collect($validated)->contains(
            fn($role) => str_contains(strtolower($role), 'pengawas_rotary')
        );

        return $kepalaSudah && $pengawasSudah;
    }
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('panjang')
                    ->label('Panjang')
                    ->options([
                        130 => '130',
                        260 => '260',
                    ])
                    ->required()
                    ->native(false),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::orderBy('nama_kayu')
                            ->pluck('nama_kayu', 'id')
                            ->toArray()
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('qty')
                    ->label('Qty (batang)')
                    ->numeric()
                    ->minValue(0.01)
                    ->required()
                    ->columnSpanFull(),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query
                ->with('logTerakhir')
                ->select('hasil_log_cores.*')
                ->addSelect([
                    'status_log_terakhir' => LogLogCore::select('tipe_transaksi')
                        ->whereColumn('referensi_id', 'hasil_log_cores.id')
                        ->where('referensi_type', HasilLogCore::class)
                        ->latest('id')
                        ->limit(1),
                ]))
            ->recordTitleAttribute('keterangan')
            ->columns([
                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('panjang')
                    ->label('Panjang')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('qty')
                    ->label('Qty')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                TextColumn::make('status_serah')
                    ->label('Status Serah')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $log = $record->logTerakhir;

                        // Belum pernah diserah, atau terakhir dibatalkan
                        if (! $log || $log->tipe_transaksi !== 'masuk') {
                            return 'Belum Diserah';
                        }

                        // Ambil nama dari format "Serah oleh {nama} | ..."
                        $nama = '-';
                        if (preg_match('/^Serah oleh (.+?)(?: \| |$)/', (string) $log->keterangan, $m)) {
                            $nama = $m[1];
                        }

                        $waktu = $log->created_at?->format('d/m/Y H:i') ?? '-';

                        return "Diserahkan - {$nama} - {$waktu}";
                    })
                    ->color(fn($state) => str_starts_with($state, 'Diserahkan') ? 'success' : 'gray')
                    ->wrap(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->searchable()
                    ->limit(50)
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('serah')
                    ->label('Serah')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn($record) => ! $record->sudah_diserah)
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan ke Stok Log Core')
                    ->modalDescription(fn($record) => sprintf(
                        'Serahkan %s batang %s panjang %s ke stok? Tanggal mengikuti tanggal produksi.',
                        number_format((float) $record->qty, 2, ',', '.'),
                        $record->jenisKayu?->nama_kayu ?? '-',
                        $record->panjang,
                    ))
                    ->modalSubmitActionLabel('Ya, Serahkan')
                    ->action(function ($record): void {
                        try {
                            app(LogCoreStokService::class)->serahHasilLogCore(
                                hasil: $record,
                                tanggal: Carbon::parse($this->getOwnerRecord()->tgl_produksi)->toDateString(),
                            );

                            Notification::make()
                                ->title('Berhasil diserahkan ke stok')
                                ->success()
                                ->send();
                        } catch (RuntimeException $e) {
                            Notification::make()
                                ->title('Gagal menyerahkan')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);

                            Notification::make()
                                ->title('Terjadi kesalahan sistem')
                                ->body('Silakan coba lagi atau hubungi admin.')
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make()
                    ->visible(fn($record) => ! $record->sudah_diserah),

                DeleteAction::make()
                    ->visible(fn($record) => ! $record->sudah_diserah),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('batal_serah')
                        ->label('Batalkan Serah')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->visible(fn() => $this->isSuperAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Batalkan Serah')
                        ->modalDescription('Stok akan dikurangi kembali. Jika stok sudah terpakai, pembatalan ditolak.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            if (! $this->isSuperAdmin()) {
                                Notification::make()->title('Anda tidak punya akses membatalkan serah')->danger()->send();
                                return;
                            }

                            // Urutkan supaya semua proses mengunci stok dalam urutan sama (cegah deadlock)
                            $diserah = $records
                                ->filter(fn($r) => $r->sudah_diserah)
                                ->sortBy(['id_jenis_kayu', 'panjang']);

                            if ($diserah->isEmpty()) {
                                Notification::make()->title('Tidak ada data yang sudah diserah')->warning()->send();
                                return;
                            }

                            try {
                                DB::transaction(function () use ($diserah) {
                                    $service = app(LogCoreStokService::class);

                                    foreach ($diserah as $record) {
                                        $service->batalSerahHasilLogCore($record, 'Pembatalan serah oleh super_admin');
                                    }
                                });

                                Notification::make()
                                    ->title($diserah->count() . ' data berhasil dibatalkan serahnya')
                                    ->success()
                                    ->send();
                            } catch (RuntimeException $e) {
                                Notification::make()
                                    ->title('Pembatalan gagal, tidak ada data yang berubah')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            } catch (\Throwable $e) {
                                report($e);

                                Notification::make()
                                    ->title('Terjadi kesalahan sistem')
                                    ->body('Silakan coba lagi atau hubungi admin.')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    // Bulk delete: tolak jika ada yang sudah diserah
                    DeleteBulkAction::make()
                        ->before(function (Collection $records) {
                            if ($records->contains(fn($r) => $r->sudah_diserah)) {
                                Notification::make()
                                    ->title('Tidak bisa dihapus')
                                    ->body('Ada data yang sudah diserah. Batalkan serah dulu.')
                                    ->danger()
                                    ->send();

                                throw new Halt();
                            }
                        }),
                ]),
            ]);
    }
}
