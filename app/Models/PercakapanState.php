<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PercakapanState extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'form_sementara' => 'array',
        'dokumen_diterima' => 'array',
        'riwayat_pesan' => 'array',
    ];
}
