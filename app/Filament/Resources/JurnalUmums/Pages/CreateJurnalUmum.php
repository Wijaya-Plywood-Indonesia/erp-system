<?php

namespace App\Filament\Resources\JurnalUmums\Pages;

use App\Filament\Resources\JurnalUmums\JurnalUmumResource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use App\Models\JurnalUmum;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class CreateJurnalUmum extends Page
{
    protected static string $resource = JurnalUmumResource::class;

    protected static ?string $title = 'Buat Jurnal Umum';

    public function getView(): string
    {
        return 'filament.pages.jurnal-umum-create';
    }

    /** ================= STATE ================= */
    public string $mode = 'DK'; // DK, DKK, KDD

    public array $header = [
        'tgl' => null,
        'kode_jurnal' => null,
        'no_dokumen' => null,
    ];

    public array $rows = [];

    public function mount(): void
    {
        $this->header['tgl'] = date('Y-m-d');
        // Mengambil kode jurnal terakhir untuk otomasi
        $this->header['jurnal'] = (JurnalUmum::max('jurnal') ?? 0) + 1;

        $this->addRow();
    }

    /** ================= ROW LOGIC ================= */
    public function addRow(): void
    {
        $this->rows[] = [
            'no_akun' => null,
            'keterangan' => null,
            'debit' => 0,
            'kredit' => 0,
        ];
    }

    public function removeRow(int $index): void
    {
        if (count($this->rows) > 1) {
            unset($this->rows[$index]);
            $this->rows = array_values($this->rows);
        }
    }

    /** ================= CALCULATION ================= */
    public function getTotalDebitProperty(): float
    {
        return collect($this->rows)->sum(fn ($r) => (float) ($r['debit'] ?? 0));
    }

    public function getTotalKreditProperty(): float
    {
        return collect($this->rows)->sum(fn ($r) => (float) ($r['kredit'] ?? 0));
    }

    /** ================= SAVE ================= */
    public function save(): void
    {
        if ($this->total_debit !== $this->total_kredit) {
            Notification::make()
                ->title('Gagal Simpan')
                ->body('Jumlah Debit dan Kredit harus seimbang (Balance).')
                ->danger()
                ->send();
            return;
        }

        DB::transaction(function () {
            foreach ($this->rows as $row) {
                // Mencegah manipulasi data dengan validasi backend sebelum create
                JurnalUmum::create([
                    'tgl'         => $this->header['tgl'],
                    'jurnal' => $this->header['jurnal'],
                    'no_dokumen'  => $this->header['no_dokumen'],
                    'no_akun'     => $row['no_akun'],
                    'keterangan'  => $row['keterangan'],
                    'map'         => $row['debit'] > 0 ? 'D' : 'K',
                    'jumlah'      => $row['debit'] > 0 ? $row['debit'] : $row['kredit'],
                    'user_id'     => Auth::id(),
                    'status'      => 'Belum Sinkron',
                ]);
            }
        });

        Notification::make()
            ->title('Berhasil')
            ->body('Jurnal umum telah disimpan.')
            ->success()
            ->send();

        $this->redirect(static::getResource()::getUrl('index'));
    }
}