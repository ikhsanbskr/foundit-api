<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemDiscoveryModel extends Model
{
    protected $table            = 'g_item_discoveries';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    
    // Sesuaikan dengan skema tabel g_item_discoveries
    protected $allowedFields    = [
        'user_id', 
        'category_id', 
        'category_detail_id', 
        'bank_id', 
        'report_type', 
        'status', 
        'item_name',
        'location_found', 
        'description', 
        'verification_description', 
        'bounty_amount', 
        'account_number',
        'created_at',
        'updated_at'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
