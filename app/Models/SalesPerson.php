<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesPerson extends Model
{
    use HasFactory;

    protected $table = 'sales_persons';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }
}

