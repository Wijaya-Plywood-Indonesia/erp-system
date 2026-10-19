<?php

namespace App\Filament\Pages;

use App\Services\Akuntansi\JurnalProduksiSender;
use App\Services\Akuntansi\Produksi\JurnalProduksiRegistry;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Menu "Kirim Jurnal ke Akuntansi": satu baris per jenis produksi, satu tombol
 * Kirim per baris. Jurnal dibangun dari data ERP pada saat tombol ditekan,
 * jadi boleh ditekan kapan saja dan ditekan ulang setelah hasil produksi
 * dilengkapi (selama jurnal di akuntansi masih draft).
 */
class KirimJurnalAkuntansi extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.kirim-jurnal-akuntansi';

    protected static UnitEnum|string|null $navigationGroup = 'Laporan';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Kirim Jurnal ke Akuntansi';

    protected static ?string $title = 'Kirim Jurnal ke Akuntansi';

    protected static ?string $slug = 'kirim-jurnal-akuntansi';

    protected static ?int $navigationSort = 10;

    public string $tanggal = '';

    /** @var array<string, array> keyed by jenis: label, error, jurnals[] */
    public array $ringkasan = [];

    /** @var array<string, array> hasil kirim terakhir per jenis (untuk ditampilkan) */
    public array $hasilKirim = [];

    public function mount(): void
    {
        $this->tanggal = now()->subDay()->format('Y-m-d'); // umumnya yang dikirim = produksi kemarin
        $this->muat();
    }

    public function updatedTanggal(): void
    {
        $this->hasilKirim = [];
        $this->muat();
    }

    public function muat(): void
    {
        $this->ringkasan = [];

        if (! $this->tanggalValid()) {
            return;
        }

        $sender = app(JurnalProduksiSender::class);

        foreach (app(JurnalProduksiRegistry::class)->all() as $key => $source) {
            $item = ['label' => $source->label(), 'error' => null, 'jurnals' => []];

            try {
                foreach ($sender->preview($key, $this->tanggal) as $j) {
                    $item['jurnals'][] = [
                        'no_dokumen' => $j['no_dokumen'],
                        'jumlah_baris' => $j['jumlah_baris'],
                        'total_debit' => $j['total_debit'],
                        'total_kredit' => $j['total_kredit'],
                        'is_balance' => $j['is_balance'],
                        'status' => $j['status'],
                        'terakhir_dikirim' => $j['terakhir_dikirim']?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
                        'terakhir_oleh' => $j['terakhir_oleh'],
                    ];
                }
            } catch (\Throwable $e) {
                report($e);
                $item['error'] = 'Gagal menyiapkan jurnal: '.$e->getMessage();
            }

            $this->ringkasan[$key] = $item;
        }
    }

    public function kirim(string $jenis): void
    {
        if (! $this->tanggalValid()) {
            Notification::make()->danger()->title('Tanggal tidak valid')->send();

            return;
        }

        $hasil = app(JurnalProduksiSender::class)->kirim($jenis, $this->tanggal, auth()->id());

        $this->hasilKirim[$jenis] = $hasil;

        Notification::make()
            ->title($hasil['success'] ? 'Jurnal terkirim' : 'Jurnal belum terkirim')
            ->body($hasil['message'])
            ->color($hasil['success'] ? 'success' : 'danger')
            ->send();

        $this->muat();
    }

    private function tanggalValid(): bool
    {
        try {
            return $this->tanggal !== ''
                && Carbon::createFromFormat('Y-m-d', $this->tanggal)->format('Y-m-d') === $this->tanggal;
        } catch (\Throwable) {
            return false;
        }
    }
}