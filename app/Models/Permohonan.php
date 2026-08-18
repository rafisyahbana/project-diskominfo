<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Permohonan extends Model
{
    /** @use HasFactory<\Database\Factories\PermohonanFactory> */
    use HasFactory, HasUuids;

    protected $table = 'permohonan';

    protected $fillable = [
        'nik',
        'no_wa',
        'jenis_surat',
        'data_form',
        'status',
        'catatan_petugas',
        'file_surat_url',
        'diproses_oleh',
        'nomor_surat',
    ];

    protected $casts = [
        'data_form' => 'array',
    ];

    public function petugas()
    {
        return $this->belongsTo(Petugas::class, 'diproses_oleh');
    }
}
