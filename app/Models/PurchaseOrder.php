<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'customer_id',
        'tgl_order',
        'tgl_produksi',
        'tgl_kirim',
        'keterangan',
    ];

    protected $casts = [
        'tgl_order' => 'date',
        'tgl_produksi' => 'date',
        'tgl_kirim' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $po) {
            if (empty($po->po_number)) {
                $po->po_number = static::generatePoNumber();
            }
        });
    }

    public static function generatePoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ymd');
        $lastNumber = static::query()
            ->where('po_number', 'like', $prefix . '%')
            ->count();

        return $prefix . str_pad((string) ($lastNumber + 1), 2, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Status keseluruhan PO berdasarkan progres status item:
     * belum_ada, belum_selesai, proses_sebagian, selesai_semua
     */
    public function getStatusAttribute(): string
    {
        $total = $this->items->count();

        if ($total === 0) {
            return 'belum_ada';
        }

        $selesai = $this->items->where('status', true)->count();

        return match (true) {
            $selesai === $total => 'selesai_semua',
            $selesai === 0 => 'belum_selesai',
            default => 'proses_sebagian',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        $total = $this->items->count();
        $selesai = $this->items->where('status', true)->count();

        return match ($this->status) {
            'selesai_semua' => "Selesai Semua ({$selesai}/{$total})",
            'proses_sebagian' => "Proses ({$selesai}/{$total})",
            'belum_selesai' => "Belum Selesai ({$selesai}/{$total})",
            default => 'Belum Ada Barang',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'selesai_semua' => 'success',
            'proses_sebagian' => 'warning',
            'belum_selesai' => 'danger',
            default => 'gray',
        };
    }
}