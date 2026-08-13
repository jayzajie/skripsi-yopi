<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifikasiTtd extends Model
{
    protected $table = 'verifikasi_ttd';

    protected $fillable = ['surat_id', 'nama_file', 'file', 'valid', 'keterangan', 'diverifikasi_oleh'];

    protected function casts(): array
    {
        return ['valid' => 'boolean'];
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diverifikasi_oleh');
    }

    public function surat(): BelongsTo
    {
        return $this->belongsTo(Surat::class, 'surat_id');
    }
}
