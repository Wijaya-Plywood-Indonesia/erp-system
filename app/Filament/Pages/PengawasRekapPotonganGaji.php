<?php

namespace App\Filament\Pages;

use App\Services\Payroll\LiniProduksiResolver;
use App\Services\Payroll\PeriodeJumatKamisResolver;
use App\Services\Payroll\WeeklyRekapPotonganGajiService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class PengawasRekapPotonganGaji extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.pengawas-rekap-potongan-gaji';

    protected static ?string $navigationLabel = 'Rekap Potongan Gaji';

    public ?array $data = [];

    public function mount(): void
    {
        [$mulai, $akhir] = PeriodeJumatKamisResolver::resolveDefault();
        
        // Setup initial temporary values so hitungRekap() can work for default lini
        $this->data = [
            'tanggalMulai' => $mulai,
            'tanggalAkhir' => $akhir,
            'liniTerpilih' => [],
        ];

        $this->form->fill([
            'tanggalMulai' => $mulai,
            'tanggalAkhir' => $akhir,
            'liniTerpilih' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggalMulai')
                    ->label('Tanggal Mulai')
                    ->live(),
                DatePicker::make('tanggalAkhir')
                    ->label('Tanggal Akhir')
                    ->live(),
                Select::make('liniTerpilih')
                    ->label('Lini Produksi')
                    ->options($this->liniOptions())
                    ->multiple()
                    ->searchable()
                    ->placeholder('Pilih Lini')
                    ->live(),
            ])
            ->statePath('data')
            ->columns(3);
    }

    public function updated($property): void
    {
        if (str_starts_with($property, 'data.')) {
            unset($this->rekap);
        }
    }

    public function liniOptions(): array
    {
        return LiniProduksiResolver::options();
    }

    public function isPeriodeValid(): bool
    {
        $mulai = $this->data['tanggalMulai'] ?? null;
        $akhir = $this->data['tanggalAkhir'] ?? null;
        return $mulai && $akhir && $mulai <= $akhir;
    }

    #[Computed]
    public function rekap(): Collection
    {
        if (! $this->isPeriodeValid()) {
            return collect();
        }

        return $this->hitungRekap();
    }

    protected function hitungRekap(): Collection
    {
        return app(WeeklyRekapPotonganGajiService::class)->getRekap(
            $this->data['tanggalMulai'] ?? '',
            $this->data['tanggalAkhir'] ?? '',
            $this->data['liniTerpilih'] ?? []
        );
    }

    public function getTitle(): string
    {
        return 'Rekap Potongan Gaji';
    }
}
