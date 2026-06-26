<?php

namespace App\Filament\Pages;

use App\Models\HargaKayu;
use App\Models\HargaKayuLog;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class UpdateHargaKayu extends Page
{
    use HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Kayu';

    protected static ?string $navigationLabel = 'Update Harga Kayu';

    protected string $view = 'filament.pages.update-harga-kayu';

    public Collection $prices;

    public array $inputs = [];

    public array $originalPrices = [];

    public ?string $rejectionNote = null;

    public bool $hasPending = false;

    public bool $hasRejected = false;

    public bool $showRevisionModal = false;

    public ?int $revisionPriceId = null;

    public bool $showRevisionNoteModal = false;

    public ?string $viewingRevisionNote = null;

    public ?string $revisionUpdatedBy = null;

    public function mount(): void
    {
        $this->loadPrices();
    }

    protected function loadPrices(): void
    {
        $this->hasPending = HargaKayu::where('status', 'pending')->exists();
        $this->hasRejected = HargaKayu::where('status', 'ditolak')->exists();
        $this->prices = HargaKayu::with('jenisKayu')
            ->whereHas('jenisKayu')
            ->orderBy('diameter_terkecil')
            ->get();

        $this->inputs = [];

        foreach ($this->prices as $price) {
            $value = $price->harga_baru ?? $price->harga_beli;
            $this->inputs[$price->id] = $value;
            $this->originalPrices[$price->id] = $price->harga_beli;
        }
    }

    public function getMatrixHeaderProperty(): Collection
    {
        return $this->prices
            ->sortBy('id_jenis_kayu')
            ->groupBy('jenisKayu.nama_kayu')
            ->map(function ($itemsByWood) {
                return $itemsByWood
                    ->groupBy('panjang')
                    ->map(fn ($items) => $items->pluck('grade')->unique()->sort())
                    ->sortKeys();
            });
    }

    public function getDiameterRangesProperty(): Collection
    {
        return $this->prices
            ->map(fn ($item) => (object) [
                'min' => $item->diameter_terkecil,
                'max' => $item->diameter_terbesar,
            ])
            ->unique(fn ($item) => $item->min.'-'.$item->max)
            ->sortBy('min')
            ->values();
    }

    public function getPriceRecord($woodName, $length, $grade, $minD, $maxD)
    {
        return $this->prices
            ->where('jenisKayu.nama_kayu', $woodName)
            ->where('panjang', (int) $length)
            ->where('grade', (int) $grade)
            ->where('diameter_terkecil', $minD)
            ->where('diameter_terbesar', $maxD)
            ->first();
    }

    public function submit(): void
    {
        if ($this->hasPending) {
            return;
        }

        $hasChanges = false;

        DB::transaction(function () use (&$hasChanges) {
            foreach ($this->inputs as $id => $hargaBaru) {
                $hargaKayu = HargaKayu::find($id);

                if (! $hargaKayu) {
                    continue;
                }

                if ((int) $hargaBaru === (int) $hargaKayu->harga_beli) {
                    continue;
                }

                $hasChanges = true;

                $hargaKayu->update([
                    'harga_baru' => (int) $hargaBaru,
                    'status' => 'pending',
                    'updated_by' => Auth::user()->name,
                    'catatan_penolakan' => null,
                ]);
            }
        });

        if (! $hasChanges) {
            Notification::make()->title('Belum ada perubahan harga')->warning()->send();

            return;
        }

        Notification::make()->title('Pengajuan berhasil dibuat')->success()->send();
        $this->loadPrices();
        $this->redirect(request()->header('Referer'));
    }

    public function approve(array $ids = []): void
    {
        if (empty($ids)) {
            Notification::make()->title('Pilih data terlebih dahulu')->warning()->send();

            return;
        }

        DB::transaction(function () use ($ids) {
            $rows = HargaKayu::whereIn('id', $ids)->where('status', 'pending')->get();

            foreach ($rows as $row) {
                HargaKayuLog::create([
                    'id_harga_kayu' => $row->id,
                    'harga_lama' => $row->harga_beli,
                    'harga_baru' => $row->harga_baru,
                    'petugas' => Auth::user()->name,
                    'aksi' => 'Persetujuan Harga',
                ]);

                $row->update([
                    'harga_terakhir' => $row->harga_beli,
                    'harga_beli' => $row->harga_baru,
                    'harga_baru' => null,
                    'status' => null,
                    'catatan_penolakan' => null,
                    'approved_by' => Auth::user()->name,
                ]);
            }
        });

        Notification::make()->title('Harga berhasil disetujui')->success()->send();
        $this->loadPrices();
    }

    public function reject(array $ids = []): void
    {
        if (empty($ids)) {
            Notification::make()->title('Pilih data terlebih dahulu')->warning()->send();

            return;
        }

        HargaKayu::whereIn('id', $ids)
            ->where('status', 'pending')
            ->update([
                'harga_baru' => null,
                'status' => null,
                'catatan_penolakan' => null,
                'approved_by' => Auth::user()->name,
            ]);

        Notification::make()->title('Pengajuan ditolak')->warning()->send();
        $this->loadPrices();
    }

    public function getChangedPricesProperty()
    {
        return $this->prices->filter(function ($price) {
            $current = (int) ($this->inputs[$price->id] ?? 0);

            return $current !== (int) $price->harga_beli;
        });
    }

    public function getPendingPricesProperty()
    {
        return HargaKayu::with('jenisKayu')
            ->whereIn('status', ['pending', 'ditolak'])
            ->orderBy('diameter_terkecil')
            ->get();
    }

    public function cancelSubmission(): void
    {
        HargaKayu::where('status', 'pending')->orWhere('status', 'ditolak')
            ->update([
                'harga_baru' => null,
                'status' => null,
                'approved_by' => null,
                'catatan_penolakan' => null,
            ]);

        Notification::make()->title('Pengajuan berhasil dibatalkan')->success()->send();
        $this->loadPrices();
    }

    public function openRevisionModal(int $id): void
    {
        $this->revisionPriceId = $id;
        $this->rejectionNote = '';
        $this->showRevisionModal = true;
    }

    public function revise(): void
    {
        if (blank($this->rejectionNote)) {
            Notification::make()->title('Catatan revisi wajib diisi')->danger()->send();

            return;
        }

        HargaKayu::whereKey($this->revisionPriceId)->update([
            'status' => 'ditolak',
            'catatan_penolakan' => $this->rejectionNote,
            'approved_by' => Auth::user()->name,
        ]);

        $this->showRevisionModal = false;
        $this->rejectionNote = '';

        Notification::make()->title('Catatan revisi berhasil dikirim')->success()->send();
        $this->loadPrices();
    }

    public function openRevisionNoteModal(int $id): void
    {
        $price = HargaKayu::find($id);
        $this->revisionPriceId = $id;
        $this->viewingRevisionNote = $price?->catatan_penolakan;
        $this->revisionUpdatedBy = $price?->updated_by;
        $this->showRevisionNoteModal = true;
    }

    public function updateRevisionNote(): void
    {
        if (blank($this->viewingRevisionNote)) {
            Notification::make()->title('Catatan revisi tidak boleh kosong')->danger()->send();

            return;
        }

        HargaKayu::whereKey($this->revisionPriceId)->update([
            'catatan_penolakan' => $this->viewingRevisionNote,
            'approved_by' => Auth::user()->name,
        ]);

        $this->showRevisionNoteModal = false;

        Notification::make()->title('Catatan revisi berhasil diperbarui')->success()->send();
        $this->loadPrices();
    }
}
