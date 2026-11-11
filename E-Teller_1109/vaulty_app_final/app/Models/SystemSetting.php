<?php
/* Tujuan file: Model ini merepresentasikan data system setting di basis data serta menjadi tempat definisi atribut dan relasi yang digunakan aplikasi. */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];
} 