<?php

namespace App\Models;

use App\Core\Model;

class KategoriTransaksi extends Model
{
    protected string $table = 'kategori_transaksi';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'nama_kategori',
        'jenis',
        'deskripsi',
        'created_at'
    ];

    /**
     * Get categories by jenis (masuk / keluar).
     */
    public function getByJenis(string $jenis): array
    {
        return $this->where(['jenis' => $jenis], 'nama_kategori ASC');
    }
}
