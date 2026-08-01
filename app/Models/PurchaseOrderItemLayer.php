<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItemLayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_item_id',
        'urutan',
        'material',
        'qty',
    ];

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
    
    public function barangSetengahJadi(): BelongsTo
{
    return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
}
}