<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'sub_title',
        'youtube_url',
        'hidden_information',
        'levels_id',
        'status',
        'visibility',
        'reward_amount',
        'view_count',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
