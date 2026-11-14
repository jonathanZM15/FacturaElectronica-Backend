<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyCertificate extends Model
{
    protected $fillable = ['emisor_id', 'path', 'password', 'original_name', 'updated_by'];
    protected $hidden = ['path', 'password'];
    protected $casts = ['password' => 'encrypted'];
}
