<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    // Mengunci model ke tabel master kategori induk
    protected $table            = 'gm_category';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    // Proteksi Mass-Assignment
    protected $allowedFields    = ['category_name'];

    // Manajemen Audit Log Waktu
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}