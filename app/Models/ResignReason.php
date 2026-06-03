<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResignReason extends Model
{
    use HasFactory, \App\Traits\RecordsActivity;

    protected $fillable = ['code', 'name', 'is_active'];
}
