<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'operator_id',
        'gbp_review_id',
        'action_type',
        'action_data',
        'replied_text',
    ];

    protected $casts = [
        'action_data' => 'array',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(OperationPerson::class, 'operator_id');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'gbp_review_id');
    }
}
















