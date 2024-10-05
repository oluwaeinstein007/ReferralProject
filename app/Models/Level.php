<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'referrer_1_percentage',
        'referrer_2_percentage',
        'admin_percentage',
    ];
}
