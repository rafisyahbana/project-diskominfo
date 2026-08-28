<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotifikasiTerkirim extends Model
{
    public $timestamps = false;      // hanya created_at, tidak ada updated_at
    protected $table   = 'notifikasi_terkirim';

    protected $fillable = [
        'no_wa',
        'pesan',
        'status_terkirim',
        'error_pesan',
        'id_permohonan',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
