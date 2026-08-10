<?php

namespace App\Filament\Pages;

use App\Services\NewRekapAbsensiPegawaiService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class NewAbsensi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Rekap Absensi Pegawai';

    protected static ?string $title = 'Rekap Absensi Pegawai';

    protected string $view = 'filament.pages.new-absensi';

    public ?string $tanggal = null;

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->form->fill(['tanggal' => $this->tanggal]);
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('tanggal')
                ->label('Tanggal')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->default(now())
                ->live()
                ->afterStateUpdated(fn ($state) => $this->tanggal = $state),
        ];
    }

    public function getRekap(): Collection
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        return app(NewRekapAbsensiPegawaiService::class)->getRekap($tanggal);
    }

    public function getAbsensiLainLain(): Collection
    {
        $tanggal = $this->tanggal ?? now()->format('Y-m-d');

        return app(NewRekapAbsensiPegawaiService::class)->getAbsensiLainLain($tanggal);
    }
}
