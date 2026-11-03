<?php

namespace App\Filament\Pages;

use App\Models\PengaturanValidasiOtomatis;
use App\Services\ProduksiLockService;
use App\Services\ValidasiOtomatisProduksiService;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Menu untuk mengatur validasi otomatis produksi:
 * jam, hari ini/besok, tanggal mulai, dan menu produksi mana yang aktif.
 * Hanya Super Admin yang bisa membuka & mengubah.
 */
class PengaturanValidasiOtomatisPage extends Page
{
    protected static ?string $navigationLabel = 'Pengaturan Validasi Otomatis';

    protected static ?string $title = 'Pengaturan Validasi Otomatis Produksi';
    
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static UnitEnum|string|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'pengaturan-validasi-otomatis';

    protected string $view = 'filament.pages.pengaturan-validasi-otomatis';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return ProduksiLockService::isSuperAdmin();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $s = PengaturanValidasiOtomatis::getSettings();

        $this->form->fill([
            'aktif' => (bool) $s->aktif,
            'jam_validasi' => substr((string) $s->jam_validasi, 0, 5),
            'hari_setelah' => (string) $s->hari_setelah,
            'mulai_tanggal' => $s->mulai_tanggal?->toDateString(),
            'menu_aktif' => array_values((array) $s->menu_aktif),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Jadwal')
                    ->description('Berlaku untuk semua menu produksi yang dicentang di bawah.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('aktif')
                            ->label('Aktifkan validasi otomatis')
                            ->helperText('Bila mati, tidak ada produksi yang divalidasi otomatis.')
                            ->live()
                            ->columnSpanFull(),

                        TimePicker::make('jam_validasi')
                            ->label('Jam validasi')
                            ->seconds(false)
                            ->native(false)
                            ->required()
                            ->helperText('Zona waktu WIB (Asia/Jakarta).'),

                        Select::make('hari_setelah')
                            ->label('Divalidasi pada')
                            ->options([
                                '0' => 'Hari yang sama dengan tanggal produksi',
                                '1' => 'Besok (hari setelah tanggal produksi)',
                            ])
                            ->native(false)
                            ->required()
                            ->helperText('Contoh "Besok": produksi tgl 20 divalidasi tgl 21 pada jam di atas.'),

                        DatePicker::make('mulai_tanggal')
                            ->label('Berlaku untuk produksi mulai tanggal')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->required(fn (Get $get): bool => (bool) $get('aktif'))
                            ->helperText('Produksi sebelum tanggal ini tidak ikut divalidasi otomatis, supaya data lama tidak tiba-tiba memotong stok.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Menu produksi yang divalidasi otomatis')
                    ->description('Menu yang tidak dicentang tidak akan divalidasi otomatis (tetap divalidasi manual seperti biasa).')
                    ->schema([
                        CheckboxList::make('menu_aktif')
                            ->hiddenLabel()
                            ->options(ValidasiOtomatisProduksiService::daftarMenu())
                            ->columns(3)
                            ->searchable()
                            ->bulkToggleable()
                            ->gridDirection('row'),
                    ]),

                Section::make('Aturan role (tidak diubah dari menu ini)')
                    ->collapsed()
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('catatan_role')
                            ->hiddenLabel()
                            ->content('Setelah produksi divalidasi (otomatis maupun manual), semua role selain Super Admin tidak bisa mengubah atau menghapus apa pun. Super Admin tetap bebas.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $validKeys = array_keys(ValidasiOtomatisProduksiService::daftarMenu());
        $menu = array_values(array_intersect((array) ($state['menu_aktif'] ?? []), $validKeys));

        if (! empty($state['aktif']) && ! $menu) {
            Notification::make()
                ->title('Pilih minimal satu menu produksi')
                ->body('Validasi otomatis aktif, tetapi belum ada menu produksi yang dicentang.')
                ->warning()
                ->send();

            return;
        }

        PengaturanValidasiOtomatis::getSettings()->update([
            'aktif' => (bool) ($state['aktif'] ?? false),
            'jam_validasi' => $state['jam_validasi'],
            'hari_setelah' => (int) $state['hari_setelah'],
            'mulai_tanggal' => $state['mulai_tanggal'] ?: null,
            'menu_aktif' => $menu,
            'last_updated_by' => auth()->id(),
        ]);

        Notification::make()
            ->title('Pengaturan validasi otomatis disimpan')
            ->success()
            ->send();
    }
}
