<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorShop extends Model
{
    use HasFactory;

    protected $fillable = [
        'operator_id',
        'shop_id',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(OperationPerson::class, 'operator_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
















