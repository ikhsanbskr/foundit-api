<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryDetailModel extends Model
{
    // Mengunci model ke tabel detail sub-kategori
    protected $table            = 'gm_category_detail';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    // Kolom yang diizinkan untuk dimanipulasi
    protected $allowedFields    = ['category_id', 'detail_name'];

    // Manajemen Audit Log Waktu
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Mengambil data sub-kategori berdasarkan ID Kategori Induk.
     * Digunakan untuk menyuplai data dropdown kedua di React.
     * * @param int $categoryId
     * @return array
     */
    public function getDetailsByCategory(int $categoryId)
    {
        return $this->where('category_id', $categoryId)
                    ->orderBy('id', 'ASC')
                    ->findAll();
    }
}