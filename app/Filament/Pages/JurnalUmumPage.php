<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Support\Enums\Width;
use App\Models\JurnalUmum;
use App\Models\AnakAkun;
use App\Models\IndukAkun;
use App\Models\SubAnakAkun;
use App\Services\Jurnal\JurnalUmumToJurnal1Service;
use BackedEnum;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use UnitEnum;


class JurnalUmumPage extends Page implements HasActions
{
    use InteractsWithActions;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $title = 'Jurnal Umum';
    protected string $view = 'filament.pages.jurnal-umum';
    protected static ?int $navigationSort = 1;

    protected Width|string|null $maxContentWidth = Width::Full;

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
        'hit_kbk' => 'banyak',
        'banyak' => null,
        'm3' => null,
        'harga' => null,
    ];

    public $akunList = [];
    public $items = [];
    public $jurnals = [];
    public $perPage = 50;
    public $hasMore = true;
    public $isLoading = false;

    public ?int $editingId = null;
    public ?int $deleteId = null;

    public function mount()
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->loadAkun();
        $this->loadJurnalUmum();

        $this->generateKodeJurnal();
    }

    protected function generateKodeJurnal()
    {
        $last = JurnalUmum::max('jurnal');
        $this->kode_jurnal = $last ? $last + 1 : 1;
    }

    protected function loadAkun()
{
    $sub = SubAnakAkun::selectRaw("
        kode_sub_anak_akun as kode,
        nama_sub_anak_akun as nama,
        1 as urutan
    ");

    $anak = AnakAkun::selectRaw("
        kode_anak_akun as kode,
        nama_anak_akun as nama,
        2 as urutan
    ");

    // $induk = IndukAkun::selectRaw("
    //     kode_induk_akun as kode,
    //     nama_induk_akun as nama,
    //     3 as urutan
    // ");

    $union = $sub->unionAll($anak);

    $this->akunList = DB::query()
        ->fromSub($union, 'akun')
        ->orderBy('urutan')
        ->orderBy('kode')
        ->get();
}

    public function updatedFormNoAkun($value)
{
    $this->form['nama_akun'] = '';

    if ($sub = SubAnakAkun::where('kode_sub_anak_akun', $value)->first()) {
        $this->form['nama_akun'] = $sub->nama_sub_anak_akun;
        return;
    }

    if ($anak = AnakAkun::where('kode_anak_akun', $value)->first()) {
        $this->form['nama_akun'] = $anak->nama_anak_akun;
        return;
    }
}

    public function addItem()
    {
        // Validasi paksa di backend
        $this->validate([
            'form.hit_kbk' => 'required',
            'form.no_akun' => 'required',
            'form.harga' => 'required|numeric|min:1',
        ], [
            'form.hit_kbk.required' => 'Menu wajib dipilih!',
        ]);

        $qty = $this->form['hit_kbk'] === 'banyak'
            ? $this->form['banyak']
            : $this->form['m3'];

        // Pastikan qty tidak nol agar tidak merusak perhitungan harga rata-rata nantinya
        if (!$qty || $qty <= 0) {
            Notification::make()->title('Jumlah (Banyak/M3) tidak boleh kosong!')->danger()->send();
            return;
        }

        $total = ($qty ?: 0) * ($this->form['harga'] ?: 0);

        $this->items[] = [
            ...$this->form,
            'total' => $total,
        ];

        $this->resetForm();
    }

    protected function resetForm()
    {
        $this->form = [
            'no_akun' => '',
            'nama_akun' => '',
            'nama' => '',
            'mm' => '',
            'keterangan' => '',
            'map' => 'D',
            'hit_kbk' => 'banyak',
            'banyak' => 1,
            'm3' => null,
            'harga' => null
        ];
    }

    public function getTotalDebitProperty()
    {
        return collect($this->items)
            ->where(fn($item) => strtolower($item['map']) === 'd')
            ->sum('total');
    }
    public function getTotalKreditProperty()
    {
        return collect($this->items)
            ->where(fn($item) => strtolower($item['map']) === 'k')
            ->sum('total');
    }

    public function saveJurnal()
    {
        if ($this->totalDebit !== $this->totalKredit) {
            Notification::make()->title('Tidak Balance!')->danger()->send();
            return;
        }

        DB::transaction(function () {
            foreach ($this->items as $row) {

                JurnalUmum::create([
                    ...$row,
                    'map'      => strtolower($row['map']), // D → d
                    'hit_kbk'  => $row['hit_kbk'] === 'banyak' ? 'b' : 'k',
                    'tgl'      => $this->tanggal,
                    'jurnal'   => $this->kode_jurnal,
                    'no_dokumen' => $this->no_dokumen,
                    'created_by' => Auth::user()->name,
                    'status'     => 'belum sinkron',
                ]);
            }
        });

        $this->items = [];
        $this->loadJurnalUmum();
        $this->generateKodeJurnal();

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

    public function updated($propertyName)
    {
        if ($propertyName === 'form.hit_kbk') {

            if ($this->form['hit_kbk'] === 'banyak') {
                $this->form['banyak'] = 1;
                $this->form['m3'] = null;
            }

            if ($this->form['hit_kbk'] === 'm3') {
                $this->form['banyak'] = null;
            }
        }
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
            'hit_kbk' => $jurnal->hit_kbk === 'b' ? 'banyak' : 'm3',
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
            'map'        => strtolower($this->form['map']), // D → d
            'hit_kbk'    => $this->form['hit_kbk'] === 'banyak' ? 'b' : 'k',
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

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items); // Reset keys
    }

    protected function loadJurnalUmum()
    {
        $this->jurnals = JurnalUmum::latest('id')
            ->take($this->perPage)
            ->get();
    }

    public function loadMore()
    {
        if ($this->isLoading || ! $this->hasMore) {
            return;
        }

        $this->isLoading = true;

        $total = JurnalUmum::count();

        if ($this->perPage >= $total) {
            $this->hasMore = false;
            $this->isLoading = false;
            return;
        }

        $this->perPage += 50;

        if ($this->perPage >= $total) {
            $this->hasMore = false;
        }

        $this->loadJurnalUmum();

        $this->isLoading = false;
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

                    app(\App\Services\Jurnal\JurnalFullSyncService::class)->syncAll();

                    $this->loadJurnalUmum();

                    Notification::make()
                        ->title('Sinkronisasi Berhasil')
                        ->success()
                        ->send();
                }),
        ];
    }
}
