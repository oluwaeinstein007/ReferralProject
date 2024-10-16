<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityRule extends Model
{
    use HasFactory;

    protected $fillable = ['community_id', 'rule'];

    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}
