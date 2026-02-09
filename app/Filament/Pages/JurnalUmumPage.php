<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\JurnalUmum;
use App\Models\SubAnakAkun;
use App\Services\Jurnal\JurnalUmumToJurnal1Service;
use BackedEnum;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use UnitEnum;

class JurnalUmumPage extends Page implements HasForms, HasActions
{
    use InteractsWithActions, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $title = 'Jurnal Umum Faris';
    protected string $view = 'filament.pages.jurnal-umum';

    public $tanggal;
    public $kode_jurnal;
    public $no_dokumen;
    public $form = [
        'no_akun' => '',
        'nama_akun' => '',
        'nama' => '',
        'mm' => '',
        'keterangan' => '',
        'map' => 'D',
        'hit_kbk' => '',
        'banyak' => null,
        'm3' => null,
        'harga' => null,
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

    public function updatedFormNoAkun($value)
    {
        $akun = SubAnakAkun::where('kode_sub_anak_akun', $value)->first();
        $this->form['nama_akun'] = $akun?->nama_sub_anak_akun ?? '';
    }

    public function addItem()
    {
        $qty = $this->form['hit_kbk'] === 'banyak' ? $this->form['banyak'] : $this->form['m3'];
        $total = ($qty ?: 0) * ($this->form['harga'] ?: 0);
        $this->items[] = array_merge($this->form, ['total' => $total]);
        $this->resetForm();
    }

    protected function resetForm()
    {
        $this->form = ['no_akun' => '', 'nama_akun' => '', 'nama' => '', 'mm' => '', 'keterangan' => '', 'map' => 'D', 'hit_kbk' => '', 'banyak' => null, 'm3' => null, 'harga' => null];
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
        if ($this->totalDebit !== $this->totalKredit) {
            Notification::make()->title('Tidak Balance!')->danger()->send();
            return;
        }
        DB::transaction(function () {
            foreach ($this->items as $row) {
                JurnalUmum::create([...$row, 'tgl' => $this->tanggal, 'jurnal' => $this->kode_jurnal, 'no_dokumen' => $this->no_dokumen, 'created_by' => Auth::user()->name, 'status' => 'belum sinkron']);
            }
        });
        $this->items = [];
        $this->loadJurnalUmum();
        Notification::make()->title('Berhasil Simpan Draft')->success()->send();
    }

    public function confirmSync(): void
    {
        Notification::make()
            ->title('Konfirmasi Sinkronisasi')
            ->warning()
            ->actions([
                Action::make('sync')
                    ->label('Ya, Sinkronkan')
                    ->color('danger')
                    ->button()
                    ->close()
                    ->action('syncJurnal'),
                Action::make('cancel')->label('Batal')->close(),
            ])->send();
    }

    public function syncJurnal()
    {
        try {
            $count = app(JurnalUmumToJurnal1Service::class)->sync();
            Notification::make()->title('Berhasil')->body("$count data disinkronkan")->success()->send();
        } catch (\Exception $e) {
            $this->dispatch('log-to-console', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Notification::make()->title('Gagal')->body('Cek Console (F12)')->danger()->send();
        }
        $this->loadJurnalUmum();
    }

    protected function loadJurnalUmum()
    {
        $this->jurnals = JurnalUmum::latest('id')->limit(50)->get();
    }
}
