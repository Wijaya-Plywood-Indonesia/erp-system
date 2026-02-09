<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
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

class JurnalUmumPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $navigationLabel = 'Jurnal Umum Faris';
    protected static ?string $title = 'Jurnal Umum Faris';

    protected string $view = 'filament.pages.jurnal-umum';
    protected Width|string|null $maxContentWidth = Width::Full;


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

        // RESET TOTAL & DRAFT
        $this->items = [];
        $this->loadJurnalUmum();
    }

    public function syncJurnal()
{
    DB::transaction(function () {
        // Sinkron ke jurnal utama
        app(JurnalUmumToJurnal1Service::class)->sync();

        // Update status jurnal umum
        JurnalUmum::where('status', 'Belum Sinkron')
            ->update([
                'status'    => 'sudah sinkron',
                'synced_at' => now(),
                'synced_by' => Auth::user()->name,
            ]);
    });

    $this->loadJurnalUmum();
}

public function confirmSync(): void
{
    Notification::make()
        ->title('Sinkronisasi Jurnal')
        ->body('Yakin ingin menyinkronkan jurnal? Data yang sudah sinkron tidak bisa diubah.')
        ->warning()
        ->actions([
            Action::make('sync')
                ->label('Ya, Sinkronkan')
                ->color('danger')
                ->button()
                ->close()
                ->action(fn () => $this->syncJurnal()),

            Action::make('cancel')
                ->label('Batal')
                ->close(),
        ])
        ->send();
}



    protected function loadJurnalUmum()
    {
        $this->jurnals = JurnalUmum::latest('tgl')->latest('id')->limit(100)->get();
    }
}
