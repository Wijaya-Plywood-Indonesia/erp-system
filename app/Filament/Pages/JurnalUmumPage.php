<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\JurnalUmum;
use App\Models\SubAnakAkun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;

class JurnalUmumPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $navigationLabel = 'Jurnal Umum';
    protected static ?string $title = 'Jurnal Umum';

    protected string $view = 'filament.pages.jurnal-umum';

    /** HEADER */
    public $tanggal;
    public $kode_jurnal;
    public $no_dokumen;

    /** FORM INPUT */
    public $form = [
        'no_akun' => '',
        'mm' => '',
        'nama' => '',
        'keterangan' => '',
        'map' => 'D', // D / K
        'hit_kbk' => '',
        'banyak' => 0,
        'm3' => 0,
        'harga' => 0,
    ];

    /** DATA */
    public $akunList = [];
    public $items = [];      // DRAFT
    public $jurnals = [];    // FINAL

    public function mount()
    {
        $this->tanggal = now()->format('Y-m-d');
        $this->loadAkun();
        $this->loadJurnalUmum();
    }

    protected function loadAkun()
    {
        $this->akunList = SubAnakAkun::whereHas('anakAkun.indukAkun', function ($q) {
            $q->whereBetween('kode_induk_akun', [1000, 6000]);
        })->get();
    }

    protected function resetForm()
    {
        $this->form = [
            'no_akun' => '',
            'mm' => '',
            'nama' => '',
            'keterangan' => '',
            'map' => 'D',
            'hit_kbk' => '',
            'banyak' => 0,
            'm3' => 0,
            'harga' => 0,
        ];
    }

    /** TAMBAH KE DRAFT */
    public function addItem()
{
    // tentukan dasar perhitungan
    if ($this->form['hit_kbk'] === 'banyak') {
        $qty = $this->form['banyak'];
    } else {
        $qty = $this->form['m3'];
    }

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

    /** TOTAL */
    public function getTotalDebitProperty()
    {
        return collect($this->items)
            ->where('map', 'D')
            ->sum('total');
    }

    public function getTotalKreditProperty()
    {
        return collect($this->items)
            ->where('map', 'K')
            ->sum('total');
    }

    /** SIMPAN JURNAL FINAL */
    public function saveJurnal()
    {
        if ($this->totalDebit !== $this->totalKredit || $this->totalDebit <= 0) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->items as $row) {
                JurnalUmum::create([
                    'tgl' => $this->tanggal,
                    'jurnal' => $this->kode_jurnal,
                    'no_dokumen' => $this->no_dokumen,
                    'no_akun' => $row['no_akun'],
                    'mm' => $row['mm'],
                    'nama' => $row['nama'],
                    'keterangan' => $row['keterangan'],
                    'map' => $row['map'],
                    'hit_kbk' => $row['hit_kbk'],
                    'banyak' => $row['banyak'],
                    'm3' => $row['m3'],
                    'harga' => $row['harga'],
                    'created_by' => Auth::user()->name ?? '-',
                    'status' => 'POSTED',
                ]);
            }
        });

        // reset
        $this->items = [];
        $this->loadJurnalUmum();
    }

    /** LOAD TABLE FINAL */
    public function loadJurnalUmum()
    {
        $this->jurnals = JurnalUmum::orderBy('tgl', 'desc')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();
    }
}
