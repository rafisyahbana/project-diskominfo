<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DokumenPermohonan extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    // Aktifkan auto created_at manual karena kita tidak pakai timestamps() default
    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->created_at ??= now());
    }
}
