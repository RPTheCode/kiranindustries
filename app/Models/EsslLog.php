<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EsslLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_log_id',
        'download_date',
        'device_id',
        'user_id',
        'log_date',
        'direction',
        'att_direction',
        'c1',
        'c2',
        'c3',
        'c4',
        'c5',
        'c6',
        'c7',
        'work_code',
        'update_flag',
        'file_name',
        'longitude',
        'latitude',
        'is_approved',
        'essl_created_date',
        'essl_last_modified_date',
        'location_address',
        'body_temperature',
        'is_mask_on',
    ];

    protected $casts = [
        'log_date' => 'datetime',
        'download_date' => 'datetime',
        'essl_created_date' => 'datetime',
        'essl_last_modified_date' => 'datetime',
        'is_approved' => 'boolean',
        'is_mask_on' => 'boolean',
    ];

    /**
     * Get the mapped user.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
