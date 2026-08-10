<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SesiVerifikasi extends Model
{
    /** @use HasFactory<\Database\Factories\SesiVerifikasiFactory> */
    use HasFactory, HasUuids;

    protected $table = 'sesi_verifikasi';

    protected $fillable = [
        'no_wa',
        'nik',
        'otp_hash',
        'status',
        'percobaan_gagal',
        'expired_at',
        'berlaku_hingga',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'berlaku_hingga' => 'datetime',
        'percobaan_gagal' => 'integer',
    ];

    public function isVerifiedAndActive(): bool
    {
        return $this->status === 'verified' && $this->berlaku_hingga && now()->lessThanOrEqualTo($this->berlaku_hingga);
    }
}
