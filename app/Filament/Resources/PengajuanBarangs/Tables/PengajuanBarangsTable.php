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
use Illuminate\Support\Facades\Storage;
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

    /**
     * FileUpload kadang menyimpan sebagai array (mis. ["barang_umum/xxx.jpg"])
     * atau string JSON dari array tersebut — normalisasi dulu jadi path tunggal.
     */
    protected static function normalizeFotoPath($foto): ?string
    {
        $path = $foto;

        if (is_array($path)) {
            $path = $path[0] ?? null;
        } elseif (is_string($path) && str_starts_with(trim($path), '[')) {
            $decoded = json_decode($path, true);
            $path = is_array($decoded) ? ($decoded[0] ?? null) : $path;
        }

        return $path ?: null;
    }

    /**
     * URL foto barang umum dari disk public, siap dipakai di <img src="...">.
     * Null kalau barang tidak punya foto.
     */
    protected static function fotoBarangUmumUrl($barangUmum): ?string
    {
        $path = static::normalizeFotoPath($barangUmum?->foto);

        if (blank($path)) {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        // Rapikan double-slash (mis. akibat APP_URL di .env berakhiran '/')
        return preg_replace('#(?<!:)//+#', '/', $url);
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
                                    ->load('barangUmum')
                                    ->map(function ($item) {
                                        $nama   = e($item->barangUmum?->nama_barang ?? '-');
                                        $satuan = e($item->barangUmum?->satuan ?? '');
                                        $jumlah = static::formatQty((float) $item->jumlah);
                                        $fotoUrl = static::fotoBarangUmumUrl($item->barangUmum);

                                        $thumbnail = $fotoUrl
                                            ? "<img src=\"{$fotoUrl}\" alt=\"{$nama}\" @click=\"preview = '{$fotoUrl}'\" class=\"w-10 h-10 rounded-md object-cover flex-shrink-0 cursor-pointer hover:opacity-75 transition\" />"
                                            : "<div class=\"w-10 h-10 rounded-md bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0\">
                                                    <svg xmlns=\"http://www.w3.org/2000/svg\" class=\"w-5 h-5 text-gray-400\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\">
                                                        <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\" />
                                                    </svg>
                                                </div>";

                                        return "<li class=\"flex items-center justify-between gap-3 py-2 border-b border-gray-100 dark:border-gray-700\">
                                                    <div class=\"flex items-center gap-3 min-w-0\">
                                                        {$thumbnail}
                                                        <span class=\"truncate\">{$nama}</span>
                                                    </div>
                                                    <span class=\"font-medium whitespace-nowrap\">{$jumlah} {$satuan}</span>
                                                </li>";
                                    })
                                    ->implode('');

                                return new HtmlString(
                                    "<div x-data=\"{ preview: null }\">
                                        <ul class=\"text-sm\">{$rows}</ul>

                                        <div x-show=\"preview\" x-cloak @click=\"preview = null\"
                                             class=\"fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-6 cursor-zoom-out\"
                                             style=\"display: none;\">
                                            <img :src=\"preview\" alt=\"Foto Barang\" class=\"max-w-full max-h-full rounded-lg\" />
                                        </div>
                                    </div>"
                                );
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