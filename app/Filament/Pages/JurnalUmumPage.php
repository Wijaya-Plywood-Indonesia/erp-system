<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Support\Enums\Width;
use App\Models\JurnalUmum;
use App\Models\SubAnakAkun;
use App\Services\Jurnal\JurnalUmumToJurnal1Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use BackedEnum;
use UnitEnum;

class JurnalUmumPage extends Page implements HasActions
{
    use InteractsWithActions;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $navigationLabel = 'Jurnal Umum Faris';
    protected static ?string $title = 'Jurnal Umum Faris';

    protected string $view = 'filament.pages.jurnal-umum';
    protected Width|string|null $maxContentWidth = Width::Full;

    //     protected function getHeaderActions(): array
    // {
    //     return [];
    // }


    public $tanggal;
    public $kode_jurnal;
    public $no_dokumen;

    public $form = [
        'no_akun'    => '',
        'nama_akun'  => '',
        'nama'       => '',
        'mm'         => '',
        'keterangan' => '',
        'map'        => 'D',
        'hit_kbk'    => '',
        'banyak'     => null,
        'm3'         => null,
        'harga'      => null,
    ];

    public $akunList = [];
    public $items = [];
    public $jurnals = [];

    public ?int $editingId = null;
    public ?int $deleteId = null;

