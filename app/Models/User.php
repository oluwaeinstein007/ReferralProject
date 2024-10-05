<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string first_name
 * @property string last_name
 * @property string user_uuid
 * @property string email
 * @property string phone_number
 * @property string profile_picture
 * @property string password
 * @property string social_type
 * @property bool is_social
 * @property bool is_suspended
 * @property int user_role_id
 * @property string otp
 * @property string referral_code
 * @property string title
 */
class User extends Authenticatable implements MustVerifyEmail
{
    // use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

     protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'phone_number',
        'whatsapp_number',
        'gender',
        'date_of_birth',
        'country',
        'ref_balance',
        'task_balance',
        'ref_sort',
        'frozen_page_url',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_country_id',
        'country_id',
        'state_id',
        'city',
        'address',
        'status',
        'user_role_id',
        'level_id',
        'auth_otp',
        'auth_otp_expires_at',
        'payment_otp',
        'payment_otp_expires_at',
        'referral_code',
        'referred_by_user_id_1',
        'referred_by_user_id_2',
        'referral_code_used',
        'social_type',
        'is_social',
        'is_suspended',
        'suspension_reason',
        'suspension_date',
        'suspension_duration',
        'password',
    ];



    // public function getProfilePictureAttribute($value){
    //     // Check if the profile_picture is null or empty
    //     if (empty($value)) {
    //         return asset('images/defaultProfilePicture.jpeg');
    //     }
    //     return $value;
    // }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function role()
    {
        return $this->belongsTo(UserRole::class, 'user_role_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

}
