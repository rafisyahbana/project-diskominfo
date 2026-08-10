<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LogAksesAgent extends Model
{
    /** @use HasFactory<\Database\Factories\LogAksesAgentFactory> */
    use HasFactory, HasUuids;

    protected $table = 'log_akses_agent';
    public $timestamps = false;

    protected $fillable = [
        'no_wa',
        'nik',
        'tool_dipanggil',
        'payload',
        'hasil',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
