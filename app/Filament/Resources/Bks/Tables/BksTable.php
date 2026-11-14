<?php

namespace App\Filament\Resources\Bks\Tables;

use App\Models\Bk;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->groups([
                Group::make('tanggal')
                    ->label('Tanggal')
                    ->date()
                    ->orderQueryUsing(fn ($query) => $query->orderBy('tanggal', 'desc'))
                    ->collapsible(),
            ])
            ->defaultGroup('tanggal')
            // Warna baris: CSS kecil ini disisipkan di sini supaya tidak perlu build CSS. Ubah angka terakhir (0.28 = 28%) untuk kepekatan.
            ->header(new \Illuminate\Support\HtmlString('<style>tr.bkbm-merah { background-color: rgba(220, 38, 38, 0.28) !important; } tr.bkbm-hijau { background-color: rgba(22, 163, 74, 0.28) !important; } tr.bkbm-kuning { background-color: rgba(234, 179, 8, 0.38) !important; }</style>'))
            ->groupingSettingsHidden()
            ->recordUrl(fn (Bk $r) => \App\Filament\Resources\Bks\BkResource::getUrl('view', ['record' => $r]))
            ->recordClasses(fn (Bk $r) => match ($r->warna()) {
                'danger' => 'bkbm-merah',
                'success' => 'bkbm-hijau',
                'warning' => 'bkbm-kuning',
                default => null,
            })
            ->columns([
                TextColumn::make('tanggal')->date('d M Y')->sortable(),
                TextColumn::make('no_nota')->label('No. Nota')->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::cari($query, $search))
                    ->copyable()->copyMessage('No. nota disalin')->copyMessageDuration(1500)->tooltip('Klik untuk menyalin')->disabledClick(),
                TextColumn::make('kategori')->label('Kategori')->weight('bold')
                    ->formatStateUsing(fn ($state) => strtoupper((string) $state))
                    ->color(fn ($state) => match ($state) { 'bkl' => 'info', 'bkk' => 'warning', 'bkr' => 'primary', default => 'gray' }),
                TextColumn::make('tujuan_nota')->label('Kepada'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state, Bk $r) => $r->labelStatus())
                    ->color(fn ($state, Bk $r) => $r->warna()),
                TextColumn::make('hari_di_luar')->label('Di luar')
                    ->getStateUsing(fn (Bk $r) => $r->masihDiLuar() && $r->divalidasi_at
                        ? (int) $r->divalidasi_at->diffInDays(now()) . ' hari' : null)
                    ->placeholder('-')
                    ->color(fn (Bk $r) => $r->masihDiLuar() && $r->divalidasi_at && $r->divalidasi_at->diffInDays(now()) > 30 ? 'danger' : 'gray')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('divalidasi_at', $direction === 'asc' ? 'desc' : 'asc');
                    }),
                TextColumn::make('pembuat.name')->label('Pembuat')->placeholder('-'),
                TextColumn::make('validator.name')->label('Divalidasi Oleh')
                    ->placeholder('Belum divalidasi')->color(fn ($state) => $state ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('kategori')->options(collect(config('bkbm.kategori_bk'))->map(fn ($k) => $k['label'])->all()),
                SelectFilter::make('status')->options(['draft' => 'Draft', 'divalidasi' => 'Barang di luar', 'dipinjam' => 'Dipinjam (belum kembali)', 'selesai' => 'Selesai']),
            ])
            ->recordActions([
                \App\Filament\Resources\Bks\Actions\BarangKembaliAction::make(),
                \App\Filament\Resources\Bks\Actions\CetakBkActions::make(),
                ViewAction::make(),
                EditAction::make()->visible(fn (Bk $r) => ! $r->terkunci()),
                DeleteAction::make()->visible(fn (Bk $r) => ! $r->terkunci()),
            ]);
    }

    /** Pencarian fleksibel: nomor nota, kategori, tujuan, keterangan, pembuat/validator, isi barang, nomor BM terkait. */
    private static function cari(Builder $query, string $search): Builder
    {
        $like = '%' . trim($search) . '%';

        return $query->orWhere(function (Builder $q) use ($like) {
            $q->where('no_nota', 'like', $like)
                ->orWhere('kategori', 'like', $like)
                ->orWhere('tujuan_nota', 'like', $like)
                ->orWhere('keterangan', 'like', $like)
                ->orWhere('ref_no_bkk', 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhereHas('validator', fn ($r) => $r->where('name', 'like', $like))
                ->orWhereHas('pembuat', fn ($r) => $r->where('name', 'like', $like))
                ->orWhereHas('bmTerkait', fn ($r) => $r->where('no_nota', 'like', $like))
                ->orWhereHas('detail', fn ($r) => $r->where('nama_barang', 'like', $like)->orWhere('keterangan', 'like', $like));
        });
    }
}