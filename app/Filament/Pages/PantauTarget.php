<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Target;
use Filament\Actions\Action;
use BackedEnum;
use UnitEnum;
use Livewire\Attributes\Url;

class PantauTarget extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Master';
    protected static ?string $title = 'Pantau Target';

    protected string $view = 'filament.pages.pantau-target';

    #[Url]
    public $mesin_id = '';
    
    #[Url]
    public $search = '';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Buat Target')
                ->url(route('filament.admin.resources.targets.create'))
        ];
    }

    public function getTargetsProperty()
    {
        return Target::query()
            ->with(['mesin', 'ukuranModel', 'jenisKayu', 'kategoriBarang'])
            ->when($this->mesin_id, fn($q) => $q->where('id_mesin', $this->mesin_id))
            ->when($this->search, function($q) {
                $search = $this->search;
                $q->where(function($q2) use ($search) {
                    $q2->whereHas('mesin', fn($q3) => $q3->where('nama_mesin', 'like', "%{$search}%"))
                      ->orWhereHas('jenisKayu', fn($q3) => $q3->where('nama_kayu', 'like', "%{$search}%"))
                      ->orWhere('grade', 'like', "%{$search}%")
                      ->orWhereHas('ukuranModel', function ($q3) use ($search) {
                          $q3->where('panjang', 'like', "%{$search}%")
                             ->orWhere('lebar', 'like', "%{$search}%")
                             ->orWhere('tebal', 'like', "%{$search}%");
                      });
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    public function getMesinsProperty()
    {
        return \App\Models\Mesin::orderBy('nama_mesin')->get();
    }
}

