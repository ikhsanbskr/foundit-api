<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemImageModel extends Model
{
    protected $table            = 'g_item_images';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    
    // Sesuaikan dengan skema tabel g_item_images
    protected $allowedFields    = [
        'item_id', 
        'image_path', 
        'is_primary', 
        'created_at'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = ''; // Tidak ada updated_at di tabel image
}
