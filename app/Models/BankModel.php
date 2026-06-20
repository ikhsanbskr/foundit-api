<?php

namespace App\Models;

use CodeIgniter\Model;

class BankModel extends Model
{
    protected $table            = 'gm_bank_type';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['bank_name', 'bank_code'];
}
