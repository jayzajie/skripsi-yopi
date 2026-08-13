<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriDokumen extends Model
{
    protected $table = 'kategori_dokumen';

    protected $fillable = ['nama', 'kode', 'deskripsi', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function surat(): HasMany
    {
        return $this->hasMany(Surat::class, 'kategori_id');
    }
}
