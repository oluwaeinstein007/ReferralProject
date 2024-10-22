<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'sender_user_id',
        'receiver_account_type',
        'status',
        'otp',
        'amount',
        'transaction_id',
        'link',
        'receiver_user_id',
        'description',
    ];

    // protected $casts = [
    //     'amount' => 'decimal:10,2',
    // ];

    /**
     * Get the sender user that owns the transaction.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * Get the receiver user that owns the transaction.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }
}
