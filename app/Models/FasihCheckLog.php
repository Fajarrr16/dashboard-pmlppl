<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FasihCheckLog extends Model
{
    use HasFactory;

    protected $table = 'fasih_check_logs';

    protected $fillable = [
        'kartu_kendali_id',
        'status_sebelumnya',
        'status_baru',
        'petugas',
        'keterangan',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kartuKendali(): BelongsTo
    {
        return $this->belongsTo(KartuKendali::class);
    }
}