    public function mount()
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->loadAkun();
        $this->loadJurnalUmum();
    }

    protected function loadAkun()
    {
        $this->akunList = SubAnakAkun::orderBy('kode_sub_anak_akun')->get();
    }

    protected function resetForm()
    {
        $this->form = [
            'no_akun'    => '',
            'nama_akun'  => '',
            'nama'       => '',
            'mm'         => '',
            'keterangan' => '',
            'map'        => 'D',
            'hit_kbk'    => '',
            'banyak'     => null,
            'm3'         => null,
            'harga'      => null,
        ];
    }

    public function updatedFormNoAkun($value)
    {
        $akun = SubAnakAkun::where('kode_sub_anak_akun', $value)->first();
        $this->form['nama_akun'] = $akun?->nama_sub_anak_akun ?? '';
    }

    public function addItem()
    {
        $qty = $this->form['hit_kbk'] === 'banyak'
            ? $this->form['banyak']
            : $this->form['m3'];

        $total = ($qty ?: 0) * ($this->form['harga'] ?: 0);

        $this->items[] = [
            ...$this->form,
            'total' => $total,
        ];

        $this->resetForm();
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function getTotalDebitProperty()
    {
        return collect($this->items)->where('map', 'D')->sum('total');
    }

    public function getTotalKreditProperty()
    {
        return collect($this->items)->where('map', 'K')->sum('total');
    }

    public function saveJurnal()
    {
        if ($this->totalDebit !== $this->totalKredit || $this->totalDebit <= 0) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->items as $row) {
                JurnalUmum::create([
                    'tgl'        => $this->tanggal,
                    'jurnal'     => $this->kode_jurnal,
                    'no_dokumen' => $this->no_dokumen,
                    'no_akun'    => $row['no_akun'],
                    'nama_akun'  => $row['nama_akun'],
                    'nama'       => $row['nama'],
                    'mm'         => $row['mm'],
                    'keterangan' => $row['keterangan'],
                    'map'        => $row['map'],
                    'hit_kbk'    => $row['hit_kbk'],
                    'banyak'     => $row['banyak'],
                    'm3'         => $row['m3'],
                    'harga'      => $row['harga'],
                    'created_by' => Auth::user()->name,
                    'status'     => 'belum sinkron',
                ]);
            }
        });

        $this->items = [];
        $this->loadJurnalUmum();
    }

    public function editJurnal(int $id)
    {
        $jurnal = JurnalUmum::findOrFail($id);

        if ($jurnal->status === 'sudah sinkron') {
            Notification::make()
                ->title('Jurnal sudah disinkronkan')
                ->danger()
                ->send();
            return;
        }

        $this->editingId = $id;
        $this->tanggal = $jurnal->tgl;

        $this->form = [
            'no_akun'    => $jurnal->no_akun,
            'nama_akun'  => $jurnal->nama_akun,
            'nama'       => $jurnal->nama,
            'mm'         => $jurnal->mm,
            'keterangan' => $jurnal->keterangan,
            'map'        => $jurnal->map,
            'hit_kbk'    => $jurnal->hit_kbk,
            'banyak'     => $jurnal->banyak,
            'm3'         => $jurnal->m3,
            'harga'      => $jurnal->harga,
        ];

        $this->dispatch('scroll-to-form');
        Notification::make()
            ->title('Mode Edit Aktif')
            ->success()
            ->send();
    }

    public function updateJurnal()
    {
        if (! $this->editingId) {
            return;
        }

        $jurnal = JurnalUmum::find($this->editingId);

        if (! $jurnal || $jurnal->status === 'sudah sinkron') {
            Notification::make()
                ->title('Jurnal tidak bisa diupdate')
                ->danger()
                ->send();
            return;
        }

        $jurnal->update([
            'tgl'        => $this->tanggal,
            'no_akun'    => $this->form['no_akun'],
            'nama_akun'  => $this->form['nama_akun'],
            'nama'       => $this->form['nama'],
            'mm'         => $this->form['mm'],
            'keterangan' => $this->form['keterangan'],
            'map'        => $this->form['map'],
            'hit_kbk'    => $this->form['hit_kbk'],
            'banyak'     => $this->form['banyak'],
            'm3'         => $this->form['m3'],
            'harga'      => $this->form['harga'],
        ]);
        $this->loadJurnalUmum();

        $this->cancelEdit();

        Notification::make()
            ->title('Jurnal berhasil diupdate')
            ->success()
            ->send();
    }

    public function cancelEdit()
    {
        $this->editingId = null;
        $this->resetForm();
    }



    public function confirmDelete(int $id)
    {
        $this->deleteJurnal($id);
    }


    public function deleteJurnal(int $id)
    {
        $jurnal = JurnalUmum::find($id);

        if (! $jurnal || $jurnal->status === 'sudah sinkron') {
            Notification::make()
                ->title('Tidak bisa dihapus')
                ->danger()
                ->send();
            return;
        }

        $jurnal->delete();

        Notification::make()
            ->title('Jurnal berhasil dihapus')
            ->success()
            ->send();

        $this->loadJurnalUmum();
    }


    // public function confirmSync()
    // {
    //     Notification::make()
    //         ->title('Sinkronisasi Jurnal')
    //         ->warning()
    //         ->actions([
    //             Action::make('sync')
    //                 ->label('Ya, Sinkronkan')
    //                 ->color('danger')
    //                 ->button()
    //                 ->action(fn() => $this->syncJurnal()),
    //             Action::make('cancel')
    //                 ->label('Batal')
    //                 ->close(),
    //         ])
    //         ->send();
    // }

    // public function syncJurnal()
    // {
    //     DB::transaction(function () {
    //         app(JurnalUmumToJurnal1Service::class)->sync();

    //         JurnalUmum::where('status', 'belum sinkron')->update([
    //             'status'    => 'sudah sinkron',
    //             'synced_at' => now(),
    //             'synced_by' => Auth::user()->name,
    //         ]);
    //     });

    //     $this->loadJurnalUmum();
    // }

    protected function loadJurnalUmum()
    {
        $this->jurnals = JurnalUmum::latest('tgl')->latest('id')->limit(100)->get();
    }

    protected function getActions(): array
{
    return [
        Action::make('syncJurnal')
            ->label('Sinkronisasi Jurnal')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Sinkronisasi Jurnal Umum')
            ->modalDescription('Yakin ingin menyinkronkan seluruh jurnal umum yang belum disinkron?')
            ->modalSubmitActionLabel('Ya, Sinkronkan')
            ->action(function () {
                app(\App\Services\Jurnal\JurnalUmumToJurnal1Service::class)->sync();
                $this->loadJurnalUmum();
            }),
    ];
}
}