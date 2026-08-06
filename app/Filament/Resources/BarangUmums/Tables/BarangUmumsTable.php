<?php

namespace App\Filament\Resources\BarangUmums\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class BarangUmumsTable
{
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

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('satuan')
                    ->label('Satuan')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stok.stok_qty')
                    ->label('Stok Saat Ini')
                    ->numeric(4)
                    ->sortable()
                    ->default(0)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Ditambahkan')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                ImageColumn::make('foto')
                    ->label('Foto')
                    ->disk('public')
                    ->size(60)
                    ->getStateUsing(fn ($record) => static::normalizeFotoPath($record->foto))
                    ->extraImgAttributes(
                        fn($record) => filled($record->foto)
                            ? ['class' => 'cursor-pointer hover:opacity-75 transition rounded-md']
                            : ['style' => 'display:none']
                    )
                    ->action(
                        Action::make('lihatFotoBarang')
                            ->label('Lihat Foto')
                            ->modalHeading('Foto Barang')
                            ->modalWidth(Width::Large)
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                            ->visible(fn($record) => filled($record->foto))
                            ->modalContent(function ($record) {
                                $path = static::normalizeFotoPath($record->foto);

                                if (blank($path)) {
                                    return new HtmlString('<p class="text-sm text-gray-500">Foto tidak ditemukan.</p>');
                                }

                                $url = Storage::disk('public')->url($path);
                                // Rapikan double-slash (mis. akibat APP_URL di .env berakhiran '/')
                                $url = preg_replace('#(?<!:)//+#', '/', $url);

                                return new HtmlString(
                                    "<img src=\"{$url}\" alt=\"Foto Barang\" class=\"w-full h-auto rounded-lg\" />"
                                );
                            })
                    ),
            ])
            ->recordActions([
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