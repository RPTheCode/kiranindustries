<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    use HasFactory, \App\Traits\RecordsActivity;

    protected $fillable = ['name', 'is_active'];
}
