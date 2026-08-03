<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1 baris item = 1 pesanan Plywood beserta susunan lapisan veneernya.
 */
class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'id_barang_setengah_jadi_hp',
        'jumlah',
        'keterangan',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function layers(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemLayer::class)->orderBy('urutan');
    }

    public function barangSetengahJadi(): BelongsTo
{
    return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
}
}