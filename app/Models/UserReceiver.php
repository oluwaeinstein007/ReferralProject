<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserReceiver extends Pivot
{
    protected $table = 'user_receivers';

    protected $fillable = [
        'user_id',
        'receiver_id',
        'payment_status',
        'expires_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
