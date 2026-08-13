<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CadanganData extends Model
{
    protected $table = 'cadangan_data';

    protected $fillable = ['nama_file', 'ukuran', 'dibuat_oleh'];

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh');
    }
}
