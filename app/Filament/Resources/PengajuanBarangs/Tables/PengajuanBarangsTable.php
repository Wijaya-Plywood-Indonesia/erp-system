<?php

namespace App\Filament\Resources\PengajuanBarangs\Tables;

use App\Services\PengajuanBarangService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class PengajuanBarangsTable
{
    /**
     * Format qty tanpa nol di belakang koma yang tidak perlu.
     * 10.0000 -> "10", 10.5000 -> "10.5"
     */
    public static function formatQty(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                // ── Kolom Barang Diajukan: ringkas + tombol "Lihat Detail" (modal) ──
                TextColumn::make('items_count')
                    ->label('Barang Diajukan')
                    ->counts('items')
                    ->formatStateUsing(fn($state) => $state . ' item — Lihat Detail')
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-o-eye')
                    ->action(
                        Action::make('lihatDetailItems')
                            ->label('Lihat Detail')
                            ->modalHeading('Detail Barang Diajukan')
                            ->modalWidth(Width::Medium)
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                            ->modalContent(function ($record) {
                                $rows = $record->items
                                    ->map(function ($item) {
                                        $nama   = e($item->barangUmum?->nama_barang ?? '-');
                                        $satuan = e($item->barangUmum?->satuan ?? '');
                                        $jumlah = static::formatQty((float) $item->jumlah);

                                        return "<li class=\"flex justify-between py-1 border-b border-gray-100 dark:border-gray-700\">
                                                    <span>{$nama}</span>
                                                    <span class=\"font-medium\">{$jumlah} {$satuan}</span>
                                                </li>";
                                    })
                                    ->implode('');

                                return new HtmlString("<ul class=\"text-sm\">{$rows}</ul>");
                            })
                    ),

                TextColumn::make('lokasi_penggunaan')
                    ->label('Lokasi')
                    ->searchable(),

                TextColumn::make('pengaju.name')
                    ->label('Diajukan Oleh'),

                // ── Kolom Pengawas Produksi: badge jadi tombol verifikasi kalau berhak & masih menunggu ──
                TextColumn::make('status_pengawas_produksi')
                    ->label('Pengawas Produksi')
                    ->badge()
                    ->color(function (string $state, $record) {
                        if ($state === 'menunggu'
                            && Auth::user()?->hasRole(['pengawas_produksi', 'super_admin'])) {
                            return 'primary';
                        }
                        return match ($state) {
                            'disetujui' => 'success',
                            'ditolak'   => 'danger',
                            default     => 'gray',
                        };
                    })
                    ->icon(function (string $state, $record) {
                        return ($state === 'menunggu'
                            && Auth::user()?->hasRole(['pengawas_produksi', 'super_admin']))
                            ? 'heroicon-o-pencil-square'
                            : null;
                    })
                    ->formatStateUsing(function (string $state, $record) {
                        if ($state === 'menunggu'
                            && Auth::user()?->hasRole(['pengawas_produksi', 'super_admin'])) {
                            return 'Verifikasi Sekarang';
                        }
                        return ucfirst($state);
                    })
                    ->action(
                        Action::make('verifikasiPengawas')
                            ->label('Verifikasi (Pengawas Produksi)')
                            ->modalHeading('Verifikasi - Pengawas Produksi')
                            ->visible(fn($record) => Auth::user()?->hasRole(['pengawas_produksi', 'super_admin'])
                                && $record->status_pengawas_produksi === 'menunggu')
                            ->schema([
                                Radio::make('keputusan')
                                    ->label('Keputusan')
                                    ->options([
                                        'disetujui' => 'Setujui',
                                        'ditolak'   => 'Tolak',
                                    ])
                                    ->inline()
                                    ->required()
                                    ->live(),

                                Textarea::make('alasan')
                                    ->label('Alasan Penolakan')
                                    ->rows(2)
                                    ->visible(fn($get) => $get('keputusan') === 'ditolak')
                                    ->requiredIf('keputusan', 'ditolak'),
                            ])
                            ->action(function ($record, array $data) {
                                static::putuskan($record, 'pengawas_produksi', $data['keputusan']);
                            })
                    ),

                // ── Kolom Kepala Produksi: badge jadi tombol verifikasi kalau berhak & masih menunggu ──
                TextColumn::make('status_kepala_produksi')
                    ->label('Kepala Produksi')
                    ->badge()
                    ->color(function (string $state, $record) {
                        if ($state === 'menunggu'
                            && Auth::user()?->hasRole(['kepala_produksi_wijaya', 'super_admin'])) {
                            return 'primary';
                        }
                        return match ($state) {
                            'disetujui' => 'success',
                            'ditolak'   => 'danger',
                            default     => 'gray',
                        };
                    })
                    ->icon(function (string $state, $record) {
                        return ($state === 'menunggu'
                            && Auth::user()?->hasRole(['kepala_produksi_wijaya', 'super_admin']))
                            ? 'heroicon-o-pencil-square'
                            : null;
                    })
                    ->formatStateUsing(function (string $state, $record) {
                        if ($state === 'menunggu'
                            && Auth::user()?->hasRole(['kepala_produksi_wijaya', 'super_admin'])) {
                            return 'Verifikasi Sekarang';
                        }
                        return ucfirst($state);
                    })
                    ->action(
                        Action::make('verifikasiKepala')
                            ->label('Verifikasi (Kepala Produksi)')
                            ->modalHeading('Verifikasi - Kepala Produksi')
                            ->visible(fn($record) => Auth::user()?->hasRole(['kepala_produksi_wijaya', 'super_admin'])
                                && $record->status_kepala_produksi === 'menunggu')
                            ->schema([
                                Radio::make('keputusan')
                                    ->label('Keputusan')
                                    ->options([
                                        'disetujui' => 'Setujui',
                                        'ditolak'   => 'Tolak',
                                    ])
                                    ->inline()
                                    ->required()
                                    ->live(),

                                Textarea::make('alasan')
                                    ->label('Alasan Penolakan')
                                    ->rows(2)
                                    ->visible(fn($get) => $get('keputusan') === 'ditolak')
                                    ->requiredIf('keputusan', 'ditolak'),
                            ])
                            ->action(function ($record, array $data) {
                                static::putuskan($record, 'kepala_produksi', $data['keputusan']);
                            })
                    ),

                // ── Kolom Admin Barang: badge jadi tombol verifikasi kalau berhak & masih menunggu ──
                TextColumn::make('status_admin_barang')
                    ->label('Admin Barang')
                    ->badge()
                    ->color(function (string $state, $record) {
                        if ($state === 'menunggu'
                            && Auth::user()?->hasRole(['admin_barang', 'super_admin'])) {
                            return 'primary';
                        }
                        return match ($state) {
                            'disetujui' => 'success',
                            'ditolak'   => 'danger',
                            default     => 'gray',
                        };
                    })
                    ->icon(function (string $state, $record) {
                        return ($state === 'menunggu'
                            && Auth::user()?->hasRole(['admin_barang', 'super_admin']))
                            ? 'heroicon-o-pencil-square'
                            : null;
                    })
                    ->formatStateUsing(function (string $state, $record) {
                        if ($state === 'menunggu'
                            && Auth::user()?->hasRole(['admin_barang', 'super_admin'])) {
                            return 'Verifikasi Sekarang';
                        }
                        return ucfirst($state);
                    })
                    ->action(
                        Action::make('verifikasiAdmin')
                            ->label('Verifikasi (Admin Barang)')
                            ->modalHeading('Verifikasi - Admin Barang')
                            ->visible(fn($record) => Auth::user()?->hasRole(['admin_barang', 'super_admin'])
                                && $record->status_admin_barang === 'menunggu')
                            ->schema([
                                Radio::make('keputusan')
                                    ->label('Keputusan')
                                    ->options([
                                        'disetujui' => 'Setujui',
                                        'ditolak'   => 'Tolak',
                                    ])
                                    ->inline()
                                    ->required()
                                    ->live(),

                                Textarea::make('alasan')
                                    ->label('Alasan Penolakan')
                                    ->rows(2)
                                    ->visible(fn($get) => $get('keputusan') === 'ditolak')
                                    ->requiredIf('keputusan', 'ditolak'),
                            ])
                            ->action(function ($record, array $data) {
                                static::putuskan($record, 'admin_barang', $data['keputusan']);
                            })
                    ),

                TextColumn::make('status_ringkas')
                    ->label('Status')
                    ->badge()
                    ->color(fn($record) => match (true) {
                        $record->sudahDiproses()      => 'success',
                        $record->adaYangMenolak()      => 'danger',
                        $record->sudahDisetujuiSemua() => 'warning',
                        default                          => 'gray',
                    }),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                ImageColumn::make('foto')
                    ->label('Foto')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraImgAttributes(['class' => 'cursor-pointer hover:opacity-75 transition'])
                    ->action(
                        Action::make('lihatFoto')
                            ->label('Lihat Foto')
                            ->modalHeading('Foto')
                            ->modalWidth(Width::Large)
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                            ->visible(fn($record) => filled($record->foto))
                            ->modalContent(function ($record) {
                                $url = \Illuminate\Support\Facades\Storage::url($record->foto);

                                return new HtmlString(
                                    "<img src=\"{$url}\" alt=\"Foto\" class=\"w-full h-auto rounded-lg\" />"
                                );
                            })
                    ),
            ])
            ->filters([
                Filter::make('tanggal')
                    ->label('Rentang Tanggal')
                    ->schema([
                        DatePicker::make('dari')
                            ->label('Dari Tanggal'),
                        DatePicker::make('sampai')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['dari'] ?? null, fn($q, $tanggal) => $q->whereDate('tanggal', '>=', $tanggal))
                            ->when($data['sampai'] ?? null, fn($q, $tanggal) => $q->whereDate('tanggal', '<=', $tanggal));
                    })
                    ->indicateUsing(function (array $data) {
                        $indicators = [];

                        if ($data['dari'] ?? null) {
                            $indicators[] = 'Dari ' . \Illuminate\Support\Carbon::parse($data['dari'])->format('d/m/Y');
                        }
                        if ($data['sampai'] ?? null) {
                            $indicators[] = 'Sampai ' . \Illuminate\Support\Carbon::parse($data['sampai'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                SelectFilter::make('status_kepala_produksi')
                    ->label('Status Kepala Produksi')
                    ->options([
                        'menunggu'  => 'Menunggu',
                        'disetujui' => 'Disetujui',
                        'ditolak'   => 'Ditolak',
                    ]),
                SelectFilter::make('status_admin_barang')
                    ->label('Status Admin Barang')
                    ->options([
                        'menunggu'  => 'Menunggu',
                        'disetujui' => 'Disetujui',
                        'ditolak'   => 'Ditolak',
                    ]),
                SelectFilter::make('status_pengawas_produksi')
                    ->label('Status Pengawas Produksi')
                    ->options([
                        'menunggu'  => 'Menunggu',
                        'disetujui' => 'Disetujui',
                        'ditolak'   => 'Ditolak',
                    ]),
            ])
            ->recordActions([
                // ── Edit: hanya boleh selagi belum ada satupun keputusan (kepala produksi & admin barang masih menunggu) ──
                EditAction::make()
                    ->visible(fn($record) => ($record->status_kepala_produksi === 'menunggu'
                            || $record->status_admin_barang === 'menunggu'
                            || $record->status_pengawas_produksi === 'menunggu')
                        && (Auth::id() === $record->diajukan_oleh || Auth::user()?->hasRole('super_admin'))),

                // ── Hapus: sama, tampil selama minimal 1 pihak belum memutuskan ──
                DeleteAction::make()
                    ->visible(fn($record) => ($record->status_kepala_produksi === 'menunggu'
                            || $record->status_admin_barang === 'menunggu'
                            || $record->status_pengawas_produksi === 'menunggu')
                        && (Auth::id() === $record->diajukan_oleh || Auth::user()?->hasRole('super_admin'))),

                // ── Retry kalau dulu gagal karena stok kurang ──
                Action::make('cobaProsesUlang')
                    ->label('Coba Proses Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->sudahDisetujuiSemua() && !$record->sudahDiproses())
                    ->action(function ($record) {
                        try {
                            app(PengajuanBarangService::class)->cobaProsesUlang($record);
                            Notification::make()->success()->title('Berhasil diproses, stok terpotong.')->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    protected static function putuskan($record, string $role, string $keputusan): void
    {
        try {
            app(PengajuanBarangService::class)->approve(
                pengajuan: $record,
                role: $role,
                keputusan: $keputusan,
                userId: Auth::id(),
            );

            Notification::make()->success()
                ->title($keputusan === 'disetujui' ? 'Berhasil disetujui' : 'Berhasil ditolak')
                ->send();
        } catch (\RuntimeException $e) {
            Notification::make()->danger()
                ->title('Gagal')
                ->body($e->getMessage())
                ->send();
        }
    }
}